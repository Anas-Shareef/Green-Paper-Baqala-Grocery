import React, { useState, useEffect } from 'react';
import { User, MapPin, History } from 'lucide-react';
import { api } from '../services/api';

export const ProfilePage = ({ customer, onOpenAuth, onSelectOrderForTracking }) => {
  const [orders, setOrders] = useState([]);

  useEffect(() => {
    if (customer?.phone) {
      api.getCustomerHistory(customer.phone).then((data) => {
        setOrders(data || []);
      });
    }
  }, [customer]);

  if (!customer) {
    return (
      <div className="max-w-md mx-auto text-center p-8 bg-white border border-slate-200/80 rounded-3xl space-y-4 my-10 shadow-sm text-slate-900">
        <User className="w-16 h-16 text-emerald-600 mx-auto" />
        <h2 className="text-xl font-extrabold text-slate-900">Guest Customer Profile</h2>
        <p className="text-xs text-slate-500">Sign in with your mobile phone OTP to access saved Villa address and order history.</p>
        <button onClick={onOpenAuth} className="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20">
          Sign In with Phone OTP
        </button>
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto space-y-6 pb-6">
      
      {/* Customer Card Header */}
      <div className="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4 text-slate-900">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-2xl shadow-lg shadow-emerald-600/20">
            {customer.name ? customer.name.charAt(0) : 'C'}
          </div>
          <div>
            <h2 className="text-xl font-extrabold text-slate-900">{customer.name}</h2>
            <div className="text-xs text-emerald-700 font-mono font-bold mt-0.5">+{customer.phone}</div>
            <div className="text-xs text-slate-500 mt-1 flex items-center gap-1">
              <MapPin className="w-3.5 h-3.5 text-emerald-600" />
              {customer.villa_number || 'Villa 12'} ({customer.zone || 'Zone A'})
            </div>
          </div>
        </div>
      </div>

      {/* PWA Install Banner */}
      <div className="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl flex items-center justify-between text-xs">
        <div className="space-y-0.5">
          <div className="font-extrabold text-emerald-950">Install Baqqala App on Home Screen</div>
          <p className="text-[11px] text-emerald-800">Fast 1-tap ordering without app store downloads.</p>
        </div>
        <button onClick={() => alert("Tap browser share button and select 'Add to Home Screen'")} className="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-[11px] shrink-0 shadow-xs">
          Install PWA
        </button>
      </div>

      {/* Order History Section */}
      <div className="bg-white border border-slate-200/80 rounded-3xl p-5 space-y-4 shadow-sm text-slate-900">
        <div className="flex items-center justify-between">
          <h3 className="font-extrabold text-slate-900 text-base flex items-center gap-2">
            <History className="w-4 h-4 text-emerald-600" />
            Previous Order History ({orders.length})
          </h3>
        </div>

        <div className="space-y-3">
          {orders.map((o) => (
            <div key={o.id} className="p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs space-y-2">
              <div className="flex justify-between items-start">
                <div>
                  <div className="font-mono font-bold text-slate-900 text-sm">{o.order_number}</div>
                  <div className="text-[10px] text-slate-500">{new Date(o.created_at).toLocaleDateString()}</div>
                </div>
                <div className="text-right">
                  <div className="font-mono font-black text-emerald-700 text-sm">₹{parseFloat(o.total_amount).toFixed(2)}</div>
                  <span className="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                    {o.status}
                  </span>
                </div>
              </div>

              <div className="pt-2 border-t border-slate-200 flex justify-between items-center text-[11px]">
                <span className="text-slate-500">{o.items?.length || 0} Items ordered</span>
                <button
                  onClick={() => onSelectOrderForTracking(o)}
                  className="text-emerald-700 hover:underline font-bold"
                >
                  Track Order &rarr;
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
};
