import React, { useEffect, useState } from 'react';
import { Plus, Edit2, Trash2, X, Tags } from 'lucide-react';
import { adminApi } from '../services/api';

export function CategoriesPage() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({ name: '', description: '', sort_order: '0', status: 'active', image_file: null });

  const fetchCategories = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getCategories();
      if (res.success) setCategories(res.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCategories();
  }, []);

  const handleOpenModal = (cat = null) => {
    if (cat) {
      setEditingId(cat.id);
      setForm({ name: cat.name, description: cat.description || '', sort_order: cat.sort_order || 0, status: cat.status || 'active', image_file: null });
    } else {
      setEditingId(null);
      setForm({ name: '', description: '', sort_order: '0', status: 'active', image_file: null });
    }
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData();
      Object.keys(form).forEach(k => { if (form[k] !== null) formData.append(k, form[k]); });
      const res = await adminApi.saveCategory(formData, editingId);
      if (res.success) {
        setModalOpen(false);
        fetchCategories();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this category?')) return;
    try {
      await adminApi.deleteCategory(id);
      fetchCategories();
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Categories</h1>
          <p className="text-sm text-slate-400">Organize products into customer-facing grocery categories</p>
        </div>
        <button onClick={() => handleOpenModal()} className="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl flex items-center gap-2 text-sm shadow-lg shadow-emerald-500/20">
          <Plus className="w-4 h-4" /> Add Category
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {categories.map((c) => (
          <div key={c.id} className="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3 flex flex-col justify-between">
            <div className="flex items-start justify-between gap-3">
              <img src={c.image || 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=100'} alt={c.name} className="w-12 h-12 rounded-xl object-cover border border-slate-800 bg-slate-950" />
              <div className="flex gap-1">
                <button onClick={() => handleOpenModal(c)} className="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg"><Edit2 className="w-3.5 h-3.5" /></button>
                <button onClick={() => handleDelete(c.id)} className="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg"><Trash2 className="w-3.5 h-3.5" /></button>
              </div>
            </div>
            <div>
              <h3 className="font-semibold text-white text-base">{c.name}</h3>
              <p className="text-xs text-slate-400">{c.products_count || 0} Products</p>
            </div>
          </div>
        ))}
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-md space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <h3 className="font-bold text-lg text-white">{editingId ? 'Edit Category' : 'Add Category'}</h3>
              <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Category Name</label>
                <input type="text" required value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm" />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Upload Icon (Supabase Storage)</label>
                <input type="file" accept="image/*" onChange={e => setForm({...form, image_file: e.target.files[0]})} className="w-full text-xs text-slate-400" />
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => setModalOpen(false)} className="px-4 py-2 bg-slate-800 text-slate-300 font-semibold rounded-xl text-sm">Cancel</button>
                <button type="submit" className="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl text-sm">Save</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
