import React, { useState, useEffect } from 'react';
import { User, MapPin, CheckCircle2, MessageCircle, Copy, Check, ShieldCheck } from 'lucide-react';
import { api } from '../services/api';

export const CheckoutPage = ({ cart, customer, onOrderSuccess, onBackToCart }) => {
  // Step 1 State: Mobile Number
  const [phoneInput, setPhoneInput] = useState(
    customer?.phone || localStorage.getItem('baqqala_customer_phone') || ''
  );
  const [normalizedPhone, setNormalizedPhone] = useState('');
  const [recognitionStatus, setRecognitionStatus] = useState('idle'); // idle | recognizing | recognized | not_recognized | error
  const [recognizedCustomer, setRecognizedCustomer] = useState(null);
  const [savedAddresses, setSavedAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);

  const [isEditingAddress, setIsEditingAddress] = useState(false);

  // New Customer & Edit Address Form State
  const [newCustomerName, setNewCustomerName] = useState('');
  const [newVillaNumber, setNewVillaNumber] = useState('');
  const [newStreetAddress, setNewStreetAddress] = useState('');
  const [newZone, setNewZone] = useState('');
  const [newLandmark, setNewLandmark] = useState('');
  const [newDeliveryNotes, setNewDeliveryNotes] = useState('');

  // Sync edit address fields when recognized customer selected address changes
  useEffect(() => {
    if (recognizedCustomer) {
      setNewCustomerName(recognizedCustomer.name || '');
      const addr = savedAddresses.find(a => a.id === selectedAddressId) || savedAddresses[0];
      if (addr) {
        setNewVillaNumber(addr.villa_number || '');
        setNewStreetAddress(addr.street_address || '');
        setNewZone(addr.zone || '');
        setNewLandmark(addr.landmark || '');
      } else {
        setIsEditingAddress(true);
      }
    }
  }, [recognizedCustomer, selectedAddressId, savedAddresses]);

  // General Checkout State
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [orderCreatedData, setOrderCreatedData] = useState(null);
  const [copied, setCopied] = useState(false);
  const [activeIdempotencyKey, setActiveIdempotencyKey] = useState(() => {
    return typeof crypto !== 'undefined' && crypto.randomUUID 
      ? `chk-${crypto.randomUUID()}` 
      : `chk-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
  });

  // Authoritative client subtotal display (AED)
  const subtotal = cart.reduce((acc, item) => acc + (parseFloat(item.product.sale_price || item.product.retail_price || item.product.price || 0) * item.quantity), 0);

  // Phone normalization utility matching UAE formats
  const normalizePhoneNumber = (raw) => {
    if (!raw) return '';
    let digits = raw.replace(/[^\d+]/g, '');
    if (digits.startsWith('+')) {
      return '+' + digits.replace(/[^\d]/g, '');
    }
    digits = digits.replace(/[^\d]/g, '');
    if (digits.startsWith('05')) return '+971' + digits.substring(1);
    if (digits.startsWith('9715')) return '+' + digits;
    if (digits.length === 9 && digits.startsWith('5')) return '+971' + digits;
    return '+' + digits;
  };

  // Perform Customer Recognition via POST /api/v1/customer/recognize
  const handleRecognize = async (targetPhone) => {
    const raw = targetPhone !== undefined ? targetPhone : phoneInput;
    const phoneToTest = raw.trim();

    if (!phoneToTest || phoneToTest.replace(/[^\d]/g, '').length < 8) {
      setError('Please enter a valid UAE mobile number (e.g. 0501234567 or +971501234567).');
      return;
    }

    setRecognitionStatus('recognizing');
    setError(null);

    const norm = normalizePhoneNumber(phoneToTest);
    setNormalizedPhone(norm);

    try {
      const res = await api.recognizeCustomer(norm);
      if (res && (res.recognized || res.customer_exists)) {
        setRecognitionStatus('recognized');
        setRecognizedCustomer(res.customer);
        setSavedAddresses(res.addresses || []);

        if (res.default_address) {
          setSelectedAddressId(res.default_address.id);
        } else if (res.addresses && res.addresses.length > 0) {
          const defaultAddr = res.addresses.find(a => a.is_default) || res.addresses[0];
          setSelectedAddressId(defaultAddr.id);
        }
      } else {
        setRecognitionStatus('not_recognized');
        setRecognizedCustomer(null);
        setSavedAddresses([]);
        setSelectedAddressId(null);
      }
      localStorage.setItem('baqqala_customer_phone', norm);
    } catch (err) {
      console.error('Recognition error:', err);
      setRecognitionStatus('error');
      setError('Unable to perform customer recognition. Please try again.');
    }
  };

  // Auto-recognize on mount if phone is stored
  useEffect(() => {
    if (phoneInput && phoneInput.trim().length >= 8) {
      handleRecognize(phoneInput);
    }
  }, []);

  // Submit Order via POST /api/v1/orders
  const handleSubmitOrder = async (e) => {
    e.preventDefault();

    if (cart.length === 0) {
      setError('Your cart is empty. Please add items before checking out.');
      return;
    }

    const cleanPhoneDigits = phoneInput.replace(/[^\d]/g, '');
    if (!cleanPhoneDigits || cleanPhoneDigits.length < 8) {
      setError('Please enter a valid UAE mobile phone number (e.g. 0501234567).');
      return;
    }

    // Resolve delivery address fields
    let targetVilla = '';
    let targetStreet = '';
    let targetZone = '';
    let targetLandmark = newLandmark.trim();
    let targetNotes = newDeliveryNotes.trim();
    let targetAddressId = null;

    if (recognitionStatus === 'recognized' && !isEditingAddress && savedAddresses.length > 0) {
      const selectedAddr = savedAddresses.find(a => a.id === selectedAddressId) || savedAddresses[0];
      if (selectedAddr) {
        if (typeof selectedAddr.id === 'number' || /^\d+$/.test(String(selectedAddr.id))) {
          targetAddressId = parseInt(selectedAddr.id, 10);
        }
        targetVilla = selectedAddr.villa_number || '';
        targetStreet = selectedAddr.street_address || '';
        targetZone = selectedAddr.zone || '';
        targetLandmark = selectedAddr.landmark || '';
        targetNotes = selectedAddr.delivery_notes || '';
      }
    } else {
      targetVilla = newVillaNumber.trim();
      targetStreet = newStreetAddress.trim();
      targetZone = newZone.trim();
    }

    // Validate required address fields
    if (!targetVilla) {
      setError('Please provide your Villa Number (e.g. Villa 94).');
      return;
    }
    if (!targetStreet) {
      setError('Please provide your Street / Area (e.g. Street 11).');
      return;
    }
    if (!targetZone) {
      setError('Please provide your Zone (e.g. Zone B).');
      return;
    }

    const targetName = (recognitionStatus === 'recognized' && recognizedCustomer?.name)
      ? recognizedCustomer.name
      : (newCustomerName.trim() || 'Valued Customer');

    setLoading(true);
    setError(null);

    // Prepare Canonical Request Payload with stable idempotency key
    const normPhone = normalizedPhone || normalizePhoneNumber(phoneInput);
    const payload = {
      customer_name: targetName,
      customer_phone: normPhone,
      villa_number: targetVilla,
      street_address: targetStreet,
      delivery_address: targetStreet, // Alias for backward compatibility
      zone: targetZone,
      landmark: targetLandmark || undefined,
      notes: targetNotes || undefined,
      payment_method: 'cod', // Enforce COD strictly
      idempotency_key: activeIdempotencyKey,
      items: cart.map(i => ({
        product_id: i.product.id,
        quantity: i.quantity
      }))
    };

    if (targetAddressId) {
      payload.address_id = targetAddressId;
    }

    try {
      const res = await api.submitOrder(payload);

      // Support canonical { success: true, data: { order, whatsapp } } or flat response
      const orderData = res?.data?.order || res?.order || res;
      const orderNumber = res?.data?.order?.order_number || res?.order_number || orderData?.order_number;
      const customerOrderNumber = res?.data?.order?.customer_order_number || res?.customer_order_number || orderData?.customer_order_number;
      const waUrl = res?.data?.whatsapp?.url || res?.whatsapp?.url || res?.whatsapp_url || orderData?.whatsapp_url;
      const msgBody = res?.data?.whatsapp?.message || res?.whatsapp?.message || res?.message_body || orderData?.message_body;

      if (orderData && (orderNumber || orderData.id)) {
        setOrderCreatedData({
          order: orderData,
          order_number: orderNumber,
          customer_order_number: customerOrderNumber,
          whatsapp_url: waUrl,
          message_body: msgBody,
        });

        // Trigger WhatsApp strictly on successful order creation
        if (waUrl) {
          setTimeout(() => {
            window.open(waUrl, '_blank', 'noopener,noreferrer');
          }, 400);
        }

        if (onOrderSuccess) {
          onOrderSuccess(orderData);
        }
      } else {
        throw new Error('Invalid response received from server.');
      }
    } catch (err) {
      // Detailed error logging for development per Section 2
      console.error('Order API Error', {
        status: err.response?.status,
        data: err.response?.data,
        message: err.response?.data?.message,
        errors: err.response?.data?.errors,
      });

      // User-friendly error messages per Section 18
      if (err.response?.status === 422) {
        const validationErrors = err.response?.data?.errors;
        if (validationErrors?.items) {
          setError('Some items in your cart are no longer available in the requested quantity. Please review your cart.');
        } else if (validationErrors?.customer_phone) {
          setError('Please enter a valid UAE mobile phone number.');
        } else {
          setError(err.response?.data?.message || 'Please check your delivery details and try again.');
        }
      } else if (err.response?.status >= 500) {
        setError("We couldn't place your order right now. Please try again in a few moments.");
      } else {
        setError(err.response?.data?.message || "We couldn't place your order. Please review your cart and delivery details.");
      }
    } finally {
      setLoading(false);
    }
  };

  const handleCopyDetails = () => {
    if (orderCreatedData?.message_body) {
      navigator.clipboard.writeText(orderCreatedData.message_body);
      setCopied(true);
      setTimeout(() => setCopied(false), 3000);
    }
  };

  // SUCCESS SCREEN
  if (orderCreatedData) {
    return (
      <div className="max-w-xl mx-auto space-y-6 pb-8 text-slate-900">
        <div className="bg-white border border-emerald-200 rounded-3xl p-6 sm:p-8 shadow-xl text-center space-y-5">
          <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto shadow-sm">
            <CheckCircle2 className="w-10 h-10" />
          </div>

          <div className="space-y-1">
            <span className="text-[11px] font-black text-emerald-800 uppercase tracking-widest bg-emerald-100 px-3 py-1 rounded-full">
              ✓ ORDER CREATED IN SYSTEM
            </span>
            <h2 className="text-3xl font-black text-emerald-800 font-mono mt-3">
              Order #{orderCreatedData.order?.customer_order_number || orderCreatedData.customer_order_number || '1'}
            </h2>
            <p className="text-xs text-slate-600 font-medium pt-1">
              Opening WhatsApp with your prefilled order message...
            </p>
          </div>

          <div className="p-5 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-4 text-xs text-left">
            <div className="flex items-center justify-between font-bold text-emerald-950 border-b border-emerald-200/80 pb-3">
              <span className="flex items-center gap-2 text-sm font-black">
                <MessageCircle className="w-5 h-5 text-emerald-600" />
                WhatsApp Delivery Confirmation
              </span>
              <span className="text-[10px] text-emerald-800 bg-white px-2.5 py-0.5 rounded-full border border-emerald-300 font-extrabold uppercase">
                Action Required
              </span>
            </div>

            <p className="text-slate-700 text-xs leading-relaxed">
              Your order is recorded in the Baqqala Admin System. Please tap <strong>"Send"</strong> in WhatsApp to complete your order confirmation.
            </p>

            <div className="pt-2 flex flex-col sm:flex-row gap-3">
              <a
                href={orderCreatedData.whatsapp_url}
                target="_blank"
                rel="noreferrer"
                className="flex-1 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md flex items-center justify-center gap-2 transition-all"
              >
                <MessageCircle className="w-4 h-4" />
                Open WhatsApp
              </a>

              <button
                type="button"
                onClick={handleCopyDetails}
                className="px-4 py-3.5 bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition-colors shrink-0"
              >
                {copied ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4 text-slate-500" />}
                {copied ? 'Copied!' : 'Copy Order Details'}
              </button>
            </div>
          </div>

          <div className="text-[11px] text-slate-500 font-medium">
            Cash on Delivery • Pay the delivery person when your order arrives.
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto space-y-6 pb-8 text-slate-900">
      {/* PAGE HEADER */}
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 tracking-tight">Villa Delivery Checkout</h1>
          <p className="text-xs text-emerald-700 font-bold mt-0.5">
            Instant Phone Recognition + WhatsApp Delivery
          </p>
        </div>
        <button
          type="button"
          onClick={onBackToCart}
          className="text-xs text-slate-500 hover:text-slate-900 font-bold underline transition-colors"
        >
          ← Back to Cart
        </button>
      </div>

      {error && (
        <div className="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-semibold shadow-xs">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmitOrder} className="space-y-5 text-xs">

        {/* STEP 1: MOBILE NUMBER (GUEST RECOGNITION) */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
          <div className="flex justify-between items-center">
            <h2 className="font-extrabold text-slate-900 text-sm flex items-center gap-2">
              <User className="w-4 h-4 text-emerald-600" />
              👤 1. Mobile Number (Guest Recognition)
            </h2>
            {recognitionStatus === 'recognizing' && (
              <span className="text-[11px] text-emerald-700 font-bold animate-pulse">Recognizing...</span>
            )}
          </div>

          <div className="flex gap-2">
            <input
              type="tel"
              value={phoneInput}
              onChange={(e) => {
                setPhoneInput(e.target.value);
                if (recognitionStatus !== 'idle') setRecognitionStatus('idle');
              }}
              onBlur={() => {
                if (phoneInput && phoneInput.trim().replace(/[^\d]/g, '').length >= 8 && recognitionStatus === 'idle') {
                  handleRecognize(phoneInput);
                }
              }}
              placeholder="+971 50 XXX XXXX"
              required
              disabled={loading || recognitionStatus === 'recognizing'}
              className="flex-1 bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
            />
            <button
              type="button"
              onClick={() => handleRecognize(phoneInput)}
              disabled={loading || recognitionStatus === 'recognizing'}
              className="px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold rounded-xl text-xs transition-colors shrink-0 shadow-sm"
            >
              {recognitionStatus === 'recognizing' ? 'Checking...' : 'Recognize'}
            </button>
          </div>

          <p className="text-[11px] text-slate-500 leading-normal">
            No password needed. Enter your mobile number to load saved delivery details or place an instant guest order.
          </p>

          {/* RECOGNITION RESULT BANNER */}
          {recognitionStatus === 'recognized' && recognizedCustomer && (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 space-y-2">
              <div className="flex items-center gap-1.5 text-emerald-900 font-extrabold text-xs">
                <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                ✓ Customer Recognized
              </div>
              <div className="text-xs space-y-0.5 pl-5">
                <div className="font-bold text-slate-900">{recognizedCustomer.name}</div>
                <div className="font-mono text-slate-600 text-[11px]">{recognizedCustomer.phone}</div>
                <div className="text-[10px] text-emerald-700 font-semibold pt-1">
                  Your saved delivery details have been found.
                </div>
              </div>
            </div>
          )}

          {recognitionStatus === 'not_recognized' && (
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-3 text-[11px] text-slate-700 font-medium flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              ✓ Mobile number is available for a new guest order.
            </div>
          )}
        </div>

        {/* STEP 2: DELIVERY ADDRESS */}
        {(recognitionStatus === 'recognized' || recognitionStatus === 'not_recognized' || recognitionStatus === 'idle' || (phoneInput && phoneInput.trim().length >= 8)) && (
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
            <h2 className="font-extrabold text-slate-900 text-sm flex items-center gap-2">
              <MapPin className="w-4 h-4 text-emerald-600" />
              📍 2. Delivery Address
            </h2>

            {/* SCENARIO A: RECOGNIZED CUSTOMER */}
            {recognitionStatus === 'recognized' && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Saved Delivery Address
                  </div>
                  <button
                    type="button"
                    onClick={() => setIsEditingAddress(!isEditingAddress)}
                    className="text-xs text-emerald-700 hover:text-emerald-800 font-bold underline transition-colors"
                  >
                    {isEditingAddress ? '← Use Saved Address' : 'Edit Address'}
                  </button>
                </div>

                {!isEditingAddress ? (
                  <div className="space-y-3">
                    {savedAddresses.length > 0 ? (
                      <div className="space-y-3">
                        {savedAddresses.map((addr) => {
                          const isSelected = selectedAddressId === addr.id;
                          return (
                            <div
                              key={addr.id}
                              onClick={() => setSelectedAddressId(addr.id)}
                              className={`p-4 rounded-xl border transition-all cursor-pointer space-y-1.5 ${
                                isSelected
                                  ? 'bg-emerald-50/80 border-emerald-600 text-emerald-950 shadow-xs'
                                  : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-emerald-300'
                              }`}
                            >
                              <div className="flex justify-between items-center">
                                <span className="font-black text-xs flex items-center gap-1.5">
                                  🏠 {addr.villa_number ? `Villa ${addr.villa_number}` : 'Villa Delivery'}
                                  {addr.is_default && (
                                    <span className="bg-emerald-200 text-emerald-900 text-[9px] px-1.5 py-0.2 rounded font-extrabold uppercase">
                                      Default
                                    </span>
                                  )}
                                </span>
                                <div className="w-4 h-4 rounded-full border border-emerald-600 flex items-center justify-center">
                                  {isSelected && <div className="w-2.5 h-2.5 bg-emerald-600 rounded-full" />}
                                </div>
                              </div>
                              <div className="text-xs text-slate-800 font-medium">
                                {addr.street_address} {addr.zone && !addr.street_address.includes(addr.zone) ? `, ${addr.zone}` : ''}
                              </div>
                              {addr.landmark && (
                                <div className="text-[11px] text-slate-500">
                                  Near {addr.landmark}
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    ) : (
                      <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl text-slate-600 text-xs">
                        No saved addresses found. Click "Edit Address" to enter delivery details.
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="space-y-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                    <div className="text-xs font-bold text-slate-800 pb-1">Update Delivery Address:</div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block font-bold text-slate-700 mb-1">Villa Number *</label>
                        <input
                          type="text"
                          value={newVillaNumber}
                          onChange={(e) => setNewVillaNumber(e.target.value)}
                          required
                          placeholder="e.g. Villa 94"
                          className="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl px-3 py-2 outline-none focus:border-emerald-600"
                        />
                      </div>
                      <div>
                        <label className="block font-bold text-slate-700 mb-1">Zone *</label>
                        <input
                          type="text"
                          value={newZone}
                          onChange={(e) => setNewZone(e.target.value)}
                          required
                          placeholder="e.g. Zone B"
                          className="w-full bg-white border border-slate-300 text-slate-900 rounded-xl px-3 py-2 outline-none focus:border-emerald-600"
                        />
                      </div>
                    </div>
                    <div>
                      <label className="block font-bold text-slate-700 mb-1">Street / Area *</label>
                      <input
                        type="text"
                        value={newStreetAddress}
                        onChange={(e) => setNewStreetAddress(e.target.value)}
                        required
                        placeholder="e.g. Street 11"
                        className="w-full bg-white border border-slate-300 text-slate-900 rounded-xl px-3 py-2 outline-none focus:border-emerald-600"
                      />
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* SCENARIO B: NEW CUSTOMER / GUEST FORM */}
            {(recognitionStatus === 'not_recognized' || recognitionStatus === 'idle') && (
              <div className="space-y-3 pt-1">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Customer Name *</label>
                  <input
                    type="text"
                    value={newCustomerName}
                    onChange={(e) => setNewCustomerName(e.target.value)}
                    required
                    placeholder="Enter your full name"
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Villa Number *</label>
                    <input
                      type="text"
                      value={newVillaNumber}
                      onChange={(e) => setNewVillaNumber(e.target.value)}
                      required
                      placeholder="e.g. Villa 24"
                      className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-bold rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Zone *</label>
                    <input
                      type="text"
                      value={newZone}
                      onChange={(e) => setNewZone(e.target.value)}
                      required
                      placeholder="e.g. Zone 19, Abu Dhabi"
                      className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                    />
                  </div>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Street / Area *</label>
                  <input
                    type="text"
                    value={newStreetAddress}
                    onChange={(e) => setNewStreetAddress(e.target.value)}
                    required
                    placeholder="e.g. Main Street, Al Rawdah"
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Landmark (Optional)</label>
                  <input
                    type="text"
                    value={newLandmark}
                    onChange={(e) => setNewLandmark(e.target.value)}
                    placeholder="e.g. Near Green Mosque"
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Delivery Notes (Optional)</label>
                  <textarea
                    value={newDeliveryNotes}
                    onChange={(e) => setNewDeliveryNotes(e.target.value)}
                    placeholder="e.g. Call driver before arrival / Leave at gate"
                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl p-3 outline-none focus:border-emerald-600 focus:bg-white transition-colors h-16"
                  ></textarea>
                </div>
              </div>
            )}
          </div>
        )}

        {/* STEP 3: PAYMENT METHOD (COD ONLY) */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-3 shadow-sm">
          <h2 className="font-extrabold text-slate-900 text-sm">💵 3. Payment Method</h2>
          <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-600 text-emerald-950 font-bold flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-4 h-4 rounded-full bg-emerald-600 flex items-center justify-center">
                <div className="w-2 h-2 bg-white rounded-full" />
              </div>
              <span>Cash on Delivery</span>
            </div>
            <span className="text-[10px] text-emerald-800 bg-emerald-200/80 px-2 py-0.5 rounded font-black uppercase">
              Only Method
            </span>
          </div>
          <p className="text-[11px] text-slate-500">
            Pay the delivery person when your order arrives.
          </p>
        </div>

        {/* ORDER SUMMARY */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
          <h2 className="font-extrabold text-slate-900 text-sm">Order Summary</h2>

          <div className="space-y-2.5 divide-y divide-slate-100 max-h-56 overflow-y-auto pr-1">
            {cart.map((item) => {
              const price = parseFloat(item.product.sale_price || item.product.retail_price || item.product.price || 0);
              return (
                <div key={item.product.id} className="pt-2.5 first:pt-0 flex justify-between items-center text-xs">
                  <div>
                    <div className="font-bold text-slate-900">{item.product.name}</div>
                    <div className="text-[11px] text-slate-500">Qty: {item.quantity} &times; AED {price.toFixed(2)}</div>
                  </div>
                  <div className="font-mono font-bold text-slate-900">AED {(price * item.quantity).toFixed(2)}</div>
                </div>
              );
            })}
          </div>

          <div className="pt-3 border-t border-slate-200 font-mono text-xs space-y-1.5">
            <div className="flex justify-between text-slate-600">
              <span>Products</span>
              <span className="font-bold text-slate-900">AED {subtotal.toFixed(2)}</span>
            </div>
            <div className="flex justify-between text-emerald-700 font-bold">
              <span>Delivery</span>
              <span>FREE</span>
            </div>
            <div className="flex justify-between items-center text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
              <span>Total</span>
              <span className="text-xl font-black text-emerald-700">AED {subtotal.toFixed(2)}</span>
            </div>
            <div className="text-[11px] text-slate-500 font-sans pt-1">
              Payment: <strong>Cash on Delivery</strong>
            </div>
          </div>
        </div>

        {/* PRIMARY ACTION BUTTON */}
        <button
          type="submit"
          disabled={loading || recognitionStatus === 'recognizing'}
          className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs uppercase tracking-widest rounded-2xl transition-all shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2"
        >
          <MessageCircle className="w-5 h-5" />
          <span>{loading ? 'Creating your order...' : 'PLACE ORDER & CONTINUE TO WHATSAPP'}</span>
        </button>

      </form>
    </div>
  );
};
