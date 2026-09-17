import React, { useEffect, useState } from 'react';
import { ShoppingBag, Eye, RefreshCw, MessageCircle, Copy, Check, CheckCircle } from 'lucide-react';
import { adminApi } from '../services/api';
import { useAdminRealtime } from '../context/AdminRealtimeContext';

export function OrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedStatus, setSelectedStatus] = useState('');
  const [activeOrder, setActiveOrder] = useState(null);
  const [copied, setCopied] = useState(false);

  const { refreshRealtime } = useAdminRealtime();

  const fetchOrders = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getOrders({ status: selectedStatus });
      if (res && res.data) {
        setOrders(res.data.data || res.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [selectedStatus]);

  const handleUpdateStatus = async (orderId, newStatus) => {
    try {
      const res = await adminApi.updateOrderStatus(orderId, newStatus);
      if (res && (res.success || res.data)) {
        const updated = res.data || res;
        if (activeOrder && activeOrder.id === orderId) {
          setActiveOrder(updated);
        }
        fetchOrders();
        refreshRealtime();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const generateWhatsAppText = (order) => {
    if (!order) return '';
    const custOrderNum = order.customer_order_number || 1;
    const villa = order.customer_villa ? `Villa ${order.customer_villa}` : '';
    const rawAddress = order.customer_address || order.delivery_address || '';
    
    // Deduplicate villa/zone if needed
    let fullAddr = villa ? `${villa}, ${rawAddress}` : rawAddress;
    
    let text = `Hello Baqqala,\n\n`;
    text += `I would like to place an order.\n\n`;
    text += `Customer Order: #${custOrderNum}\n`;
    text += `Baqqala Order ID: ${order.order_number}\n\n`;
    text += `Customer:\n${order.customer_name_snapshot || order.customer_name || 'Customer'}\n${order.customer_phone_snapshot || order.customer_phone || ''}\n\n`;
    text += `Delivery Address:\n${fullAddr}\n`;
    if (order.notes || order.customer_notes_snapshot) {
      text += `Notes: ${order.notes || order.customer_notes_snapshot}\n`;
    }
    text += `\nItems:\n`;
    order.items?.forEach(i => {
      text += `• ${i.product_name} × ${i.quantity}\n`;
    });
    text += `\nSubtotal: AED ${parseFloat(order.subtotal || order.total_amount || 0).toFixed(2)}\n`;
    text += `Delivery: FREE\n`;
    text += `Total: AED ${parseFloat(order.total_amount || order.total || 0).toFixed(2)}\n\n`;
    text += `Payment Method:\nCash on Delivery\n\n`;
    text += `Please confirm my order.\n\nThank you.`;

    return text;
  };

  const handleCopyText = (text) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 3000);
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header & Status Filter Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <ShoppingBag className="w-6 h-6 text-emerald-600" />
            Customer Orders
          </h1>
          <p className="text-xs text-slate-500 mt-1">Automatic order entry, WhatsApp confirmation & delivery fulfillment</p>
        </div>

        <div className="flex items-center gap-2">
          <button onClick={fetchOrders} className="p-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-bold transition-colors">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 shadow-xs"
          >
            <option value="">All Order Statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="preparing">Preparing</option>
            <option value="out_for_delivery">Out for Delivery</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      {/* Orders Table */}
      <div className="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-slate-50 border-b border-slate-200 font-bold text-slate-900 uppercase text-[10px] tracking-wider">
              <tr>
                <th className="p-4">Global Order ID</th>
                <th className="p-4">Customer Order</th>
                <th className="p-4">Customer</th>
                <th className="p-4">Delivery Address</th>
                <th className="p-4 text-right">Total</th>
                <th className="p-4 text-center">WhatsApp</th>
                <th className="p-4">Status</th>
                <th className="p-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="8" className="p-8 text-center text-slate-400">Loading customer orders...</td>
                </tr>
              ) : orders.length === 0 ? (
                <tr>
                  <td colSpan="8" className="p-8 text-center text-slate-400">No customer orders found.</td>
                </tr>
              ) : (
                orders.map((o) => (
                  <tr key={o.id} className="hover:bg-slate-50 transition-colors">
                    <td className="p-4 font-bold text-slate-900 font-mono text-sm">{o.order_number}</td>
                    <td className="p-4">
                      <span className="px-2.5 py-1 bg-emerald-100 text-emerald-900 border border-emerald-200 rounded-full font-extrabold font-mono text-xs">
                        Customer #{o.customer_order_number || 1}
                      </span>
                    </td>
                    <td className="p-4 font-bold text-slate-900">
                      <div>{o.customer_name_snapshot || o.customer_name}</div>
                      <div className="text-[10px] text-slate-500 font-mono font-semibold">{o.customer_phone_snapshot || o.customer_phone}</div>
                    </td>
                    <td className="p-4">
                      <div className="font-bold text-slate-900">{o.customer_villa ? `Villa ${o.customer_villa}` : 'Villa Delivery'}</div>
                      <div className="text-[10px] text-slate-500 line-clamp-1">{o.customer_address || o.delivery_address}</div>
                    </td>
                    <td className="p-4 text-right font-mono font-black text-emerald-700 text-sm">
                      AED {parseFloat(o.total_amount || o.total || 0).toFixed(2)}
                    </td>
                    <td className="p-4 text-center">
                      <span className="inline-flex items-center gap-1 text-[10px] uppercase font-extrabold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        {o.whatsapp_status || 'prepared'}
                      </span>
                    </td>
                    <td className="p-4">
                      <select
                        value={o.status}
                        onChange={(e) => handleUpdateStatus(o.id, e.target.value)}
                        className="px-2.5 py-1 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 outline-none focus:border-emerald-600 capitalize"
                      >
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="preparing">Preparing</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </td>
                    <td className="p-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        {o.status === 'pending' && (
                          <button
                            onClick={() => handleUpdateStatus(o.id, 'confirmed')}
                            className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-extrabold shadow-xs transition-colors"
                          >
                            Accept & Reserve
                          </button>
                        )}
                        <button
                          onClick={() => setActiveOrder(o)}
                          className="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-bold inline-flex items-center gap-1 transition-colors"
                        >
                          <Eye className="w-3.5 h-3.5 text-emerald-600" /> Details
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Order Detail Modal */}
      {activeOrder && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-lg space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl text-slate-900">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-[10px] uppercase font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                    Status: {activeOrder.status.toUpperCase()}
                  </span>
                  <span className="text-xs font-extrabold font-mono bg-emerald-600 text-white px-2.5 py-0.5 rounded-full">
                    Customer Order #{activeOrder.customer_order_number || 1}
                  </span>
                </div>
                <h3 className="font-mono font-black text-xl text-slate-900 mt-1">{activeOrder.order_number}</h3>
              </div>
              <button onClick={() => setActiveOrder(null)} className="text-slate-400 hover:text-slate-700 font-bold">&times;</button>
            </div>

            {/* Customer Details Snapshot */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
              <p><span className="text-slate-500 font-medium">Customer Name:</span> <strong className="text-slate-900">{activeOrder.customer_name_snapshot || activeOrder.customer_name}</strong></p>
              <p><span className="text-slate-500 font-medium">Phone Number:</span> <strong className="text-slate-900 font-mono">{activeOrder.customer_phone_snapshot || activeOrder.customer_phone}</strong></p>
              <p><span className="text-slate-500 font-medium">Delivery Address:</span> <strong className="text-slate-900">{activeOrder.customer_villa ? `Villa ${activeOrder.customer_villa}, ` : ''}{activeOrder.customer_address || activeOrder.delivery_address}</strong></p>
              <p><span className="text-slate-500 font-medium">Payment Method:</span> <strong className="text-slate-900 uppercase font-mono">{activeOrder.payment_method}</strong> ({activeOrder.payment_status})</p>
            </div>

            {/* WhatsApp Message Preview Box */}
            <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-2 text-xs">
              <div className="flex justify-between items-center font-extrabold text-emerald-950">
                <span className="flex items-center gap-1.5">
                  <MessageCircle className="w-4 h-4 text-emerald-700" />
                  WhatsApp Prefilled Message
                </span>
                <button
                  onClick={() => handleCopyText(generateWhatsAppText(activeOrder))}
                  className="px-2.5 py-1 bg-white hover:bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-lg text-[11px] font-bold flex items-center gap-1 transition-colors"
                >
                  {copied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5 text-slate-500" />}
                  {copied ? 'Copied' : 'Copy'}
                </button>
              </div>
              <textarea
                readOnly
                value={generateWhatsAppText(activeOrder)}
                className="w-full bg-white border border-emerald-200 text-slate-800 font-mono text-[11px] rounded-xl p-3 outline-none h-28 resize-none"
              ></textarea>
            </div>

            {/* Order Items */}
            <div className="space-y-2">
              <h4 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Order Items ({activeOrder.items?.length || 0})</h4>
              <div className="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden">
                {activeOrder.items?.map((item) => (
                  <div key={item.id} className="p-3 bg-white flex items-center justify-between text-xs">
                    <div>
                      <span className="font-bold text-slate-900 block">{item.product_name}</span>
                      <span className="text-slate-500">{item.quantity} &times; AED {parseFloat(item.unit_price).toFixed(2)}</span>
                    </div>
                    <span className="font-mono font-bold text-emerald-700">AED {parseFloat(item.total).toFixed(2)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="pt-3 border-t border-slate-100 flex items-center justify-between font-mono font-black text-slate-900 text-base">
              <span>TOTAL DUE:</span>
              <span className="text-xl text-emerald-700">AED {parseFloat(activeOrder.total_amount || activeOrder.total || 0).toFixed(2)}</span>
            </div>

            {/* Order Status Transition Actions */}
            <div className="pt-2 flex gap-2">
              {activeOrder.status === 'pending' && (
                <button
                  onClick={() => handleUpdateStatus(activeOrder.id, 'confirmed')}
                  className="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase rounded-xl shadow-md flex items-center justify-center gap-1.5 transition-all"
                >
                  <CheckCircle className="w-4 h-4" /> Accept & Reserve Stock
                </button>
              )}
            </div>

          </div>
        </div>
      )}
    </div>
  );
}
