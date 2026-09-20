import React, { useEffect, useState } from 'react';
import { Plus, Receipt, Trash2, X, RefreshCw } from 'lucide-react';
import { adminApi } from '../services/api';

export function ExpensesPage() {
  const [expenses, setExpenses] = useState([]);
  const [categories, setCategories] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({
    category_id: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    description: '',
    attachment: null,
  });

  const fetchExpenses = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getExpenses();
      if (res.success) {
        setExpenses(res.data || []);
        setCategories(res.categories || []);
        setMeta(res.meta || {});
        if (res.categories?.length > 0 && !form.category_id) {
          setForm(prev => ({ ...prev, category_id: res.categories[0].id }));
        }
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchExpenses();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData();
      Object.keys(form).forEach(k => {
        if (form[k] !== null && form[k] !== '') formData.append(k, form[k]);
      });
      const res = await adminApi.saveExpense(formData);
      if (res.success) {
        setModalOpen(false);
        fetchExpenses();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this expense?')) return;
    try {
      await adminApi.deleteExpense(id);
      fetchExpenses();
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Expense Tracker</h1>
          <p className="text-sm text-slate-400">Record operating expenses, supplier payouts, and receipts</p>
        </div>
        <button
          onClick={() => setModalOpen(true)}
          className="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl flex items-center gap-2 text-sm shadow-lg shadow-emerald-500/20"
        >
          <Plus className="w-4 h-4" /> Add Expense
        </button>
      </div>

      <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Recorded Expenses</span>
          <div className="text-3xl font-bold text-rose-400">AED {meta.total_amount?.toFixed(2) || '0.00'}</div>
        </div>
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-300">
            <thead className="text-xs uppercase bg-slate-950 text-slate-400 border-b border-slate-800">
              <tr>
                <th className="px-4 py-3.5">Expense #</th>
                <th className="px-4 py-3.5">Category</th>
                <th className="px-4 py-3.5">Date</th>
                <th className="px-4 py-3.5">Payment Method</th>
                <th className="px-4 py-3.5">Amount</th>
                <th className="px-4 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan="6" className="text-center py-8 text-slate-500">
                    <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-emerald-400" />
                    Loading expenses...
                  </td>
                </tr>
              ) : expenses.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-8 text-slate-500">No expenses recorded yet.</td>
                </tr>
              ) : (
                expenses.map((e) => (
                  <tr key={e.id} className="hover:bg-slate-800/40">
                    <td className="px-4 py-3.5 font-semibold text-white">{e.expense_number}</td>
                    <td className="px-4 py-3.5 text-slate-300">{e.category?.name || 'General'}</td>
                    <td className="px-4 py-3.5 text-slate-400">{e.expense_date}</td>
                    <td className="px-4 py-3.5 uppercase text-xs font-medium text-slate-400">{e.payment_method}</td>
                    <td className="px-4 py-3.5 font-bold text-rose-400">AED {parseFloat(e.amount).toFixed(2)}</td>
                    <td className="px-4 py-3.5 text-right">
                      <button onClick={() => handleDelete(e.id)} className="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg">
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-md space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <h3 className="font-bold text-lg text-white">Record New Expense</h3>
              <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Expense Category</label>
                <select value={form.category_id} onChange={e => setForm({...form, category_id: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm">
                  {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Amount ($)</label>
                  <input type="number" step="0.01" required value={form.amount} onChange={e => setForm({...form, amount: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Expense Date</label>
                  <input type="date" required value={form.expense_date} onChange={e => setForm({...form, expense_date: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Payment Method</label>
                <select value={form.payment_method} onChange={e => setForm({...form, payment_method: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm">
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="check">Check</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Description / Notes</label>
                <input type="text" value={form.description} onChange={e => setForm({...form, description: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => setModalOpen(false)} className="px-4 py-2 bg-slate-800 text-slate-300 font-semibold rounded-xl text-sm">Cancel</button>
                <button type="submit" className="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl text-sm">Record Expense</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
