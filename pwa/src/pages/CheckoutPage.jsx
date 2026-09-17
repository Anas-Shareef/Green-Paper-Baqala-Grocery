import React, { useState } from 'react';
import { User } from 'lucide-react';
import { api } from '../services/api';

export const CheckoutPage = ({ cart, customer, onOrderSuccess, onBackToCart }) => {
  const [name, setName] = useState(customer?.name || 'Muhammed Al Nuaimi');
  const [phone, setPhone] = useState(customer?.phone || '971501112233');
  const [villaNumber, setVillaNumber] = useState(customer?.villa_number || 'Villa 12');
  const [address, setAddress] = useState(customer?.address || 'Street 4, Villa 12, Zone A');
  const [notes, setNotes] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('Cash');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const subtotal = cart.reduce((acc, item) => acc + (item.product.retail_price * item.quantity), 0);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const payload = {
      name,
      phone,
      villa_number: villaNumber,
      address,
      notes,
      payment_method: paymentMethod,
      items: cart.map(i => ({
        product_id: i.product.id,
        quantity: i.quantity
      }))
    };

    try {
      const res = await api.submitOrder(payload);
      if (res.success) {
        onOrderSuccess(res.order);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit order. Please check MOV & stock availability.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto space-y-6 pb-6">
      
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-black text-slate-900">Villa Delivery Checkout</h2>
          <p className="text-xs text-emerald-700 font-semibold mt-0.5">Baqqala — Cash on Delivery</p>
        </div>
        <button onClick={onBackToCart} className="text-xs text-slate-500 hover:text-slate-900 font-bold">
          &larr; Back to Cart
        </button>
      </div>

      {error && (
        <div className="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs font-semibold">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4 text-xs">
        
        {/* Contact & Location Box */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
          <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
            <User className="w-4 h-4 text-emerald-600" />
            Customer & Delivery Info
          </h3>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block font-bold text-slate-700 mb-1">Full Name *</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Mobile Phone *</label>
              <input
                type="text"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block font-bold text-slate-700 mb-1">Villa Number *</label>
              <input
                type="text"
                value={villaNumber}
                onChange={(e) => setVillaNumber(e.target.value)}
                required
                placeholder="Villa 12, Zone A"
                className="w-full bg-slate-50 border border-slate-300 text-emerald-800 font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Payment Method</label>
              <select
                value={paymentMethod}
                onChange={(e) => setPaymentMethod(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              >
                <option value="Cash">Cash on Delivery (COD)</option>
                <option value="Card">Card on Delivery</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1">Street Address / Landmark</label>
            <input
              type="text"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              required
              className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1">Order / Gate Delivery Notes</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="e.g. Leave package at Villa front gate..."
              className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl p-3 outline-none focus:border-emerald-600 focus:bg-white transition-colors h-16"
            ></textarea>
          </div>
        </div>

        {/* Order Items Summary Box */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
          <h3 className="font-bold text-slate-900 text-sm">Order Items ({cart.length})</h3>

          <div className="space-y-2 divide-y divide-slate-100 max-h-48 overflow-y-auto pr-1">
            {cart.map((item) => (
              <div key={item.product.id} className="pt-2 first:pt-0 flex justify-between items-center text-xs">
                <div>
                  <div className="font-bold text-slate-900">{item.product.name}</div>
                  <div className="text-[10px] text-slate-500">Qty: {item.quantity} &times; ₹{item.product.retail_price}</div>
                </div>
                <div className="font-mono font-bold text-emerald-700">₹{(item.product.retail_price * item.quantity).toFixed(2)}</div>
              </div>
            ))}
          </div>

          <div className="pt-3 border-t border-slate-100 font-mono text-xs space-y-1">
            <div className="flex justify-between text-slate-600">
              <span>Subtotal:</span>
              <span className="font-bold text-slate-900">₹{subtotal.toFixed(2)}</span>
            </div>
            <div className="flex justify-between text-emerald-700 font-bold">
              <span>Delivery Charge:</span>
              <span>FREE</span>
            </div>
            <div className="flex justify-between items-center text-base font-black text-slate-900 pt-2 border-t border-slate-100">
              <span>TOTAL DUE ON DELIVERY:</span>
              <span className="text-xl font-black text-emerald-700">₹{subtotal.toFixed(2)}</span>
            </div>
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2"
        >
          {loading ? 'Submitting Order...' : 'Confirm & Place Delivery Order'}
        </button>

      </form>
    </div>
  );
};
