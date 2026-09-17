import React, { useEffect, useState } from 'react';
import { DollarSign, ShoppingBag, Clock, CheckCircle2, AlertTriangle, TrendingDown, RefreshCw } from 'lucide-react';
import { adminApi } from '../services/api';

export function DashboardPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchDashboard = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getDashboard();
      if (res.success) {
        setData(res.data);
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

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="flex items-center gap-3 text-emerald-400">
          <RefreshCw className="w-6 h-6 animate-spin" />
          <span className="font-medium text-sm">Loading dashboard analytics...</span>
        </div>
      </div>
    );
  }

  const metrics = data?.metrics || {};

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Overview Dashboard</h1>
          <p className="text-sm text-slate-400">Real-time metrics, low stock alerts, and recent customer orders</p>
        </div>
        <button
          onClick={fetchDashboard}
          className="p-2.5 bg-slate-900 border border-slate-800 rounded-xl text-slate-400 hover:text-white transition-colors"
        >
          <RefreshCw className="w-4 h-4" />
        </button>
      </div>

      {/* Metric Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Today's Sales</span>
            <div className="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
              <DollarSign className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">${metrics.today_sales?.toFixed(2) || '0.00'}</div>
          <p className="text-xs text-slate-500">{metrics.today_orders || 0} orders placed today</p>
        </div>

        <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Pending Orders</span>
            <div className="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">{metrics.pending_orders || 0}</div>
          <p className="text-xs text-slate-500">Requires fulfillment</p>
        </div>

        <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Delivered</span>
            <div className="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">{metrics.delivered_orders || 0}</div>
          <p className="text-xs text-slate-500">Successfully completed</p>
        </div>

        <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Low Stock Items</span>
            <div className="w-9 h-9 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
              <AlertTriangle className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">{metrics.low_stock_count || 0}</div>
          <p className="text-xs text-slate-500">{metrics.out_of_stock || 0} items out of stock</p>
        </div>
      </div>

      {/* Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Orders Table */}
        <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold text-white">Recent Orders</h3>
            <span className="text-xs text-slate-400">{data?.recent_orders?.length || 0} latest</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm text-slate-300">
              <thead className="text-xs uppercase bg-slate-950/50 text-slate-400 border-b border-slate-800">
                <tr>
                  <th className="px-3 py-2.5">Order</th>
                  <th className="px-3 py-2.5">Customer</th>
                  <th className="px-3 py-2.5">Total</th>
                  <th className="px-3 py-2.5">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {data?.recent_orders?.map((order) => (
                  <tr key={order.id} className="hover:bg-slate-800/30">
                    <td className="px-3 py-3 font-medium text-white">{order.order_number}</td>
                    <td className="px-3 py-3 text-slate-400">{order.customer_name}</td>
                    <td className="px-3 py-3 font-semibold text-emerald-400">${order.total?.toFixed(2)}</td>
                    <td className="px-3 py-3">
                      <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold capitalize ${
                        order.status === 'delivered' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                        order.status === 'pending' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                        'bg-slate-800 text-slate-300'
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
        <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold text-white">Low Stock Alerts</h3>
            <span className="text-xs text-rose-400 font-semibold">{data?.low_stock_products?.length || 0} items low</span>
          </div>

          <div className="space-y-3 overflow-y-auto max-h-[350px] pr-1">
            {data?.low_stock_products?.map((item) => (
              <div key={item.id} className="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <img src={item.image || 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=100'} alt={item.name} className="w-10 h-10 rounded-lg object-cover bg-slate-800" />
                  <div>
                    <h4 className="text-sm font-medium text-white">{item.name}</h4>
                    <p className="text-xs text-slate-400">{item.category?.name || 'General'}</p>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-xs font-bold px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20">
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
