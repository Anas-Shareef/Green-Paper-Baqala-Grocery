import React, { useEffect, useState } from 'react';
import { Warehouse, Plus, RefreshCw, AlertTriangle, ArrowUpRight, ArrowDownRight } from 'lucide-react';
import { adminApi } from '../services/api';

export function InventoryPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [adjustModalOpen, setAdjustModalOpen] = useState(false);
  const [form, setForm] = useState({ product_id: '', type: 'purchase', quantity: '10', reason: 'Stock Replenishment' });

  const fetchInventory = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getInventory();
      if (res.success) setData(res.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInventory();
  }, []);

  const handleAdjustSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await adminApi.adjustStock(form);
      if (res.success) {
        setAdjustModalOpen(false);
        fetchInventory();
      }
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Inventory & Stock Movements</h1>
          <p className="text-sm text-slate-400">Track real-time stock levels, stock audits, and manual inventory adjustments</p>
        </div>
        <button
          onClick={() => {
            setForm({ product_id: data?.products[0]?.id || '', type: 'purchase', quantity: '10', reason: 'Stock Replenishment' });
            setAdjustModalOpen(true);
          }}
          className="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl flex items-center gap-2 text-sm shadow-lg shadow-emerald-500/20"
        >
          <Plus className="w-4 h-4" /> Stock Adjustment
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Products Stock List */}
        <div className="lg:col-span-2 p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
          <h3 className="font-semibold text-white">Current Product Stock</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm text-slate-300">
              <thead className="text-xs uppercase bg-slate-950 text-slate-400 border-b border-slate-800">
                <tr>
                  <th className="px-3 py-2.5">Product</th>
                  <th className="px-3 py-2.5">Category</th>
                  <th className="px-3 py-2.5">Stock Quantity</th>
                  <th className="px-3 py-2.5">Min Threshold</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {data?.products?.map((p) => (
                  <tr key={p.id} className="hover:bg-slate-800/30">
                    <td className="px-3 py-3 font-medium text-white">{p.name}</td>
                    <td className="px-3 py-3 text-slate-400">{p.category?.name || 'General'}</td>
                    <td className="px-3 py-3">
                      <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                        p.stock_quantity <= 0 ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                        p.stock_quantity <= (p.minimum_stock_level || 5) ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                        'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                      }`}>
                        {p.stock_quantity} units
                      </span>
                    </td>
                    <td className="px-3 py-3 text-slate-400">{p.minimum_stock_level || 5} units</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Stock Movement Audit Log */}
        <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
          <h3 className="font-semibold text-white">Stock Audit Movement Log</h3>
          <div className="space-y-3 max-h-[500px] overflow-y-auto pr-1">
            {data?.recent_movements?.map((m) => (
              <div key={m.id} className="p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 space-y-1">
                <div className="flex items-center justify-between text-xs font-semibold">
                  <span className="text-white">{m.product?.name || 'Product'}</span>
                  <span className={m.quantity > 0 ? 'text-emerald-400' : 'text-rose-400'}>
                    {m.quantity > 0 ? `+${m.quantity}` : m.quantity}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs text-slate-400">
                  <span className="capitalize">{m.type} ({m.reason})</span>
                  <span>{m.previous_quantity} → {m.new_quantity}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {adjustModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-md space-y-4">
            <h3 className="font-bold text-lg text-white">Manual Stock Adjustment</h3>
            <form onSubmit={handleAdjustSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Select Product</label>
                <select value={form.product_id} onChange={e => setForm({...form, product_id: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm">
                  {data?.products?.map(p => (
                    <option key={p.id} value={p.id}>{p.name} (Current: {p.stock_quantity})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Type</label>
                  <select value={form.type} onChange={e => setForm({...form, type: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm">
                    <option value="purchase">Purchase (+)</option>
                    <option value="return">Return (+)</option>
                    <option value="adjustment">Adjustment (+/-)</option>
                    <option value="damage">Damage (-)</option>
                    <option value="sale">Sale (-)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Quantity Offset</label>
                  <input type="number" required value={form.quantity} onChange={e => setForm({...form, quantity: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Reason / Notes</label>
                <input type="text" value={form.reason} onChange={e => setForm({...form, reason: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
              </div>

              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => setAdjustModalOpen(false)} className="px-4 py-2 bg-slate-800 text-slate-300 font-semibold rounded-xl text-sm">Cancel</button>
                <button type="submit" className="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl text-sm">Apply Adjustment</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
