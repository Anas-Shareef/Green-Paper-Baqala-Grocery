import React, { useEffect, useState } from 'react';
import { ShoppingBag, Eye, RefreshCw, CheckCircle, XCircle, Truck, Package, Clock } from 'lucide-react';
import { adminApi } from '../services/api';

export function OrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedStatus, setSelectedStatus] = useState('');
  const [activeOrder, setActiveOrder] = useState(null);

  const fetchOrders = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getOrders({ status: selectedStatus });
      if (res.success) setOrders(res.data || []);
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
      if (res.success) {
        if (activeOrder && activeOrder.id === orderId) {
          setActiveOrder(res.data);
        }
        fetchOrders();
      }
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Customer Orders</h1>
          <p className="text-sm text-slate-400">View customer orders, update delivery status, and inspect items</p>
        </div>
        <select
          value={selectedStatus}
          onChange={(e) => setSelectedStatus(e.target.value)}
          className="px-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-emerald-500"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="preparing">Preparing</option>
          <option value="out_for_delivery">Out for Delivery</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-300">
            <thead className="text-xs uppercase bg-slate-950 text-slate-400 border-b border-slate-800">
              <tr>
                <th className="px-4 py-3.5">Order Number</th>
                <th className="px-4 py-3.5">Customer</th>
                <th className="px-4 py-3.5">Phone</th>
                <th className="px-4 py-3.5">Total</th>
                <th className="px-4 py-3.5">Status</th>
                <th className="px-4 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="6" className="text-center py-8 text-slate-500">
                    <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-emerald-400" />
                    Loading orders...
                  </td>
                </tr>
              ) : orders.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-8 text-slate-500">No orders found.</td>
                </tr>
              ) : (
                orders.map((o) => (
                  <tr key={o.id} className="hover:bg-slate-800/40">
                    <td className="px-4 py-3.5 font-bold text-white">{o.order_number}</td>
                    <td className="px-4 py-3.5 text-slate-300">{o.customer_name}</td>
                    <td className="px-4 py-3.5 text-slate-400">{o.customer_phone}</td>
                    <td className="px-4 py-3.5 font-bold text-emerald-400">${parseFloat(o.total).toFixed(2)}</td>
                    <td className="px-4 py-3.5">
                      <select
                        value={o.status}
                        onChange={(e) => handleUpdateStatus(o.id, e.target.value)}
                        className="px-3 py-1 bg-slate-950 border border-slate-800 rounded-lg text-xs font-semibold capitalize text-slate-200 focus:outline-none focus:border-emerald-500"
                      >
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="preparing">Preparing</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </td>
                    <td className="px-4 py-3.5 text-right">
                      <button
                        onClick={() => setActiveOrder(o)}
                        className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-medium inline-flex items-center gap-1.5"
                      >
                        <Eye className="w-3.5 h-3.5" /> Details
                      </button>
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
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-lg space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div>
                <h3 className="font-bold text-lg text-white">Order {activeOrder.order_number}</h3>
                <p className="text-xs text-slate-400">{activeOrder.created_at}</p>
              </div>
              <button onClick={() => setActiveOrder(null)} className="text-slate-400 hover:text-white">✕</button>
            </div>

            <div className="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2 text-xs">
              <p><span className="text-slate-400">Customer:</span> <strong className="text-white">{activeOrder.customer_name}</strong> ({activeOrder.customer_phone})</p>
              <p><span className="text-slate-400">Delivery Address:</span> <strong className="text-white">{activeOrder.delivery_address}</strong></p>
              <p><span className="text-slate-400">Payment Method:</span> <strong className="text-white uppercase">{activeOrder.payment_method}</strong> ({activeOrder.payment_status})</p>
            </div>

            <div className="space-y-2">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400">Order Items</h4>
              <div className="divide-y divide-slate-800 border border-slate-800 rounded-xl overflow-hidden">
                {activeOrder.items?.map((item) => (
                  <div key={item.id} className="p-3 bg-slate-950 flex items-center justify-between text-xs">
                    <div>
                      <span className="font-semibold text-white block">{item.product_name}</span>
                      <span className="text-slate-400">{item.quantity} x ${parseFloat(item.unit_price).toFixed(2)}</span>
                    </div>
                    <span className="font-bold text-emerald-400">${parseFloat(item.total).toFixed(2)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="pt-2 border-t border-slate-800 flex items-center justify-between font-bold text-base text-white">
              <span>Grand Total</span>
              <span className="text-emerald-400">${parseFloat(activeOrder.total).toFixed(2)}</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
