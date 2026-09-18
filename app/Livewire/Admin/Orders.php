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
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination, WithFileUploads;

    // Order Import Modal
    public bool $showImportModal = false;
    public $orderImportFile = null;

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

        // Calculate Prev / Next IDs efficiently using indexed queries
        $this->prevOrderId = Order::where('id', '>', $id)->orderBy('id', 'asc')->value('id');
        $this->nextOrderId = Order::where('id', '<', $id)->orderBy('id', 'desc')->value('id');

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
            $query->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
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

        // Date Range Filter (Index-Friendly Timestamp Ranges)
        if ($this->filterDate === 'today') {
            $query->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
        } elseif ($this->filterDate === 'yesterday') {
            $yesterday = now()->subDay();
            $query->whereBetween('created_at', [$yesterday->startOfDay(), $yesterday->endOfDay()]);
        } elseif ($this->filterDate === 'last7') {
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
        } elseif ($this->filterDate === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
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

    public function processOrderImport()
    {
        $this->validate([
            'orderImportFile' => 'required|file|max:10240',
        ]);

        try {
            $path = $this->orderImportFile->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                session()->flash('error', 'Unable to open uploaded orders file.');
                return;
            }

            $rawHeader = fgetcsv($handle);
            if (!$rawHeader) {
                fclose($handle);
                session()->flash('error', 'Uploaded file is empty.');
                return;
            }

            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeader[0]);
            $headerMap = [];
            foreach ($rawHeader as $idx => $col) {
                $headerMap[trim(strtolower($col))] = $idx;
            }

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                if (empty(array_filter($data))) continue;
                $phone = trim($data[$headerMap['customer_phone'] ?? 1] ?? '');
                if (empty($phone)) continue;

                $rows[] = [
                    'customer_name' => trim($data[$headerMap['customer_name'] ?? 0] ?? 'Customer'),
                    'customer_phone' => $phone,
                    'villa_number' => trim($data[$headerMap['villa_number'] ?? 2] ?? ''),
                    'street_address' => trim($data[$headerMap['street_address'] ?? 3] ?? ''),
                    'payment_method' => strtolower(trim($data[$headerMap['payment_method'] ?? 4] ?? 'cash')),
                    'payment_status' => strtolower(trim($data[$headerMap['payment_status'] ?? 5] ?? 'unpaid')),
                    'status' => strtolower(trim($data[$headerMap['status'] ?? 6] ?? 'delivered')),
                    'notes' => trim($data[$headerMap['notes'] ?? 7] ?? ''),
                    'item_sku_or_name' => trim($data[$headerMap['item_sku_or_name'] ?? 8] ?? 'General Grocery'),
                    'item_quantity' => max(1, intval($data[$headerMap['item_quantity'] ?? 9] ?? 1)),
                    'item_price' => max(0, floatval($data[$headerMap['item_price'] ?? 10] ?? 0)),
                ];
            }
            fclose($handle);

            $importedCount = 0;
            \Illuminate\Support\Facades\DB::transaction(function () use ($rows, &$importedCount) {
                $grouped = [];
                foreach ($rows as $r) {
                    $grouped[$r['customer_phone']][] = $r;
                }

                foreach ($grouped as $phone => $items) {
                    $first = $items[0];
                    $customer = \App\Models\Customer::firstOrCreate(
                        ['phone' => $phone],
                        [
                            'name' => $first['customer_name'],
                            'villa_number' => $first['villa_number'],
                            'street_address' => $first['street_address'],
                            'zone' => 'Default',
                        ]
                    );

                    $orderNumber = 'ORD-' . strtoupper(\Illuminate\Support\Str::random(8));
                    $subtotal = 0;
                    foreach ($items as $i) {
                        $subtotal += ($i['item_quantity'] * $i['item_price']);
                    }

                    $order = Order::create([
                        'order_number' => $orderNumber,
                        'customer_id' => $customer->id,
                        'customer_name_snapshot' => $customer->name,
                        'customer_phone_snapshot' => $customer->phone,
                        'customer_villa' => $first['villa_number'],
                        'customer_address' => $first['street_address'],
                        'delivery_address' => $first['street_address'],
                        'status' => $first['status'],
                        'payment_method' => $first['payment_method'],
                        'payment_status' => $first['payment_status'],
                        'subtotal' => $subtotal,
                        'total_amount' => $subtotal,
                        'order_source' => 'import',
                        'notes' => $first['notes'] ?: 'Imported via CSV',
                    ]);

                    foreach ($items as $i) {
                        $p = Product::where('sku', $i['item_sku_or_name'])->orWhere('name', $i['item_sku_or_name'])->first();
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $p?->id,
                            'product_name' => $p ? $p->name : $i['item_sku_or_name'],
                            'sku' => $p?->sku,
                            'unit_price' => $i['item_price'],
                            'quantity' => $i['item_quantity'],
                            'subtotal' => $i['item_quantity'] * $i['item_price'],
                        ]);
                    }
                    $importedCount++;
                }
            });

            $this->showImportModal = false;
            $this->orderImportFile = null;
            session()->flash('message', "Successfully imported {$importedCount} customer orders.");
        } catch (\Throwable $e) {
            session()->flash('error', "Orders import failed: " . $e->getMessage());
        }
    }

    public function render()
    {
        // 1. Consolidated Operational KPIs in a SINGLE SQL query (7x reduction in DB roundtrips)
        $slaThreshold = now()->subMinutes(45);
        $kpiRow = \Illuminate\Support\Facades\DB::table('orders')->selectRaw("
            COUNT(CASE WHEN status IN ('awaiting_whatsapp', 'pending') THEN 1 END) as kpi_new,
            COUNT(CASE WHEN status = 'awaiting_whatsapp' THEN 1 END) as kpi_awaiting,
            COUNT(CASE WHEN status = 'preparing' THEN 1 END) as kpi_preparing,
            COUNT(CASE WHEN status = 'ready' THEN 1 END) as kpi_ready,
            COUNT(CASE WHEN status = 'out_for_delivery' THEN 1 END) as kpi_out_for_delivery,
            COALESCE(SUM(CASE WHEN status IN ('awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery') AND payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as kpi_cod_outstanding,
            COUNT(CASE WHEN status IN ('awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery') AND created_at < ? THEN 1 END) as kpi_late
        ", [$slaThreshold])->first();

        // 2. Fetch Paginated Orders for Table View
        $orders = $this->getBaseQuery()->paginate(25);

        // 3. For Kanban Board View, fetch only recent active orders with targeted fields
        $boardOrders = [];
        if ($this->viewMode === 'board') {
            $activeList = Order::with([
                'customer:id,name,phone,villa_number,zone',
                'deliveryStaff:id,name,role',
                'items:id,order_id,product_name,quantity,unit_price,total'
            ])
            ->whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
            ->orderBy('id', 'desc')
            ->limit(60)
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
        $deliveryDrivers = User::whereIn('role', ['delivery', 'staff', 'admin', 'super_admin'])->get(['id', 'name', 'role']);

        return view('livewire.admin.orders', [
            'orders' => $orders,
            'boardOrders' => $boardOrders,
            'deliveryDrivers' => $deliveryDrivers,
            'kpis' => [
                'new' => (int) ($kpiRow->kpi_new ?? 0),
                'awaiting' => (int) ($kpiRow->kpi_awaiting ?? 0),
                'preparing' => (int) ($kpiRow->kpi_preparing ?? 0),
                'ready' => (int) ($kpiRow->kpi_ready ?? 0),
                'out_for_delivery' => (int) ($kpiRow->kpi_out_for_delivery ?? 0),
                'cod_outstanding' => (float) ($kpiRow->kpi_cod_outstanding ?? 0.00),
                'late' => (int) ($kpiRow->kpi_late ?? 0),
            ],
        ])->layout('components.layouts.app', ['title' => 'Baqqala — Order Management & Fulfillment Control Center']);
    }
}
