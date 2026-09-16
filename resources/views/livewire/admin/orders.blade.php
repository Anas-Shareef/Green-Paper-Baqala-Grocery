<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Order Lifecycle Management
                <span class="badge badge-emerald">Transactional Engine</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Track online & POS grocery orders, assign delivery staff, and monitor gross & net profit metrics.</p>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif

    <!-- Status Tabs & Search -->
    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold">
            @foreach(['all' => 'All Orders', 'pending' => 'Pending', 'accepted' => 'Accepted', 'preparing' => 'Preparing', 'out_for_delivery' => 'Out For Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $key => $label)
            <button wire:click="$set('statusTab', '{{ $key }}')" 
                    class="px-3.5 py-2 rounded-xl transition-all {{ $statusTab === $key ? 'bg-emerald-600 text-white font-black shadow-md shadow-emerald-600/20' : 'bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200 border border-slate-200' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div class="w-full md:w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search order #, customer, villa..." class="input-field font-mono">
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head">
                    <tr>
                        <th class="p-3.5">Order No</th>
                        <th class="p-3.5">Customer / Villa</th>
                        <th class="p-3.5">Source & Pay</th>
                        <th class="p-3.5 text-right">Revenue</th>
                        <th class="p-3.5 text-right">Product Cost</th>
                        <th class="p-3.5 text-right">Net Profit</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($orders as $o)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-mono font-black text-slate-900 text-sm">
                            {{ $o->order_number }}
                            <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $o->created_at->format('d M, H:i') }}</div>
                        </td>
                        <td class="tbl-cell">
                            <div class="font-extrabold text-slate-900 text-sm">{{ $o->customer?->name ?? 'Walk-in Cashier' }}</div>
                            <div class="text-[10px] text-emerald-700 font-semibold mt-0.5">{{ $o->customer_villa ?? 'POS Counter' }}</div>
                        </td>
                        <td class="tbl-cell">
                            <span class="badge badge-slate">
                                {{ $o->order_source }} • {{ $o->payment_method }}
                            </span>
                        </td>
                        <td class="tbl-cell text-right font-mono font-black text-slate-900 text-sm">₹{{ number_format($o->total_amount, 2) }}</td>
                        <td class="tbl-cell text-right font-mono text-slate-500">₹{{ number_format($o->product_cost, 2) }}</td>
                        <td class="tbl-cell text-right font-mono font-bold text-emerald-700">₹{{ number_format($o->net_profit, 2) }}</td>
                        <td class="tbl-cell text-center">
                            @if($o->status === 'delivered')
                            <span class="badge badge-emerald">{{ str_replace('_', ' ', $o->status) }}</span>
                            @elseif($o->status === 'pending')
                            <span class="badge badge-amber">{{ str_replace('_', ' ', $o->status) }}</span>
                            @elseif($o->status === 'cancelled')
                            <span class="badge badge-rose">{{ str_replace('_', ' ', $o->status) }}</span>
                            @else
                            <span class="badge badge-slate">{{ str_replace('_', ' ', $o->status) }}</span>
                            @endif
                        </td>
                        <td class="tbl-cell text-right">
                            <button wire:click="viewOrder({{ $o->id }})" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg text-xs font-extrabold transition-all">
                                Manage Order
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-slate-400 font-medium">No orders found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $orders->links() }}
        </div>
    </div>

    <!-- MODAL: Order Detail & Status Transition Modal -->
    @if($showDetailModal && $viewingOrder)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-2xl shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-xl font-mono">{{ $viewingOrder->order_number }}</h3>
                    <p class="text-xs text-slate-500">Placed on {{ $viewingOrder->created_at->format('d M Y, H:i') }} via {{ $viewingOrder->order_source }}</p>
                </div>
                <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <!-- Customer & Delivery Info Grid -->
            <div class="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div>
                    <div class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Customer Details</div>
                    <div class="font-extrabold text-slate-900 text-sm mt-1">{{ $viewingOrder->customer?->name ?? 'Walk-in Customer' }}</div>
                    <div class="text-slate-600">Phone: +{{ $viewingOrder->customer?->phone ?? 'N/A' }}</div>
                    <div class="text-emerald-700 font-semibold">Villa: {{ $viewingOrder->customer_villa ?? 'N/A' }}</div>
                    <div class="text-slate-500 mt-1">{{ $viewingOrder->customer_address ?? 'N/A' }}</div>
                </div>
                <div>
                    <div class="font-bold text-slate-500 uppercase text-[10px] tracking-wider">Delivery Staff Assignment</div>
                    <select wire:model="selectedDeliveryStaffId" class="input-field mt-1">
                        <option value="">Unassigned Driver</option>
                        @foreach($deliveryDrivers as $dd)
                        <option value="{{ $dd->id }}">{{ $dd->name }} ({{ strtoupper($dd->role) }})</option>
                        @endforeach
                    </select>
                    <div class="mt-2 text-slate-500">Internal Delivery Cost: <strong class="text-amber-700 font-mono">₹{{ number_format($viewingOrder->internal_delivery_cost, 2) }}</strong></div>
                </div>
            </div>

            <!-- Items Table Snapshot -->
            <div class="space-y-2">
                <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Ordered Items Breakdown</h4>
                <div class="bg-white rounded-xl overflow-hidden border border-slate-200 shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-3">Product Name</th>
                                <th class="p-3 text-right">Qty</th>
                                <th class="p-3 text-right">Unit Price</th>
                                <th class="p-3 text-right font-mono text-rose-600">Wholesale Cost</th>
                                <th class="p-3 text-right font-mono text-emerald-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($viewingOrder->items as $item)
                            <tr>
                                <td class="p-3 text-slate-900 font-bold">{{ $item->product_name }}</td>
                                <td class="p-3 text-right font-mono text-slate-700">{{ $item->quantity }}</td>
                                <td class="p-3 text-right font-mono text-slate-600">₹{{ number_format($item->unit_price, 2) }}</td>
                                <td class="p-3 text-right font-mono text-rose-600">₹{{ number_format($item->wholesale_cost, 2) }}</td>
                                <td class="p-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($item->total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Net Profit Formula Summary -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs space-y-1.5 font-mono">
                <div class="flex justify-between text-slate-600"><span>Retail Revenue Subtotal:</span><span>₹{{ number_format($viewingOrder->subtotal, 2) }}</span></div>
                <div class="flex justify-between text-rose-600"><span>Total Product Wholesale Cost:</span><span>-₹{{ number_format($viewingOrder->product_cost, 2) }}</span></div>
                <div class="flex justify-between text-amber-600"><span>Internal Delivery Cost:</span><span>-₹{{ number_format($viewingOrder->internal_delivery_cost, 2) }}</span></div>
                <div class="flex justify-between font-black text-emerald-700 text-sm pt-2 border-t border-slate-200">
                    <span>NET PROFIT EARNED:</span>
                    <span>₹{{ number_format($viewingOrder->net_profit, 2) }}</span>
                </div>
            </div>

            <!-- Status Transition Buttons -->
            <div class="space-y-2 pt-2">
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Change Order Status & Notify Customer via WhatsApp:</div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="updateStatus('accepted')" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm">Accept</button>
                    <button wire:click="updateStatus('preparing')" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm">Preparing</button>
                    <button wire:click="updateStatus('out_for_delivery')" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-sm">Out for Delivery</button>
                    <button wire:click="updateStatus('delivered')" class="btn-glow py-2 px-4 text-xs">Delivered</button>
                    <button wire:click="updateStatus('cancelled')" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-sm">Cancel Order</button>
                </div>
            </div>

            <!-- Download Invoice & Close -->
            <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                <a href="{{ route('invoice.download', ['order' => $viewingOrder->order_number]) }}" target="_blank" class="btn-outline">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Customer Invoice PDF
                </a>
                <button wire:click="$set('showDetailModal', false)" class="btn-outline">Close</button>
            </div>

        </div>
    </div>
    @endif

</div>
