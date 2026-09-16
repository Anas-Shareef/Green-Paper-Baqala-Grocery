import React, { useState, useEffect } from 'react';
import { FileText, RefreshCw } from 'lucide-react';
import { api } from '../services/api';

export const TrackingPage = ({ currentOrder }) => {
  const [orderNumber, setOrderNumber] = useState(currentOrder?.order_number || 'ORD-000101');
  const [trackingData, setTrackingData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchTracking = async (number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.getOrderTracking(number || orderNumber);
      setTrackingData(res);
    } catch (err) {
      setError('Order not found. Please verify order number.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (orderNumber) {
      fetchTracking(orderNumber);
    }
  }, []);

  const order = trackingData?.order;
  const timeline = trackingData?.timeline || [];

  return (
    <div className="max-w-xl mx-auto space-y-6 pb-6">
      
      <div className="flex items-center justify-between border-b border-slate-800 pb-4">
        <div>
          <h2 className="text-xl font-black text-white">Live Order Tracking</h2>
          <p className="text-xs text-emerald-400 font-semibold mt-0.5">Baqqala Delivery Status</p>
        </div>
        
        <button onClick={() => fetchTracking(orderNumber)} className="p-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl flex items-center gap-1 text-xs font-bold">
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          Refresh
        </button>
      </div>

      {/* Order Search Bar */}
      <form onSubmit={(e) => { e.preventDefault(); fetchTracking(orderNumber); }} className="flex gap-2">
        <input
          type="text"
          value={orderNumber}
          onChange={(e) => setOrderNumber(e.target.value)}
          placeholder="Enter Order Number e.g. ORD-000101"
          className="flex-1 bg-slate-900 border border-slate-800 text-white font-mono font-bold text-xs rounded-xl px-4 py-3 outline-none focus:border-emerald-500"
        />
        <button type="submit" className="px-5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase rounded-xl shadow-md">
          Track
        </button>
      </form>

      {error && (
        <div className="p-4 bg-rose-950/80 border border-rose-500/50 text-rose-300 rounded-2xl text-xs font-semibold">
          {error}
        </div>
      )}

      {order && (
        <div className="space-y-4">
          
          {/* Status Banner Header */}
          <div className="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-3">
            <div className="flex justify-between items-start">
              <div>
                <span className="text-[10px] uppercase tracking-wider font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2.5 py-1 rounded-full">
                  Status: {order.status.replace('_', ' ').toUpperCase()}
                </span>
                <h3 className="text-2xl font-black text-white font-mono mt-2">{order.order_number}</h3>
                <p className="text-xs text-slate-400 mt-0.5">Villa: <strong className="text-white">{order.customer_villa || 'Villa 12'}</strong></p>
              </div>

              <div className="text-right">
                <div className="text-xl font-black text-emerald-400 font-mono">₹{parseFloat(order.total_amount).toFixed(2)}</div>
                <div className="text-[10px] text-slate-400 uppercase font-bold mt-1">{order.payment_method} ({order.payment_status})</div>
              </div>
            </div>

            {/* PDF Invoice Download Link */}
            <div className="pt-3 border-t border-slate-800 flex justify-between items-center text-xs">
              <span className="text-slate-400">Customer PDF Invoice:</span>
              <a
                href={`${import.meta.env.VITE_BACKEND_URL || (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? 'http://localhost:8000' : 'https://baqqala-admin.vercel.app')}/invoice/${order.order_number}`}
                target="_blank"
                rel="noreferrer"
                className="px-3.5 py-2 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 rounded-xl font-bold flex items-center gap-1.5 transition-all"
              >
                <FileText className="w-4 h-4" />
                Download PDF Receipt
              </a>
            </div>
          </div>

          {/* Vertical Timeline Stepper */}
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
            <h4 className="font-extrabold text-white text-sm">Delivery Timeline Progress</h4>

            <div className="space-y-4 relative pl-6 border-l-2 border-slate-800">
              {timeline.map((t, idx) => (
                <div key={idx} className="relative">
                  <div className={`absolute -left-[31px] top-0 w-6 h-6 rounded-full border-2 flex items-center justify-center font-bold text-xs ${
                    t.active ? 'bg-emerald-500 border-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/30' : 'bg-slate-950 border-slate-800 text-slate-600'
                  }`}>
                    {t.active ? '✓' : idx + 1}
                  </div>

                  <div>
                    <div className={`text-xs font-bold ${t.active ? 'text-white' : 'text-slate-500'}`}>{t.label}</div>
                    <div className="text-[10px] text-slate-400">{t.time ? new Date(t.time).toLocaleString() : 'Pending stage'}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

        </div>
      )}

    </div>
  );
};
