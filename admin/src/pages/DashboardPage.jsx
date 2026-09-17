import React, { useEffect, useState } from 'react';
import { DollarSign, ShoppingBag, Clock, CheckCircle2, AlertTriangle, RefreshCw, Check, ArrowRight } from 'lucide-react';
import { adminApi } from '../services/api';
import { useAdminRealtime } from '../context/AdminRealtimeContext';
import { useNavigate } from 'react-router-dom';

export function DashboardPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  const { pendingOrdersCount, latestPendingOrders, refreshRealtime } = useAdminRealtime();

  const fetchDashboard = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getDashboard();
      if (res.success || res.data) {
        setData(res.data || res);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const handleAcceptAndReserve = async (orderId) => {
    try {
      await adminApi.updateOrderStatus(orderId, 'confirmed');
      fetchDashboard();
      refreshRealtime();
    } catch (e) {
      console.error(e);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="flex items-center gap-3 text-emerald-600">
          <RefreshCw className="w-6 h-6 animate-spin" />
          <span className="font-semibold text-sm">Loading Baqqala Grocery analytics...</span>
        </div>
      </div>
    );
  }

  const metrics = data?.metrics || {};
  const pendingList = latestPendingOrders.length > 0 ? latestPendingOrders : (data?.pending_action_orders || []);

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Overview Dashboard</h1>
          <p className="text-xs text-slate-500 mt-1">Real-time grocery metrics, live order notifications & stock receiving</p>
        </div>
        <button
          onClick={() => {
            fetchDashboard();
            refreshRealtime();
          }}
          className="p-2.5 bg-white border border-slate-300 rounded-xl text-slate-600 hover:text-slate-900 shadow-xs transition-colors"
        >
          <RefreshCw className="w-4 h-4" />
        </button>
      </div>

      {/* Metric Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Today's Sales</span>
            <div className="w-9 h-9 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-700">
              <DollarSign className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-black text-slate-900 font-mono">AED {parseFloat(metrics.today_sales || 0).toFixed(2)}</div>
          <p className="text-xs text-slate-500 font-medium">{metrics.today_orders || 0} orders placed today</p>
        </div>

        <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Pending Orders</span>
            <div className="w-9 h-9 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-700">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-black text-slate-900 font-mono">{pendingOrdersCount || metrics.pending_orders || 0}</div>
          <p className="text-xs text-amber-700 font-bold">Requires action / stock reservation</p>
        </div>

        <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Delivered</span>
            <div className="w-9 h-9 rounded-xl bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-700">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-black text-slate-900 font-mono">{metrics.delivered_orders || 0}</div>
          <p className="text-xs text-slate-500 font-medium">Successfully delivered to villa</p>
        </div>

        <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Low Stock Alerts</span>
            <div className="w-9 h-9 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center text-rose-700">
              <AlertTriangle className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-black text-slate-900 font-mono">{metrics.low_stock_count || 0}</div>
          <p className="text-xs text-slate-500 font-medium">{metrics.out_of_stock || 0} items out of stock</p>
        </div>
      </div>

      {/* PENDING ORDERS REQUIRING ACTION SECTION (PRD Section 28 & 29) */}
      <div className="bg-white border-2 border-amber-300 rounded-2xl p-6 space-y-4 shadow-sm">
        <div className="flex items-center justify-between border-b border-amber-100 pb-3">
          <div className="flex items-center gap-2">
            <Clock className="w-5 h-5 text-amber-600" />
            <h2 className="text-lg font-black text-slate-900">
              Pending Orders Requiring Action ({pendingOrdersCount || pendingList.length})
            </h2>
          </div>
          <button
            onClick={() => navigate('/orders')}
            className="text-xs text-emerald-700 font-bold hover:underline flex items-center gap-1"
          >
            View All Orders <ArrowRight className="w-3.5 h-3.5" />
          </button>
        </div>

        {pendingList.length === 0 ? (
          <div className="py-6 text-center text-slate-400 text-xs italic">
            ✓ No pending orders requiring stock reservation.
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {pendingList.map((order) => (
              <div key={order.id} className="p-4 rounded-xl bg-amber-50/60 border border-amber-200 space-y-3 flex flex-col justify-between">
                <div className="flex justify-between items-start">
                  <div>
                    <div className="font-mono font-black text-slate-900 text-sm flex items-center gap-2">
                      {order.order_number}
                      <span className="bg-emerald-100 text-emerald-900 text-[10px] px-2 py-0.5 rounded-full font-extrabold uppercase">
                        Customer Order #{order.customer_order_number || 1}
                      </span>
                    </div>
                    <div className="font-bold text-xs text-slate-800 mt-1">
                      {order.customer_name_snapshot || order.customer_name || 'Customer'}
                    </div>
                    <div className="text-[11px] text-slate-600 mt-0.5">
                      🏠 {order.customer_villa ? `Villa ${order.customer_villa}, ` : ''}{order.customer_address || order.delivery_address}
                    </div>
                  </div>

                  <div className="text-right">
                    <div className="font-mono font-black text-emerald-700 text-base">
                      AED {parseFloat(order.total_amount || order.total || 0).toFixed(2)}
                    </div>
                    <div className="text-[10px] text-slate-500 uppercase font-bold">Cash on Delivery</div>
                  </div>
                </div>

                <div className="pt-2 border-t border-amber-200/80 flex items-center justify-between">
                  <span className="text-[10px] text-amber-900 bg-amber-200/80 px-2 py-0.5 rounded font-extrabold uppercase">
                    Status: {order.status}
                  </span>
                  <button
                    onClick={() => handleAcceptAndReserve(order.id)}
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-xs transition-colors flex items-center gap-1.5"
                  >
                    <Check className="w-4 h-4" /> Accept & Reserve Stock
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Low Stock Alerts & Recent Orders */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Orders Table */}
        <div className="p-6 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-extrabold text-slate-900 text-base">Recent Business Orders</h3>
            <span className="text-xs text-slate-500 font-semibold">{data?.recent_orders?.length || 0} items</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-700">
              <thead className="bg-slate-50 text-slate-900 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                <tr>
                  <th className="px-3 py-3">Global ID</th>
                  <th className="px-3 py-3">Customer Order</th>
                  <th className="px-3 py-3">Customer</th>
                  <th className="px-3 py-3 text-right">Total</th>
                  <th className="px-3 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data?.recent_orders?.map((order) => (
                  <tr key={order.id} className="hover:bg-slate-50">
                    <td className="px-3 py-3 font-mono font-bold text-slate-900">{order.order_number}</td>
                    <td className="px-3 py-3 font-mono font-bold text-emerald-700">#{order.customer_order_number || 1}</td>
                    <td className="px-3 py-3 text-slate-800 font-semibold">{order.customer_name}</td>
                    <td className="px-3 py-3 text-right font-mono font-bold text-emerald-700">AED {parseFloat(order.total || 0).toFixed(2)}</td>
                    <td className="px-3 py-3">
                      <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase ${
                        order.status === 'delivered' ? 'bg-emerald-100 text-emerald-800' :
                        order.status === 'pending' ? 'bg-amber-100 text-amber-900' :
                        'bg-slate-100 text-slate-800'
                      }`}>
                        {order.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Low Stock Alerts */}
        <div className="p-6 rounded-2xl bg-white border border-slate-200/80 shadow-sm space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-extrabold text-slate-900 text-base">Low Stock Alerts</h3>
            <span className="text-xs text-rose-600 font-bold">{data?.low_stock_products?.length || 0} items low</span>
          </div>

          <div className="space-y-3 overflow-y-auto max-h-[350px] pr-1">
            {data?.low_stock_products?.map((item) => (
              <div key={item.id} className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <img src={item.image || 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=100'} alt={item.name} className="w-10 h-10 rounded-lg object-cover bg-slate-200" />
                  <div>
                    <h4 className="text-xs font-bold text-slate-900">{item.name}</h4>
                    <p className="text-[10px] text-slate-500">{item.category?.name || 'General Grocery'}</p>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-xs font-bold px-2.5 py-1 rounded-lg bg-rose-100 text-rose-800 border border-rose-200">
                    {item.stock_quantity} in stock
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

    </div>
  );
}
