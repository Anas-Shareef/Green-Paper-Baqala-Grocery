<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    protected $listeners = ['orderReceived' => '$refresh'];

    public string $statusTab = 'all'; // all, pending, accepted, preparing, out_for_delivery, delivered, cancelled
    public string $search = '';

    // Order Detail Modal
    public bool $showDetailModal = false;
    public ?Order $viewingOrder = null;
    public ?int $selectedDeliveryStaffId = null;

    public function viewOrder(int $id)
    {
        $order = Order::with(['items', 'customer', 'deliveryStaff'])->findOrFail($id);
        $this->viewingOrder = $order;
        $this->selectedDeliveryStaffId = $order->delivery_staff_id;
        $this->showDetailModal = true;
    }

    public function updateStatus(string $status, OrderService $orderService, WhatsAppService $whatsAppService)
    {
        if (!$this->viewingOrder) return;

        $order = Order::findOrFail($this->viewingOrder->id);

        switch ($status) {
            case 'accepted':
                $orderService->acceptOrder($order);
                break;
            case 'preparing':
                $orderService->prepareOrder($order);
                break;
            case 'out_for_delivery':
                $orderService->outForDelivery($order, $this->selectedDeliveryStaffId);
                break;
            case 'delivered':
                $orderService->deliverOrder($order);
                break;
            case 'cancelled':
                $orderService->cancelOrder($order, 'Cancelled from Admin Board');
                break;
        }

        // Send WhatsApp status notification
        $whatsAppService->sendOrderStatusUpdate($order, $status);

        $this->viewOrder($order->id);
        session()->flash('message', "Order #{$order->order_number} status updated to " . strtoupper($status) . "!");
    }

    public function render()
    {
        $query = Order::with(['customer', 'deliveryStaff'])->orderBy('id', 'desc');

        if ($this->statusTab !== 'all') {
            $query->where('status', $this->statusTab);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                    ->orWhere('customer_villa', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', function ($cq) {
                        $cq->where('name', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%");
                    });
            });
        }

        return view('livewire.admin.orders', [
            'orders' => $query->paginate(15),
            'deliveryDrivers' => User::where('role', 'delivery')->orWhere('role', 'staff')->get(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Order Lifecycle & Delivery']);
    }
}
