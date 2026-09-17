import React, { useEffect, useState } from 'react';
import { BarChart3, TrendingUp, DollarSign, ShoppingBag, RefreshCw } from 'lucide-react';
import { adminApi } from '../services/api';

export function ReportsPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('this_month');

  const fetchReports = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getReports(period);
      if (res.success) setData(res.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, [period]);

  const summary = data?.summary || {};

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Financial & Sales Reports</h1>
          <p className="text-sm text-slate-400">Server-side PostgreSQL aggregation of revenues, expenses, and net profit</p>
        </div>
        <select
          value={period}
          onChange={(e) => setPeriod(e.target.value)}
          className="px-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-emerald-500"
        >
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="this_week">This Week</option>
          <option value="this_month">This Month</option>
        </select>
      </div>

      {loading ? (
        <div className="flex items-center justify-center min-h-[300px]">
          <RefreshCw className="w-6 h-6 animate-spin text-emerald-400" />
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Revenue</span>
              <div className="text-2xl font-bold text-emerald-400">${summary.total_revenue?.toFixed(2)}</div>
              <p className="text-xs text-slate-500">{summary.total_orders} total orders</p>
            </div>

            <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Expenses</span>
              <div className="text-2xl font-bold text-rose-400">${summary.total_expenses?.toFixed(2)}</div>
              <p className="text-xs text-slate-500">Recorded operating costs</p>
            </div>

            <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Estimated COGS</span>
              <div className="text-2xl font-bold text-amber-400">${summary.estimated_cogs?.toFixed(2)}</div>
              <p className="text-xs text-slate-500">Cost of goods sold</p>
            </div>

            <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Net Profit</span>
              <div className={`text-2xl font-bold ${summary.net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                ${summary.net_profit?.toFixed(2)}
              </div>
              <p className="text-xs text-slate-500">Final profit figure</p>
            </div>
          </div>

          <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
            <h3 className="font-semibold text-white">Top Selling Products in Period</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-slate-300">
                <thead className="text-xs uppercase bg-slate-950 text-slate-400 border-b border-slate-800">
                  <tr>
                    <th className="px-4 py-3">Product Name</th>
                    <th className="px-4 py-3">Units Sold</th>
                    <th className="px-4 py-3">Total Sales Revenue</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {data?.top_products?.map((item, idx) => (
                    <tr key={idx} className="hover:bg-slate-800/30">
                      <td className="px-4 py-3 font-semibold text-white">{item.product_name}</td>
                      <td className="px-4 py-3 text-slate-300">{item.total_qty} units</td>
                      <td className="px-4 py-3 font-bold text-emerald-400">${parseFloat(item.total_sales).toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
