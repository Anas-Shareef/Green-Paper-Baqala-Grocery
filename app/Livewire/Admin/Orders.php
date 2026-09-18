<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WhatsAppOrderService;
use App\Services\WhatsAppService;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    protected $listeners = [
        'orderReceived' => '$refresh',
        'refreshOrders' => '$refresh',
    ];

    // View Mode: 'list' (Table) or 'board' (Kanban)
    public string $viewMode = 'list';

    // Status Filter Tabs
    public string $statusTab = 'active'; // active, all, awaiting_whatsapp, confirmed, preparing, ready, out_for_delivery, delivered, exceptions

    // Smart Filters Shortcut
    public string $smartFilter = ''; // needs_attention, late, awaiting_whatsapp, cod_unpaid, unassigned, ready_to_deliver, today

    // Toolbar Filters & Search
    public string $search = '';
    public string $filterPayment = ''; // '', pending, paid
    public string $filterSource = ''; // '', PWA, POS, Admin, WhatsApp
    public string $filterDriver = ''; // '', unassigned, or user id
    public string $filterZone = '';
    public string $filterDate = 'all'; // all, today, yesterday, last7, month
    public string $sort = 'urgent'; // urgent, newest, oldest, highest_total, lowest_total

    // Bulk Actions Selection
    public array $selectedOrders = [];
    public bool $selectAll = false;
    public string $bulkActionChoice = '';
    public ?int $bulkDriverId = null;

    // Slide-out Order Detail Drawer
    public bool $showDrawer = false;
    public ?int $viewingOrderId = null;
    public ?Order $viewingOrder = null;
    public ?int $selectedDeliveryStaffId = null;
    public ?int $prevOrderId = null;
    public ?int $nextOrderId = null;

    // Picking Mode Modal
    public bool $showPickingModal = false;
    public string $barcodeInput = '';
    public ?string $pickingSuccessMsg = null;
    public ?string $pickingErrorMsg = null;

    // COD Collection Modal
    public bool $showCodModal = false;
    public ?int $codOrderId = null;
    public float $codAmount = 0.00;
    public string $codDiffReason = '';

    // Cancellation Modal
    public bool $showCancelModal = false;
    public ?int $cancelOrderId = null;
    public string $cancelReason = 'Customer cancelled';
    public string $cancelCustomReason = '';

    // Failed Delivery Modal
    public bool $showFailedModal = false;
    public ?int $failedOrderId = null;
    public string $failedReason = 'Customer unavailable';
    public string $failedNotes = '';

    // Delivery Run Sheet Modal
    public bool $showDeliverySheetModal = false;
    public array $deliverySheetOrders = [];

    // Confirmation Alert Flash
    public ?string $bannerMessage = null;

    public function mount()
    {
        // Default to active orders tab
        $this->statusTab = 'active';
    }

    public function updatedSelectAll(bool $value)
    {
        if ($value) {
            $this->selectedOrders = $this->getBaseQuery()->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }

    public function setStatusTab(string $tab)
    {
        $this->statusTab = $tab;
        $this->smartFilter = '';
        $this->resetPage();
    }

    public function setSmartFilter(string $filter)
    {
        $this->smartFilter = ($this->smartFilter === $filter) ? '' : $filter;
        $this->resetPage();
    }

    public function setViewMode(string $mode)
    {
        $this->viewMode = $mode;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusTab = 'active';
        $this->smartFilter = '';
        $this->filterPayment = '';
        $this->filterSource = '';
        $this->filterDriver = '';
        $this->filterZone = '';
        $this->filterDate = 'all';
        $this->sort = 'urgent';
        $this->selectedOrders = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /**
     * View Order in Slide-Out Drawer
     */
    public function viewOrder(int $id)
    {
        $this->viewingOrderId = $id;
        $this->loadViewingOrder();

        // Calculate Prev / Next IDs in current view
        $allIds = $this->getBaseQuery()->pluck('id')->toArray();
        $currentIndex = array_search($id, $allIds);
        $this->prevOrderId = ($currentIndex !== false && isset($allIds[$currentIndex - 1])) ? $allIds[$currentIndex - 1] : null;
        $this->nextOrderId = ($currentIndex !== false && isset($allIds[$currentIndex + 1])) ? $allIds[$currentIndex + 1] : null;

        $this->showDrawer = true;
    }

    public function closeDrawer()
    {
        $this->showDrawer = false;
        $this->viewingOrder = null;
        $this->viewingOrderId = null;
    }

    public function navigateOrder(string $direction)
    {
        if ($direction === 'prev' && $this->prevOrderId) {
            $this->viewOrder($this->prevOrderId);
        } elseif ($direction === 'next' && $this->nextOrderId) {
            $this->viewOrder($this->nextOrderId);
        }
    }

    protected function loadViewingOrder()
    {
        if (!$this->viewingOrderId) return;
        $this->viewingOrder = Order::with([
            'customer.orders',
            'customer.defaultAddress',
            'items.product',
            'deliveryStaff',
            'activities',
            'statusHistory',
        ])->find($this->viewingOrderId);

        if ($this->viewingOrder) {
            $this->selectedDeliveryStaffId = $this->viewingOrder->delivery_staff_id;
        }
    }

    /**
     * Advance Order to Recommended Next Action
     */
    public function executeNextAction(int $orderId, OrderService $orderService, WhatsAppService $whatsAppService)
    {
        $order = Order::findOrFail($orderId);

        switch ($order->status) {
            case 'awaiting_whatsapp':
            case 'pending':
                $orderService->confirmOrder($order, auth()->user());
                $whatsAppService->sendOrderStatusUpdate($order, 'accepted');
                $this->flashBanner("Order #{$order->order_number} confirmed! Stock reserved.");
                break;

            case 'confirmed':
            case 'accepted':
                $orderService->startPreparing($order, auth()->user());
                $whatsAppService->sendOrderStatusUpdate($order, 'preparing');
                $this->flashBanner("Order #{$order->order_number} is now in preparation / picking.");
                break;

            case 'preparing':
                $orderService->markReady($order, auth()->user());
                $whatsAppService->sendOrderStatusUpdate($order, 'ready');
                $this->flashBanner("Order #{$order->order_number} marked READY for delivery dispatch.");
                break;

            case 'ready':
                if (!$order->delivery_staff_id && $this->selectedDeliveryStaffId) {
                    $orderService->assignDriver($order, $this->selectedDeliveryStaffId, auth()->user());
                }
                $orderService->dispatchOrder($order, $order->delivery_staff_id, null, auth()->user());
                $whatsAppService->sendOrderStatusUpdate($order, 'out_for_delivery');
                $this->flashBanner("Order #{$order->order_number} is OUT FOR DELIVERY.");
                break;

            case 'out_for_delivery':
                $this->openCodModal($order->id);
                return;
        }

        if ($this->viewingOrderId === $orderId) {
            $this->loadViewingOrder();
        }
    }

    /**
     * Driver Assignment
     */
    public function assignDriver(int $orderId, ?int $driverId, OrderService $orderService)
    {
        if (!$driverId) return;
        $order = Order::findOrFail($orderId);
        $orderService->assignDriver($order, $driverId, auth()->user());
        $this->flashBanner("Driver assigned to Order #{$order->order_number}.");

        if ($this->viewingOrderId === $orderId) {
            $this->loadViewingOrder();
        }
    }

    /**
     * Picking Workflow
     */
    public function openPickingModal(int $orderId)
    {
        $this->viewOrder($orderId);
        $this->barcodeInput = '';
        $this->pickingSuccessMsg = null;
        $this->pickingErrorMsg = null;
        $this->showPickingModal = true;
    }

    public function closePickingModal()
    {
        $this->showPickingModal = false;
        $this->barcodeInput = '';
    }

    public function scanBarcode(OrderService $orderService)
    {
        $code = trim($this->barcodeInput);
        if (empty($code) || !$this->viewingOrder) return;

        // Search product by barcode or SKU
        $product = Product::where('barcode', $code)->orWhere('sku', $code)->first();
        if (!$product) {
            $this->pickingErrorMsg = "No product found with barcode: {$code}";
            $this->pickingSuccessMsg = null;
            $this->barcodeInput = '';
            return;
        }

        // Find corresponding item in viewing order
        $item = $this->viewingOrder->items->firstWhere('product_id', $product->id);
        if (!$item) {
            $this->pickingErrorMsg = "Item '{$product->name}' is NOT in this order!";
            $this->pickingSuccessMsg = null;
            $this->barcodeInput = '';
            return;
        }

        $newQty = min($item->quantity, $item->picked_quantity + 1);
        $orderService->pickItem($this->viewingOrder, $item->id, $newQty, auth()->user());

        $this->pickingSuccessMsg = "✓ Scanned {$product->name} ({$newQty}/{$item->quantity} picked)";
        $this->pickingErrorMsg = null;
        $this->barcodeInput = '';

        $this->loadViewingOrder();
    }

    public function toggleItemPicked(int $itemId, OrderService $orderService)
    {
        if (!$this->viewingOrder) return;
        $item = $this->viewingOrder->items->find($itemId);
        if (!$item) return;

        $targetQty = ($item->picked_quantity >= $item->quantity) ? 0 : $item->quantity;
        $orderService->pickItem($this->viewingOrder, $item->id, $targetQty, auth()->user());
        $this->loadViewingOrder();
    }

    public function completePicking(OrderService $orderService, WhatsAppService $whatsAppService)
    {
        if (!$this->viewingOrder) return;
        $orderService->markReady($this->viewingOrder, auth()->user());
        $whatsAppService->sendOrderStatusUpdate($this->viewingOrder, 'ready');
        $this->showPickingModal = false;
        $this->loadViewingOrder();
        $this->flashBanner("Picking completed! Order #{$this->viewingOrder->order_number} is READY for delivery.");
    }

    /**
     * COD Collection Modal & Action
     */
    public function openCodModal(int $orderId)
    {
        $order = Order::findOrFail($orderId);
        $this->codOrderId = $order->id;
        $this->codAmount = (float) $order->total_amount;
        $this->codDiffReason = '';
        $this->showCodModal = true;
    }

    public function closeCodModal()
    {
        $this->showCodModal = false;
        $this->codOrderId = null;
    }

    public function confirmCodCollection(OrderService $orderService, WhatsAppService $whatsAppService)
    {
        if (!$this->codOrderId) return;
        $order = Order::findOrFail($this->codOrderId);

        $expected = (float) $order->total_amount;
        $diff = abs($this->codAmount - $expected);

        if ($diff > 0.01 && empty(trim($this->codDiffReason))) {
            $this->addError('codDiffReason', 'Please provide a reason for the cash collection difference.');
            return;
        }

        $orderService->collectCod($order, $this->codAmount, $this->codDiffReason, auth()->user());
        if ($order->status !== 'delivered') {
            $orderService->deliverOrder($order, auth()->user());
        }

        $whatsAppService->sendOrderStatusUpdate($order, 'delivered');
        $this->closeCodModal();

        if ($this->viewingOrderId === $order->id) {
            $this->loadViewingOrder();
        }

        $this->flashBanner("COD payment of AED " . number_format($this->codAmount, 2) . " recorded. Order marked DELIVERED.");
    }

    /**
     * Cancellation Modal & Action
     */
    public function openCancelModal(int $orderId)
    {
        $this->cancelOrderId = $orderId;
        $this->cancelReason = 'Customer cancelled';
        $this->cancelCustomReason = '';
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelOrderId = null;
    }

    public function confirmCancelOrder(OrderService $orderService, WhatsAppService $whatsAppService)
    {
        if (!$this->cancelOrderId) return;
        $order = Order::findOrFail($this->cancelOrderId);
        $finalReason = $this->cancelReason === 'Other' ? trim($this->cancelCustomReason) : $this->cancelReason;

        if (empty($finalReason)) {
            $finalReason = 'Cancelled by administrator';
        }

        $orderService->cancelOrder($order, $finalReason, auth()->user());
        $whatsAppService->sendOrderStatusUpdate($order, 'cancelled');

        $this->closeCancelModal();

        if ($this->viewingOrderId === $order->id) {
            $this->loadViewingOrder();
        }

        $this->flashBanner("Order #{$order->order_number} cancelled. Reserved stock released back to inventory.");
    }

    /**
     * Failed Delivery Modal & Action
     */
    public function openFailedModal(int $orderId)
    {
        $this->failedOrderId = $orderId;
        $this->failedReason = 'Customer unavailable';
        $this->failedNotes = '';
        $this->showFailedModal = true;
    }

    public function closeFailedModal()
    {
        $this->showFailedModal = false;
        $this->failedOrderId = null;
    }

    public function confirmFailedDelivery(OrderService $orderService)
    {
        if (!$this->failedOrderId) return;
        $order = Order::findOrFail($this->failedOrderId);

        $orderService->failDelivery($order, $this->failedReason, $this->failedNotes, auth()->user());
        $this->closeFailedModal();

        if ($this->viewingOrderId === $order->id) {
            $this->loadViewingOrder();
        }

        $this->flashBanner("Delivery attempt for Order #{$order->order_number} marked as FAILED ({$this->failedReason}).");
    }

    /**
     * Bulk Action Execution
     */
    public function executeBulkAction(OrderService $orderService)
    {
        if (empty($this->selectedOrders) || empty($this->bulkActionChoice)) return;

        $orders = Order::whereIn('id', $this->selectedOrders)->get();
        $actor = auth()->user();
        $count = 0;

        foreach ($orders as $o) {
            switch ($this->bulkActionChoice) {
                case 'confirm':
                    if (in_array($o->status, ['awaiting_whatsapp', 'pending'])) {
                        $orderService->confirmOrder($o, $actor);
                        $count++;
                    }
                    break;
                case 'prepare':
                    if (in_array($o->status, ['confirmed', 'accepted'])) {
                        $orderService->startPreparing($o, $actor);
                        $count++;
                    }
                    break;
                case 'ready':
                    if ($o->status === 'preparing') {
                        $orderService->markReady($o, $actor);
                        $count++;
                    }
                    break;
                case 'assign_driver':
                    if ($this->bulkDriverId) {
                        $orderService->assignDriver($o, $this->bulkDriverId, $actor);
                        $count++;
                    }
                    break;
                case 'cancel':
                    if (!in_array($o->status, ['delivered', 'cancelled'])) {
                        $orderService->cancelOrder($o, 'Bulk cancellation from Admin Control Center', $actor);
                        $count++;
                    }
                    break;
                case 'delivery_sheet':
                    $this->deliverySheetOrders = $orders->toArray();
                    $this->showDeliverySheetModal = true;
                    return;
            }
        }

        $this->selectedOrders = [];
        $this->selectAll = false;
        $this->bulkActionChoice = '';
        $this->bulkDriverId = null;

        $this->flashBanner("Bulk action completed on {$count} orders.");
    }

    /**
     * Export Filtered Orders to CSV
     */
    public function exportCsv()
    {
        $orders = $this->getBaseQuery()->get();
        $csvHeader = [
            'Order No',
            'Customer Order',
            'Customer Name',
            'Phone',
            'Villa',
            'Zone',
            'Delivery Address',
            'Subtotal (AED)',
            'Delivery Fee (AED)',
            'Total Amount (AED)',
            'Payment Method',
            'Payment Status',
            'Status',
            'Driver',
            'Source',
            'Created At',
            'Delivered At',
        ];

        $filename = 'baqqala_orders_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($orders, $csvHeader) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $csvHeader);

            foreach ($orders as $o) {
                fputcsv($output, [
                    $o->order_number,
                    $o->customer_order_number,
                    $o->customer_name_snapshot ?: ($o->customer->name ?? 'Walk-in'),
                    $o->customer_phone_snapshot ?: ($o->customer->phone ?? ''),
                    $o->customer_villa ?? '',
                    $o->customer?->zone ?? '',
                    $o->customer_address ?? '',
                    $o->subtotal,
                    $o->delivery_charge,
                    $o->total_amount,
                    $o->payment_method,
                    $o->payment_status,
                    $o->status,
                    $o->deliveryStaff?->name ?? 'Unassigned',
                    $o->order_source,
                    $o->created_at->format('Y-m-d H:i:s'),
                    $o->delivered_at ? $o->delivered_at->format('Y-m-d H:i:s') : '',
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Helper to flash messages
     */
    protected function flashBanner(string $msg)
    {
        $this->bannerMessage = $msg;
        session()->flash('message', $msg);
    }

    /**
     * Base Filtered Query
     */
    protected function getBaseQuery()
    {
        $query = Order::with(['customer', 'deliveryStaff', 'items'])->orderBy('id', 'desc');

        // Status Tabs Filter
        if ($this->statusTab === 'active') {
            $query->whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery']);
        } elseif ($this->statusTab === 'exceptions') {
            $query->whereIn('status', ['cancelled', 'expired', 'failed_delivery', 'returned', 'refunded']);
        } elseif ($this->statusTab !== 'all') {
            $query->where('status', $this->statusTab);
        }

        // Smart Filters
        if ($this->smartFilter === 'needs_attention') {
            $query->whereIn('status', ['awaiting_whatsapp', 'pending', 'failed_delivery']);
        } elseif ($this->smartFilter === 'late') {
            $query->whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
                  ->where('created_at', '<', now()->subMinutes(45));
        } elseif ($this->smartFilter === 'awaiting_whatsapp') {
            $query->where('status', 'awaiting_whatsapp');
        } elseif ($this->smartFilter === 'cod_unpaid') {
            $query->where('payment_status', 'pending');
        } elseif ($this->smartFilter === 'unassigned') {
            $query->whereIn('status', ['ready', 'preparing', 'confirmed', 'accepted'])
                  ->whereNull('delivery_staff_id');
        } elseif ($this->smartFilter === 'ready_to_deliver') {
            $query->where('status', 'ready');
        } elseif ($this->smartFilter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        }

        // Payment Filter
        if ($this->filterPayment) {
            $query->where('payment_status', $this->filterPayment);
        }

        // Order Source Filter
        if ($this->filterSource) {
            $query->where('order_source', $this->filterSource);
        }

        // Driver Filter
        if ($this->filterDriver === 'unassigned') {
            $query->whereNull('delivery_staff_id');
        } elseif (!empty($this->filterDriver)) {
            $query->where('delivery_staff_id', (int) $this->filterDriver);
        }

        // Zone Filter
        if ($this->filterZone) {
            $query->where(function ($zq) {
                $zq->where('customer_address', 'like', "%{$this->filterZone}%")
                   ->orWhereHas('customer', function ($cq) {
                       $cq->where('zone', $this->filterZone);
                   });
            });
        }

        // Date Range Filter
        if ($this->filterDate === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($this->filterDate === 'yesterday') {
            $query->whereDate('created_at', now()->subDay()->toDateString());
        } elseif ($this->filterDate === 'last7') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->filterDate === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        // Search
        if (!empty(trim($this->search))) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhere('customer_villa', 'like', "%{$s}%")
                  ->orWhere('customer_name_snapshot', 'like', "%{$s}%")
                  ->orWhere('customer_phone_snapshot', 'like', "%{$s}%")
                  ->orWhere('customer_address', 'like', "%{$s}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('name', 'like', "%{$s}%")
                         ->orWhere('phone', 'like', "%{$s}%");
                  })
                  ->orWhereHas('items', function ($iq) use ($s) {
                      $iq->where('product_name', 'like', "%{$s}%");
                  });
            });
        }

        // Sorting
        if ($this->sort === 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($this->sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($this->sort === 'highest_total') {
            $query->orderBy('total_amount', 'desc');
        } elseif ($this->sort === 'lowest_total') {
            $query->orderBy('total_amount', 'asc');
        } else {
            // 'urgent' default: prioritize awaiting and ready, then newest
            $query->orderByRaw("
                CASE 
                    WHEN status = 'awaiting_whatsapp' THEN 1
                    WHEN status = 'pending' THEN 2
                    WHEN status = 'ready' THEN 3
                    WHEN status IN ('confirmed', 'accepted') THEN 4
                    WHEN status = 'preparing' THEN 5
                    WHEN status = 'out_for_delivery' THEN 6
                    ELSE 7
                END ASC
            ")->orderBy('id', 'desc');
        }

        return $query;
    }

    public function render()
    {
        // 1. Calculate Real-Time Operational KPIs
        $kpiNew = Order::whereIn('status', ['awaiting_whatsapp', 'pending'])->count();
        $kpiAwaiting = Order::where('status', 'awaiting_whatsapp')->count();
        $kpiPreparing = Order::where('status', 'preparing')->count();
        $kpiReady = Order::where('status', 'ready')->count();
        $kpiOutForDelivery = Order::where('status', 'out_for_delivery')->count();

        $kpiCodOutstanding = (float) Order::whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
            ->where('payment_status', 'pending')
            ->sum('total_amount');

        $kpiLate = Order::whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
            ->where('created_at', '<', now()->subMinutes(45))
            ->count();

        // 2. Fetch Paginated Orders for Table View
        $orders = $this->getBaseQuery()->paginate(25);

        // 3. For Kanban Board View, fetch active orders grouped by pipeline
        $boardOrders = [];
        if ($this->viewMode === 'board') {
            $activeList = Order::with(['customer', 'deliveryStaff', 'items'])
                ->whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
                ->orderBy('id', 'desc')
                ->get();

            $boardOrders = [
                'awaiting' => $activeList->whereIn('status', ['awaiting_whatsapp', 'pending']),
                'confirmed' => $activeList->whereIn('status', ['confirmed', 'accepted']),
                'preparing' => $activeList->where('status', 'preparing'),
                'ready' => $activeList->where('status', 'ready'),
                'out_for_delivery' => $activeList->where('status', 'out_for_delivery'),
            ];
        }

        // 4. Delivery Drivers List
        $deliveryDrivers = User::whereIn('role', ['delivery', 'staff', 'admin', 'super_admin'])->get();

        return view('livewire.admin.orders', [
            'orders' => $orders,
            'boardOrders' => $boardOrders,
            'deliveryDrivers' => $deliveryDrivers,
            'kpis' => [
                'new' => $kpiNew,
                'awaiting' => $kpiAwaiting,
                'preparing' => $kpiPreparing,
                'ready' => $kpiReady,
                'out_for_delivery' => $kpiOutForDelivery,
                'cod_outstanding' => $kpiCodOutstanding,
                'late' => $kpiLate,
            ],
        ])->layout('components.layouts.app', ['title' => 'Baqqala — Order Management & Fulfillment Control Center']);
    }
}
