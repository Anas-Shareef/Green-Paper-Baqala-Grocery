import React, { useState, useEffect } from 'react';
import { User, MapPin, Plus, CheckCircle2, MessageCircle, Copy, Check, Sparkles } from 'lucide-react';
import { api } from '../services/api';

export const CheckoutPage = ({ cart, customer, onOrderSuccess, onBackToCart }) => {
  const [phone, setPhone] = useState(customer?.phone || localStorage.getItem('baqqala_customer_phone') || '971501112233');
  const [name, setName] = useState(customer?.name || localStorage.getItem('baqqala_customer_name') || '');
  const [addresses, setAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [isNewAddressFormOpen, setIsNewAddressFormOpen] = useState(false);

  // New address form fields
  const [villaNumber, setVillaNumber] = useState('Villa 12');
  const [address, setAddress] = useState('Street 4, Zone A');
  const [zone, setZone] = useState('Zone A');
  const [addressLabel, setAddressLabel] = useState('Home');
  const [notes, setNotes] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('Cash');

  const [identifying, setIdentifying] = useState(false);
  const [customerRecognized, setCustomerRecognized] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [orderCreatedData, setOrderCreatedData] = useState(null);
  const [copied, setCopied] = useState(false);

  const subtotal = cart.reduce((acc, item) => acc + (item.product.retail_price * item.quantity), 0);

  // Identify customer automatically when phone number changes or component mounts
  const handleIdentify = async (phoneToIdentify) => {
    const targetPhone = phoneToIdentify || phone;
    if (!targetPhone || targetPhone.length < 7) return;

    setIdentifying(true);
    setError(null);
    try {
      const res = await api.identifyCustomer(targetPhone);
      if (res && res.customer_exists) {
        setCustomerRecognized(true);
        if (res.customer?.name) {
          setName(res.customer.name);
          localStorage.setItem('baqqala_customer_name', res.customer.name);
        }
        if (res.addresses && res.addresses.length > 0) {
          setAddresses(res.addresses);
          const defaultAddr = res.addresses.find(a => a.is_default) || res.addresses[0];
          setSelectedAddressId(defaultAddr.id);
          setVillaNumber(defaultAddr.villa_number || 'Villa 12');
          setAddress(defaultAddr.street_address || 'Street 4, Zone A');
        } else {
          setIsNewAddressFormOpen(true);
        }
      } else {
        setCustomerRecognized(false);
        setAddresses([]);
        setIsNewAddressFormOpen(true);
      }
      localStorage.setItem('baqqala_customer_phone', targetPhone);
    } catch (err) {
      console.error(err);
    } finally {
      setIdentifying(false);
    }
  };

  useEffect(() => {
    if (phone) {
      handleIdentify(phone);
    }
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (cart.length === 0) {
      setError('Your cart is empty. Please add grocery items first.');
      return;
    }

    setLoading(true);
    setError(null);

    const idempotencyKey = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `chk-${Date.now()}-${Math.random()}`;

    const payload = {
      name: name || 'Valued Customer',
      phone,
      address_id: selectedAddressId && !isNewAddressFormOpen ? selectedAddressId : null,
      address_label: addressLabel,
      villa_number: villaNumber,
      delivery_address: address,
      zone,
      notes,
      payment_method: paymentMethod,
      idempotency_key: idempotencyKey,
      items: cart.map(i => ({
        product_id: i.product.id,
        quantity: i.quantity
      }))
    };

    try {
      const res = await api.submitOrder(payload);
      if (res && (res.order || res.order_number)) {
        const orderData = res.order || res;
        const waUrl = res.whatsapp_url || orderData.whatsapp_url;
        const msgBody = res.message_body || orderData.message_body;

        setOrderCreatedData({
          order: orderData,
          whatsapp_url: waUrl,
          message_body: msgBody,
        });

        // Trigger automatic WhatsApp open after brief success feedback
        if (waUrl) {
          setTimeout(() => {
            window.open(waUrl, '_blank', 'noopener,noreferrer');
          }, 600);
        }

        if (onOrderSuccess) {
          onOrderSuccess(orderData);
        }
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit order. Please check MOV & stock availability.');
    } finally {
      setLoading(false);
    }
  };

  const handleCopyMessage = () => {
    if (orderCreatedData?.message_body) {
      navigator.clipboard.writeText(orderCreatedData.message_body);
      setCopied(true);
      setTimeout(() => setCopied(false), 3000);
    }
  };

  if (orderCreatedData) {
    return (
      <div className="max-w-xl mx-auto space-y-6 pb-8 text-slate-900">
        <div className="bg-white border border-emerald-200 rounded-3xl p-6 shadow-xl text-center space-y-4">
          <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto shadow-sm">
            <CheckCircle2 className="w-10 h-10" />
          </div>

          <div className="space-y-1">
            <span className="text-xs font-extrabold text-emerald-800 uppercase tracking-wider bg-emerald-100 px-3 py-1 rounded-full">
              ORDER SAVED IN ADMIN
            </span>
            <h2 className="text-2xl font-black text-slate-900 font-mono mt-2">
              {orderCreatedData.order.order_number || 'ORD-SUCCESS'}
            </h2>
            <p className="text-xs text-slate-600">
              Your grocery order has been created. Total Due: <strong className="text-emerald-700 font-mono text-base">₹{subtotal.toFixed(2)}</strong> (COD)
            </p>
          </div>

          <div className="p-4 bg-emerald-50 rounded-2xl border border-emerald-100 space-y-3 text-xs text-left">
            <div className="flex items-center justify-between font-bold text-emerald-950">
              <span className="flex items-center gap-1.5">
                <MessageCircle className="w-4 h-4 text-emerald-700" />
                WhatsApp Order Confirmation
              </span>
              <span className="text-[10px] text-emerald-800 bg-white px-2 py-0.5 rounded-full border border-emerald-200">
                Action Required
              </span>
            </div>
            <p className="text-slate-700 text-[11px] leading-relaxed">
              We opened WhatsApp automatically with your pre-filled order message. Please tap <strong>"Send"</strong> in WhatsApp so our villa delivery team receives your confirmation.
            </p>

            <div className="pt-2 flex flex-col sm:flex-row gap-2">
              <a
                href={orderCreatedData.whatsapp_url}
                target="_blank"
                rel="noreferrer"
                className="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-md flex items-center justify-center gap-2 transition-all"
              >
                <MessageCircle className="w-4 h-4" />
                Open WhatsApp Again
              </a>

              <button
                onClick={handleCopyMessage}
                className="px-4 py-3 bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition-colors shrink-0"
              >
                {copied ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4 text-slate-500" />}
                {copied ? 'Copied!' : 'Copy Details'}
              </button>
            </div>
          </div>

        </div>
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto space-y-6 pb-6 text-slate-900">
      
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-black text-slate-900">Villa Delivery Checkout</h2>
          <p className="text-xs text-emerald-700 font-semibold mt-0.5">Instant Phone Recognition + WhatsApp Delivery</p>
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
        
        {/* STEP 1: MOBILE PHONE RECOGNITION */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-3 shadow-sm">
          <div className="flex justify-between items-center">
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
              <User className="w-4 h-4 text-emerald-600" />
              1. Mobile Number (Guest Recognition)
            </h3>
            {identifying && <span className="text-[10px] text-emerald-700 font-semibold animate-pulse">Recognizing...</span>}
          </div>

          <div className="flex gap-2">
            <input
              type="text"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              onBlur={() => handleIdentify(phone)}
              required
              placeholder="+971 50 XXX XXXX"
              className="flex-1 bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
            />
            <button
              type="button"
              onClick={() => handleIdentify(phone)}
              className="px-4 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 font-bold rounded-xl text-xs transition-colors shrink-0"
            >
              Recognize
            </button>
          </div>

          {customerRecognized ? (
            <div className="bg-emerald-50 border border-emerald-200 p-3 rounded-xl flex items-center justify-between text-emerald-950">
              <div className="flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-emerald-600" />
                <span>Welcome back, <strong className="text-slate-900">{name || 'Customer'}</strong>! Saved delivery details found.</span>
              </div>
            </div>
          ) : (
            <p className="text-[11px] text-slate-500">
              No password needed. Enter your mobile number to load saved addresses or place instant guest orders.
            </p>
          )}
        </div>

        {/* STEP 2: DELIVERY ADDRESS SELECTION / ADDITION */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
          <div className="flex justify-between items-center">
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
              <MapPin className="w-4 h-4 text-emerald-600" />
              2. Delivery Address
            </h3>
            {addresses.length > 0 && (
              <button
                type="button"
                onClick={() => setIsNewAddressFormOpen(!isNewAddressFormOpen)}
                className="text-xs text-emerald-700 hover:underline font-bold flex items-center gap-1"
              >
                <Plus className="w-3.5 h-3.5" />
                {isNewAddressFormOpen ? 'Use Saved Address' : 'Add New Address'}
              </button>
            )}
          </div>

          {/* Saved Addresses Chips/Cards */}
          {addresses.length > 0 && !isNewAddressFormOpen && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {addresses.map((addr) => {
                const isSelected = selectedAddressId === addr.id;
                return (
                  <div
                    key={addr.id}
                    onClick={() => {
                      setSelectedAddressId(addr.id);
                      setVillaNumber(addr.villa_number || 'Villa 12');
                      setAddress(addr.street_address || 'Street 4, Zone A');
                    }}
                    className={`p-3.5 rounded-2xl border transition-all cursor-pointer space-y-1 ${
                      isSelected
                        ? 'bg-emerald-50 border-emerald-600 text-emerald-950 shadow-xs'
                        : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-emerald-300'
                    }`}
                  >
                    <div className="flex justify-between items-center">
                      <span className="font-extrabold text-xs flex items-center gap-1.5">
                        {addr.label || 'Home'}
                        {addr.is_default && <span className="bg-emerald-200 text-emerald-900 text-[9px] px-1.5 py-0.2 rounded font-bold">Default</span>}
                      </span>
                      {isSelected && <CheckCircle2 className="w-4 h-4 text-emerald-600" />}
                    </div>
                    <div className="font-bold text-slate-900">{addr.villa_number ? `Villa ${addr.villa_number}` : ''}</div>
                    <div className="text-[11px] text-slate-600 line-clamp-2">{addr.street_address} {addr.zone ? `(${addr.zone})` : ''}</div>
                  </div>
                );
              })}
            </div>
          )}

          {/* New / Edit Address Form */}
          {(addresses.length === 0 || isNewAddressFormOpen) && (
            <div className="space-y-3 pt-2">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Customer Name *</label>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  placeholder="e.g. Muhammed Al Nuaimi"
                  className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Villa / Building Number *</label>
                  <input
                    type="text"
                    value={villaNumber}
                    onChange={(e) => setVillaNumber(e.target.value)}
                    required
                    placeholder="Villa 12"
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Address Label</label>
                  <select
                    value={addressLabel}
                    onChange={(e) => setAddressLabel(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                  >
                    <option value="Home">Home</option>
                    <option value="Work">Work</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Street Address / Landmark *</label>
                <input
                  type="text"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  required
                  placeholder="Street 4, Zone A"
                  className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Gate / Delivery Notes</label>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="e.g. Leave package at Villa front gate..."
                  className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl p-3 outline-none focus:border-emerald-600 focus:bg-white transition-colors h-16"
                ></textarea>
              </div>
            </div>
          )}
        </div>

        {/* STEP 3: PAYMENT METHOD */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-3 shadow-sm">
          <h3 className="font-bold text-slate-900 text-sm">3. Payment Method</h3>
          <div className="grid grid-cols-2 gap-3">
            <label className={`p-3 rounded-xl border flex items-center gap-2 cursor-pointer font-bold ${paymentMethod === 'Cash' ? 'bg-emerald-50 border-emerald-600 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-700'}`}>
              <input
                type="radio"
                name="pm"
                value="Cash"
                checked={paymentMethod === 'Cash'}
                onChange={() => setPaymentMethod('Cash')}
                className="accent-emerald-600"
              />
              Cash on Delivery (COD)
            </label>
            <label className={`p-3 rounded-xl border flex items-center gap-2 cursor-pointer font-bold ${paymentMethod === 'Card' ? 'bg-emerald-50 border-emerald-600 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-700'}`}>
              <input
                type="radio"
                name="pm"
                value="Card"
                checked={paymentMethod === 'Card'}
                onChange={() => setPaymentMethod('Card')}
                className="accent-emerald-600"
              />
              Card on Delivery
            </label>
          </div>
        </div>

        {/* STEP 4: ORDER ITEMS SUMMARY */}
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

        {/* PRIMARY ACTION BUTTON */}
        <button
          type="submit"
          disabled={loading}
          className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2"
        >
          <MessageCircle className="w-5 h-5" />
          <span>{loading ? 'Creating Order...' : 'SEND ORDER ON WHATSAPP'}</span>
        </button>

      </form>
    </div>
  );
};
