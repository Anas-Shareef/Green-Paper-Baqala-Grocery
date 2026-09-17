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
      
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-black text-slate-900">Live Order Tracking</h2>
          <p className="text-xs text-emerald-700 font-semibold mt-0.5">Baqqala Delivery Status</p>
        </div>
        
        <button onClick={() => fetchTracking(orderNumber)} className="p-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl flex items-center gap-1 text-xs font-bold transition-colors">
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
          className="flex-1 bg-white border border-slate-300 text-slate-900 font-mono font-bold text-xs rounded-xl px-4 py-3 outline-none focus:border-emerald-600 focus:bg-white transition-colors shadow-xs"
        />
        <button type="submit" className="px-5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-md transition-all">
          Track
        </button>
      </form>

      {error && (
        <div className="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs font-semibold">
          {error}
        </div>
      )}

      {order && (
        <div className="space-y-4">
          
          {/* Status Banner Header */}
          <div className="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
            <div className="flex justify-between items-start">
              <div>
                <span className="text-[10px] uppercase tracking-wider font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-1 rounded-full">
                  Status: {order.status.replace('_', ' ').toUpperCase()}
                </span>
                <h3 className="text-2xl font-black text-slate-900 font-mono mt-2">{order.order_number}</h3>
                <p className="text-xs text-slate-500 mt-0.5">Villa: <strong className="text-slate-900">{order.customer_villa || 'Villa 12'}</strong></p>
              </div>

              <div className="text-right">
                <div className="text-xl font-black text-emerald-700 font-mono">₹{parseFloat(order.total_amount).toFixed(2)}</div>
                <div className="text-[10px] text-slate-500 uppercase font-bold mt-1">{order.payment_method} ({order.payment_status})</div>
              </div>
            </div>

            {/* PDF Invoice Download Link */}
            <div className="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
              <span className="text-slate-500">Customer PDF Invoice:</span>
              <a
                href={`${import.meta.env.VITE_BACKEND_URL || (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? 'http://localhost:8000' : '')}/api/invoice/${order.order_number}`}
                target="_blank"
                rel="noreferrer"
                className="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl font-bold flex items-center gap-1.5 transition-all"
              >
                <FileText className="w-4 h-4 text-emerald-600" />
                Download PDF Receipt
              </a>
            </div>
          </div>

          {/* Vertical Timeline Stepper */}
          <div className="bg-white border border-slate-200/80 rounded-3xl p-6 space-y-4 shadow-sm">
            <h4 className="font-extrabold text-slate-900 text-sm">Delivery Timeline Progress</h4>

            <div className="space-y-4 relative pl-6 border-l-2 border-slate-200">
              {timeline.map((t, idx) => (
                <div key={idx} className="relative">
                  <div className={`absolute -left-[31px] top-0 w-6 h-6 rounded-full border-2 flex items-center justify-center font-bold text-xs ${
                    t.active ? 'bg-emerald-600 border-emerald-500 text-white shadow-md' : 'bg-slate-100 border-slate-300 text-slate-400'
                  }`}>
                    {t.active ? '✓' : idx + 1}
                  </div>

                  <div>
                    <div className={`text-xs font-bold ${t.active ? 'text-slate-900' : 'text-slate-400'}`}>{t.label}</div>
                    <div className="text-[10px] text-slate-500">{t.time ? new Date(t.time).toLocaleString() : 'Pending stage'}</div>
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
