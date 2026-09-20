import React, { useEffect, useState } from 'react';
import { ShoppingBag, Eye, RefreshCw, MessageCircle, Copy, Check, CheckCircle, Download, Upload, FileSpreadsheet, AlertCircle, X, CheckCircle2, FileText, Search, ChevronLeft, ChevronRight, DollarSign, ExternalLink, Clock, Phone, AlertTriangle } from 'lucide-react';
import { adminApi } from '../services/api';
import { useAdminRealtime } from '../context/AdminRealtimeContext';

const getAllowedNextStatuses = (currentStatus) => {
  switch (currentStatus) {
    case 'awaiting_whatsapp':
    case 'pending':
      return ['confirmed', 'cancelled', 'expired'];
    case 'confirmed':
    case 'accepted':
      return ['preparing', 'cancelled'];
    case 'preparing':
      return ['ready', 'out_for_delivery', 'cancelled'];
    case 'ready':
      return ['out_for_delivery', 'cancelled'];
    case 'out_for_delivery':
      return ['delivered', 'failed_delivery'];
    case 'failed_delivery':
      return ['preparing', 'ready', 'cancelled'];
    case 'delivered':
    case 'cancelled':
    case 'expired':
    default:
      return [];
  }
};

export function OrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedStatus, setSelectedStatus] = useState('');
  const [selectedPaymentStatus, setSelectedPaymentStatus] = useState('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [totalOrders, setTotalOrders] = useState(0);
  const [totalPages, setTotalPages] = useState(1);

  const [activeOrder, setActiveOrder] = useState(null);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [copied, setCopied] = useState(false);
  const [copiedPhone, setCopiedPhone] = useState(false);

  // Status Change Reason Modal State
  const [reasonModal, setReasonModal] = useState({
    open: false,
    orderId: null,
    targetStatus: '',
    reason: '',
    notes: '',
  });

  // COD Payment Collection Modal State
  const [paymentModal, setPaymentModal] = useState({
    open: false,
    order: null,
    amount: '',
    reason: '',
  });

  // Orders Import Modal State
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importLoading, setImportLoading] = useState(false);
  const [importPreview, setImportPreview] = useState(null);
  const [importSuccess, setImportSuccess] = useState(null);
  const [importError, setImportError] = useState(null);

  const { refreshRealtime } = useAdminRealtime();

  // Debounce search input (350ms)
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, 350);
    return () => clearTimeout(handler);
  }, [search]);

  const fetchOrders = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getOrders({
        status: selectedStatus || undefined,
        payment_status: selectedPaymentStatus || undefined,
        q: debouncedSearch || undefined,
        page,
        per_page: perPage,
      });

      if (res && res.data) {
        if (Array.isArray(res.data.data)) {
          setOrders(res.data.data);
          const meta = res.data.meta || res.data;
          setTotalOrders(meta.total || res.data.data.length);
          setTotalPages(meta.last_page || Math.ceil((meta.total || res.data.data.length) / perPage) || 1);
        } else if (Array.isArray(res.data)) {
          setOrders(res.data);
          setTotalOrders(res.data.length);
          setTotalPages(1);
        }
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [selectedStatus, selectedPaymentStatus, debouncedSearch, page, perPage]);

  const handleUpdateStatus = async (orderId, newStatus, paymentStatus = null, reason = null) => {
    try {
      const res = await adminApi.updateOrderStatus(orderId, newStatus, paymentStatus, reason);
      if (res && (res.success || res.data)) {
        const updated = res.data || res;
        if (activeOrder && activeOrder.id === orderId) {
          setActiveOrder(updated);
        }
        fetchOrders();
        refreshRealtime();
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Failed to update order status');
      console.error(e);
    }
  };

  const requestStatusUpdate = (orderId, targetStatus) => {
    if (targetStatus === 'cancelled' || targetStatus === 'failed_delivery') {
      setReasonModal({
        open: true,
        orderId,
        targetStatus,
        reason: targetStatus === 'cancelled' ? 'Customer requested cancellation' : 'Customer unavailable',
        notes: '',
      });
      return;
    }
    handleUpdateStatus(orderId, targetStatus);
  };

  const handleConfirmReason = async () => {
    if (!reasonModal.orderId) return;
    const finalReason = reasonModal.reason === 'Other'
      ? (reasonModal.notes.trim() || 'Other reason')
      : (reasonModal.notes.trim() ? `${reasonModal.reason} - ${reasonModal.notes.trim()}` : reasonModal.reason);

    await handleUpdateStatus(reasonModal.orderId, reasonModal.targetStatus, null, finalReason);
    setReasonModal({ open: false, orderId: null, targetStatus: '', reason: '', notes: '' });
  };

  const handleOpenDetails = async (orderSummary) => {
    setActiveOrder(orderSummary);
    setDrawerLoading(true);
    try {
      const full = await adminApi.getOrder(orderSummary.id);
      if (full && (full.data || full.id)) {
        setActiveOrder(full.data || full);
      }
    } catch (e) {
      console.error('Failed to lazy load order details:', e);
    } finally {
      setDrawerLoading(false);
    }
  };

  const handleConfirmPayment = async () => {
    if (!paymentModal.order) return;
    try {
      await adminApi.collectPayment(paymentModal.order.id, parseFloat(paymentModal.amount), paymentModal.reason);
      if (activeOrder && activeOrder.id === paymentModal.order.id) {
        const refreshed = await adminApi.getOrder(paymentModal.order.id);
        setActiveOrder(refreshed.data || refreshed);
      }
      fetchOrders();
      setPaymentModal({ open: false, order: null, amount: '', reason: '' });
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to record COD payment.');
    }
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setImportFile(file);
      setImportPreview(null);
      setImportSuccess(null);
      setImportError(null);
    }
  };

  const handlePreviewImport = async () => {
    if (!importFile) return;
    setImportLoading(true);
    setImportError(null);
    try {
      const fd = new FormData();
      fd.append('file', importFile);
      const res = await adminApi.importOrders(fd);
      if (res && res.data) {
        setImportPreview(res.data);
      } else {
        setImportError('Failed to parse file preview.');
      }
    } catch (err) {
      setImportError(err.response?.data?.message || 'Error previewing import file.');
    } finally {
      setImportLoading(false);
    }
  };

  const handleConfirmImport = async () => {
    if (!importFile) return;
    setImportLoading(true);
    setImportError(null);
    try {
      const fd = new FormData();
      fd.append('file', importFile);
      fd.append('confirm', '1');
      const res = await adminApi.importOrders(fd);
      if (res && (res.success || res.data)) {
        setImportSuccess(res.message || 'Orders imported successfully!');
        setImportPreview(null);
        setImportFile(null);
        fetchOrders();
        refreshRealtime();
      } else {
        setImportError('Import completed with errors.');
      }
    } catch (err) {
      setImportError(err.response?.data?.message || 'Error executing order import.');
    } finally {
      setImportLoading(false);
    }
  };

  const generateWhatsAppText = (order) => {
    if (!order) return '';
    const custOrderNum = order.customer_order_number || 1;
    const villa = order.customer_villa ? `Villa ${order.customer_villa}` : '';
    const rawAddress = order.customer_address || order.delivery_address || '';
    
    // Deduplicate villa/zone if needed
    let fullAddr = villa ? `${villa}, ${rawAddress}` : rawAddress;
    
    let text = `Hello Baqqala,\n\n`;
    text += `I would like to place an order.\n\n`;
    text += `Customer Order: #${custOrderNum}\n`;
    text += `Baqqala Order ID: ${order.order_number}\n\n`;
    text += `Customer:\n${order.customer_name_snapshot || order.customer_name || 'Customer'}\n${order.customer_phone_snapshot || order.customer_phone || ''}\n\n`;
    text += `Delivery Address:\n${fullAddr}\n`;
    if (order.notes || order.customer_notes_snapshot) {
      text += `Notes: ${order.notes || order.customer_notes_snapshot}\n`;
    }
    text += `\nItems:\n`;
    order.items?.forEach(i => {
      text += `• ${i.product_name} × ${i.quantity}\n`;
    });
    text += `\nSubtotal: AED ${parseFloat(order.subtotal || order.total_amount || 0).toFixed(2)}\n`;
    text += `Delivery: FREE\n`;
    text += `Total: AED ${parseFloat(order.total_amount || order.total || 0).toFixed(2)}\n\n`;
    text += `Payment Method:\nCash on Delivery\n\n`;
    text += `Please confirm my order.\n\nThank you.`;

    return text;
  };

  const handleCopyText = (text) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 3000);
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header & Status Filter Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <ShoppingBag className="w-6 h-6 text-emerald-600" />
            Customer Orders
          </h1>
          <p className="text-xs text-slate-500 mt-1">Automatic order entry, WhatsApp confirmation & delivery fulfillment</p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          {/* Debounced Search */}
          <div className="relative w-full sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search order #, customer, villa..."
              className="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:border-emerald-600 shadow-xs"
            />
          </div>

          <button onClick={fetchOrders} className="p-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-bold transition-colors">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          
          <select
            value={selectedStatus}
            onChange={(e) => { setSelectedStatus(e.target.value); setPage(1); }}
            className="px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 shadow-xs"
          >
            <option value="">All Order Statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="preparing">Preparing</option>
            <option value="out_for_delivery">Out for Delivery</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>

          <select
            value={selectedPaymentStatus}
            onChange={(e) => { setSelectedPaymentStatus(e.target.value); setPage(1); }}
            className="px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 shadow-xs"
          >
            <option value="">All Payments</option>
            <option value="paid">Paid</option>
            <option value="pending">Unpaid / Pending</option>
            <option value="cod">Cash on Delivery</option>
          </select>

          {/* Export & Import side by side */}
          <div className="flex items-center gap-2">
            <a
              href={adminApi.getOrderExportUrl({ status: selectedStatus, payment_status: selectedPaymentStatus, q: debouncedSearch })}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold transition-all shadow-xs"
            >
              <Download className="w-4 h-4 text-slate-500" />
              <span>Export</span>
            </a>

            <button
              onClick={() => {
                setImportModalOpen(true);
                setImportPreview(null);
                setImportFile(null);
                setImportSuccess(null);
                setImportError(null);
              }}
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold transition-all shadow-xs"
            >
              <Upload className="w-4 h-4 text-slate-500" />
              <span>Import</span>
            </button>
          </div>
        </div>
      </div>

      {/* Orders Table */}
      <div className="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-slate-50 border-b border-slate-200 font-bold text-slate-900 uppercase text-[10px] tracking-wider">
              <tr>
                <th className="p-4">Global Order ID</th>
                <th className="p-4">Customer Order</th>
                <th className="p-4">Customer</th>
                <th className="p-4">Delivery Address</th>
                <th className="p-4 text-right">Total</th>
                <th className="p-4 text-center">WhatsApp</th>
                <th className="p-4">Status</th>
                <th className="p-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="8" className="p-8 text-center text-slate-400">Loading customer orders...</td>
                </tr>
              ) : orders.length === 0 ? (
                <tr>
                  <td colSpan="8" className="p-8 text-center text-slate-400">No customer orders found.</td>
                </tr>
              ) : (
                orders.map((o) => (
                  <tr key={o.id} className="hover:bg-slate-50 transition-colors">
                    <td className="p-4 font-bold text-slate-900 font-mono text-sm">{o.order_number}</td>
                    <td className="p-4">
                      <span className="px-2.5 py-1 bg-emerald-100 text-emerald-900 border border-emerald-200 rounded-full font-extrabold font-mono text-xs">
                        Customer #{o.customer_order_number || 1}
                      </span>
                    </td>
                    <td className="p-4 font-bold text-slate-900">
                      <div>{o.customer_name_snapshot || o.customer_name}</div>
                      <div className="text-[10px] text-slate-500 font-mono font-semibold">{o.customer_phone_snapshot || o.customer_phone}</div>
                    </td>
                    <td className="p-4">
                      <div className="font-bold text-slate-900">{o.customer_villa ? `Villa ${o.customer_villa}` : 'Villa Delivery'}</div>
                      <div className="text-[10px] text-slate-500 line-clamp-1">{o.customer_address || o.delivery_address}</div>
                    </td>
                    <td className="p-4 text-right font-mono font-black text-emerald-700 text-sm">
                      AED {parseFloat(o.total_amount || o.total || 0).toFixed(2)}
                    </td>
                    <td className="p-4 text-center">
                      <span className="inline-flex items-center gap-1 text-[10px] uppercase font-extrabold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        {o.whatsapp_status || 'prepared'}
                      </span>
                    </td>
                    <td className="p-4">
                      {getAllowedNextStatuses(o.status).length === 0 ? (
                        <span className={`px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase border ${
                          o.status === 'delivered' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' :
                          o.status === 'cancelled' ? 'bg-rose-100 text-rose-800 border-rose-200' :
                          'bg-slate-100 text-slate-700 border-slate-200'
                        }`}>
                          {o.status.replace('_', ' ')}
                        </span>
                      ) : (
                        <select
                          value={o.status}
                          onChange={(e) => requestStatusUpdate(o.id, e.target.value)}
                          className="px-2.5 py-1 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 outline-none focus:border-emerald-600 capitalize cursor-pointer"
                        >
                          <option value={o.status} disabled>{o.status.replace('_', ' ')} (Current)</option>
                          {getAllowedNextStatuses(o.status).map(st => (
                            <option key={st} value={st}>&rarr; {st.replace('_', ' ')}</option>
                          ))}
                        </select>
                      )}
                    </td>
                    <td className="p-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        {o.status === 'pending' && (
                          <button
                            onClick={() => requestStatusUpdate(o.id, 'confirmed')}
                            className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-extrabold shadow-xs transition-colors"
                          >
                            Accept & Reserve
                          </button>
                        )}
                        <button
                          onClick={() => handleOpenDetails(o)}
                          className="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-bold inline-flex items-center gap-1 transition-colors"
                        >
                          <Eye className="w-3.5 h-3.5 text-emerald-600" /> Details
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Server-side Pagination Controls */}
        <div className="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 font-medium bg-slate-50/50">
          <div className="flex items-center gap-2">
            <span>Showing {orders.length} of {totalOrders} orders</span>
            <span className="text-slate-300">|</span>
            <span className="flex items-center gap-1.5">
              <span>Rows:</span>
              <select
                value={perPage}
                onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1); }}
                className="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-800 focus:outline-none"
              >
                <option value={25}>25</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
            </span>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page <= 1 || loading}
              className="px-3 py-1.5 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed border border-slate-200 rounded-xl text-xs font-bold text-slate-700 flex items-center gap-1 transition-all shadow-2xs"
            >
              <ChevronLeft className="w-3.5 h-3.5" /> Prev
            </button>

            <span className="px-3 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl font-bold font-mono text-xs">
              Page {page} of {totalPages}
            </span>

            <button
              onClick={() => setPage(p => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages || loading}
              className="px-3 py-1.5 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed border border-slate-200 rounded-xl text-xs font-bold text-slate-700 flex items-center gap-1 transition-all shadow-2xs"
            >
              Next <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      {/* Order Detail Drawer / Modal */}
      {activeOrder && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-lg space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl text-slate-900">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-[10px] uppercase font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                    Status: {activeOrder.status.toUpperCase()}
                  </span>
                  <span className="text-xs font-extrabold font-mono bg-emerald-600 text-white px-2.5 py-0.5 rounded-full">
                    Customer Order #{activeOrder.customer_order_number || 1}
                  </span>
                  {drawerLoading && (
                    <RefreshCw className="w-3.5 h-3.5 animate-spin text-emerald-600" />
                  )}
                </div>
                <h3 className="font-mono font-black text-xl text-slate-900 mt-1">{activeOrder.order_number}</h3>
              </div>
              <button onClick={() => setActiveOrder(null)} className="text-slate-400 hover:text-slate-700 font-bold">&times;</button>
            </div>

            {/* Customer Details Snapshot */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
              <p><span className="text-slate-500 font-medium">Customer:</span> <strong className="text-slate-900">{activeOrder.customer_name_snapshot || activeOrder.customer_name}</strong></p>
              <div className="flex items-center justify-between">
                <p><span className="text-slate-500 font-medium">Phone:</span> <strong className="text-slate-900 font-mono">{activeOrder.customer_phone_snapshot || activeOrder.customer_phone}</strong></p>
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(activeOrder.customer_phone_snapshot || activeOrder.customer_phone || '');
                    setCopiedPhone(true);
                    setTimeout(() => setCopiedPhone(false), 2000);
                  }}
                  className="px-2 py-0.5 text-[10px] bg-white border border-slate-200 rounded text-slate-600 hover:bg-slate-100 font-bold"
                >
                  {copiedPhone ? 'Copied' : 'Copy Phone'}
                </button>
              </div>
              <p><span className="text-slate-500 font-medium">Delivery:</span> <strong className="text-slate-900">{activeOrder.customer_villa ? `Villa ${activeOrder.customer_villa}, ` : ''}{activeOrder.customer_address || activeOrder.delivery_address}</strong></p>
              
              <div className="pt-2 border-t border-slate-200/60 flex items-center justify-between">
                <div>
                  <span className="text-slate-500 font-medium">Payment:</span> <strong className="text-slate-900 uppercase font-mono">{activeOrder.payment_method}</strong> ({activeOrder.payment_status})
                </div>
                {activeOrder.payment_status !== 'paid' && (
                  <button
                    onClick={() => setPaymentModal({ open: true, order: activeOrder, amount: activeOrder.total_amount || '', reason: '' })}
                    className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-extrabold flex items-center gap-1 shadow-2xs transition-colors"
                  >
                    <DollarSign className="w-3 h-3" /> Collect COD
                  </button>
                )}
              </div>
            </div>

            {/* WhatsApp Actions Box */}
            <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-2 text-xs">
              <div className="flex justify-between items-center font-extrabold text-emerald-950">
                <span className="flex items-center gap-1.5">
                  <MessageCircle className="w-4 h-4 text-emerald-700" />
                  WhatsApp Handoff
                </span>
                <div className="flex items-center gap-1.5">
                  <button
                    onClick={() => {
                      const msg = generateWhatsAppText(activeOrder);
                      const targetDigits = (activeOrder.customer_phone_snapshot || activeOrder.customer_phone || '').replace(/[^\d]/g, '');
                      window.open(`https://wa.me/${targetDigits}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener,noreferrer');
                    }}
                    className="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors"
                  >
                    <ExternalLink className="w-3 h-3" /> Open WA
                  </button>
                  <button
                    onClick={() => handleCopyText(generateWhatsAppText(activeOrder))}
                    className="px-2 py-1 bg-white hover:bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-lg text-[10px] font-bold flex items-center gap-1 transition-colors"
                  >
                    {copied ? <Check className="w-3 h-3 text-emerald-600" /> : <Copy className="w-3 h-3 text-slate-500" />}
                    {copied ? 'Copied' : 'Copy'}
                  </button>
                </div>
              </div>
              <textarea
                readOnly
                value={generateWhatsAppText(activeOrder)}
                className="w-full bg-white border border-emerald-200 text-slate-800 font-mono text-[11px] rounded-xl p-3 outline-none h-24 resize-none"
              ></textarea>
            </div>

            {/* Order Items */}
            <div className="space-y-2">
              <h4 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Order Items ({activeOrder.items?.length || 0})</h4>
              <div className="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden max-h-40 overflow-y-auto">
                {activeOrder.items?.map((item) => (
                  <div key={item.id} className="p-2.5 bg-white flex items-center justify-between text-xs">
                    <div>
                      <span className="font-bold text-slate-900 block">{item.product_name}</span>
                      <span className="text-slate-500">{item.quantity} &times; AED {parseFloat(item.unit_price).toFixed(2)}</span>
                    </div>
                    <span className="font-mono font-bold text-emerald-700">AED {parseFloat(item.total).toFixed(2)}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Status Audit Trail */}
            {activeOrder.statusHistory && activeOrder.statusHistory.length > 0 && (
              <div className="space-y-1.5">
                <h4 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                  <Clock className="w-3.5 h-3.5 text-slate-500" /> Status Audit Trail
                </h4>
                <div className="border border-slate-200 rounded-2xl p-2.5 bg-slate-50 text-[11px] max-h-32 overflow-y-auto space-y-1">
                  {activeOrder.statusHistory.map((sh, idx) => (
                    <div key={idx} className="flex justify-between items-start py-1 border-b border-slate-100 last:border-0">
                      <div>
                        <span className="font-bold text-slate-900 capitalize">{sh.to_status?.replace('_', ' ')}</span>
                        {sh.reason && <p className="text-[10px] text-slate-500">{sh.reason}</p>}
                      </div>
                      <div className="text-right text-[10px] text-slate-400 font-mono">
                        <div>{sh.changed_by}</div>
                        <div>{new Date(sh.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="pt-2 border-t border-slate-100 flex items-center justify-between font-mono font-black text-slate-900 text-base">
              <span>TOTAL DUE:</span>
              <span className="text-xl text-emerald-700">AED {parseFloat(activeOrder.total_amount || activeOrder.total || 0).toFixed(2)}</span>
            </div>

            {/* Lifecycle Quick Actions in Drawer */}
            <div className="pt-2 flex flex-wrap gap-2">
              {activeOrder.status === 'pending' && (
                <button
                  onClick={() => requestStatusUpdate(activeOrder.id, 'confirmed')}
                  className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase rounded-xl shadow-xs flex items-center justify-center gap-1.5 transition-all"
                >
                  <CheckCircle className="w-4 h-4" /> Confirm & Reserve
                </button>
              )}
              {activeOrder.status === 'confirmed' && (
                <button
                  onClick={() => requestStatusUpdate(activeOrder.id, 'preparing')}
                  className="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase rounded-xl shadow-xs flex items-center justify-center gap-1.5 transition-all"
                >
                  Start Preparing
                </button>
              )}
              {activeOrder.status === 'preparing' && (
                <button
                  onClick={() => requestStatusUpdate(activeOrder.id, 'out_for_delivery')}
                  className="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs uppercase rounded-xl shadow-xs flex items-center justify-center gap-1.5 transition-all"
                >
                  Dispatch for Delivery
                </button>
              )}
              {activeOrder.status === 'out_for_delivery' && (
                <>
                  <button
                    onClick={() => requestStatusUpdate(activeOrder.id, 'delivered')}
                    className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase rounded-xl shadow-xs flex items-center justify-center gap-1.5 transition-all"
                  >
                    Mark Delivered (Finalize Sale)
                  </button>
                  <button
                    onClick={() => requestStatusUpdate(activeOrder.id, 'failed_delivery')}
                    className="py-2.5 px-3 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs uppercase rounded-xl shadow-xs transition-all"
                  >
                    Fail Delivery
                  </button>
                </>
              )}
              {getAllowedNextStatuses(activeOrder.status).includes('cancelled') && (
                <button
                  onClick={() => requestStatusUpdate(activeOrder.id, 'cancelled')}
                  className="py-2.5 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs uppercase rounded-xl transition-all"
                >
                  Cancel Order
                </button>
              )}
            </div>

          </div>
        </div>
      )}

      {/* Mandatory Reason Modal for Cancellation & Failed Delivery */}
      {reasonModal.open && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 text-slate-900">
            <div className="flex items-center gap-3 pb-3 border-b border-slate-100">
              <div className="w-10 h-10 rounded-2xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold">
                <AlertTriangle className="w-5 h-5 text-rose-600" />
              </div>
              <div>
                <h3 className="text-base font-bold text-slate-900 capitalize">
                  {reasonModal.targetStatus === 'cancelled' ? 'Cancel Customer Order' : 'Record Failed Delivery'}
                </h3>
                <p className="text-xs text-slate-500">Document reason for inventory & audit tracking</p>
              </div>
            </div>

            <div className="space-y-3 text-xs">
              <label className="block font-bold text-slate-700">Select Reason:</label>
              <select
                value={reasonModal.reason}
                onChange={(e) => setReasonModal({ ...reasonModal, reason: e.target.value })}
                className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-medium text-slate-900 outline-none focus:border-emerald-600"
              >
                {reasonModal.targetStatus === 'cancelled' ? (
                  <>
                    <option value="Customer requested cancellation">Customer requested cancellation</option>
                    <option value="Out of stock">Out of stock</option>
                    <option value="Unable to contact customer">Unable to contact customer</option>
                    <option value="Duplicate order">Duplicate order</option>
                    <option value="Delivery issue">Delivery issue</option>
                    <option value="Other">Other (specify below)</option>
                  </>
                ) : (
                  <>
                    <option value="Customer unavailable">Customer unavailable</option>
                    <option value="Wrong address">Wrong address</option>
                    <option value="Customer refused">Customer refused</option>
                    <option value="No response">No response</option>
                    <option value="Other">Other (specify below)</option>
                  </>
                )}
              </select>

              <label className="block font-bold text-slate-700">Additional Notes / Details:</label>
              <textarea
                value={reasonModal.notes}
                onChange={(e) => setReasonModal({ ...reasonModal, notes: e.target.value })}
                placeholder={reasonModal.reason === 'Other' ? 'Mandatory explanation...' : 'Optional notes...'}
                className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-medium text-slate-900 outline-none focus:border-emerald-600 h-20 resize-none"
              ></textarea>
            </div>

            <div className="flex gap-2 pt-2">
              <button
                type="button"
                onClick={() => setReasonModal({ open: false, orderId: null, targetStatus: '', reason: '', notes: '' })}
                className="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl"
              >
                Go Back
              </button>
              <button
                type="button"
                onClick={handleConfirmReason}
                className="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs"
              >
                Confirm {reasonModal.targetStatus.replace('_', ' ')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* COD Payment Collection Modal */}
      {paymentModal.open && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 text-slate-900">
            <div className="flex items-center gap-3 pb-3 border-b border-slate-100">
              <div className="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                <DollarSign className="w-5 h-5 text-emerald-600" />
              </div>
              <div>
                <h3 className="text-base font-bold text-slate-900">Record COD Cash Collection</h3>
                <p className="text-xs text-slate-500">Order {paymentModal.order?.order_number}</p>
              </div>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Cash Amount Collected (AED):</label>
                <input
                  type="number"
                  step="0.01"
                  value={paymentModal.amount}
                  onChange={(e) => setPaymentModal({ ...paymentModal, amount: e.target.value })}
                  className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-mono font-bold text-slate-900 text-base outline-none focus:border-emerald-600"
                />
                <span className="text-[10px] text-slate-400 mt-1 block">
                  Expected Total: AED {parseFloat(paymentModal.order?.total_amount || 0).toFixed(2)}
                </span>
              </div>

              {Math.abs(parseFloat(paymentModal.amount || 0) - parseFloat(paymentModal.order?.total_amount || 0)) > 0.01 && (
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Reason for Difference:</label>
                  <input
                    type="text"
                    value={paymentModal.reason}
                    onChange={(e) => setPaymentModal({ ...paymentModal, reason: e.target.value })}
                    placeholder="e.g. Customer tipped / rounding discount"
                    className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-medium text-slate-900 outline-none focus:border-emerald-600"
                  />
                </div>
              )}
            </div>

            <div className="flex gap-2 pt-2">
              <button
                type="button"
                onClick={() => setPaymentModal({ open: false, order: null, amount: '', reason: '' })}
                className="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmPayment}
                className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs"
              >
                Record Payment
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Orders Import Modal */}
      {importModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl space-y-5 text-slate-900">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                  <FileSpreadsheet className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900">Import Customer Orders</h3>
                  <p className="text-xs text-slate-500">Batch upload customer orders, external orders, or historical orders</p>
                </div>
              </div>
              <button
                onClick={() => setImportModalOpen(false)}
                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Template Download Card */}
            <div className="p-4 bg-emerald-50/70 border border-emerald-200/80 rounded-2xl flex items-center justify-between gap-4">
              <div>
                <p className="text-xs font-bold text-emerald-950">1. Download Order Import Template</p>
                <p className="text-[11px] text-emerald-800 mt-0.5">
                  Pre-formatted with sample columns: customer phone, address, items, quantities, and prices.
                </p>
              </div>
              <a
                href={adminApi.getOrderImportTemplateUrl()}
                download="Baqqala_Orders_Import_Template.csv"
                className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-extrabold rounded-xl shrink-0 shadow-xs transition-colors"
              >
                <Download className="w-4 h-4" /> Download Template
              </a>
            </div>

            {/* Error & Success Messages */}
            {importError && (
              <div className="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
                <span>{importError}</span>
              </div>
            )}
            {importSuccess && (
              <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
                <span>{importSuccess}</span>
              </div>
            )}

            {/* File Upload Form */}
            <div className="space-y-3">
              <label className="block text-xs font-bold text-slate-700">2. Select CSV or Excel File</label>
              <div className="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 text-center cursor-pointer bg-slate-50 transition-colors relative">
                <input
                  type="file"
                  accept=".csv, .xlsx, .xls"
                  onChange={handleFileChange}
                  className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                />
                <FileSpreadsheet className="w-8 h-8 text-slate-400 mx-auto mb-2" />
                <p className="text-xs font-bold text-slate-700">
                  {importFile ? importFile.name : 'Click to select or drop order spreadsheet here'}
                </p>
                <p className="text-[11px] text-slate-400 mt-1">Supports standard CSV or Excel exports</p>
              </div>
            </div>

            {/* Preview Section */}
            {importFile && !importPreview && (
              <button
                type="button"
                onClick={handlePreviewImport}
                disabled={importLoading}
                className="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-2"
              >
                {importLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Eye className="w-4 h-4" />}
                Analyze & Preview File
              </button>
            )}

            {/* Preview Summary */}
            {importPreview && (
              <div className="space-y-4 border-t border-slate-200 pt-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-600">Import Verification Summary</h4>
                  <span className="text-xs font-bold text-emerald-700">Ready to Process</span>
                </div>

                <div className="grid grid-cols-4 gap-2 text-center">
                  <div className="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                    <div className="text-lg font-black text-slate-900">{importPreview.total_rows}</div>
                    <div className="text-[10px] text-slate-500 font-bold uppercase">Total Rows</div>
                  </div>
                  <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-2.5">
                    <div className="text-lg font-black text-emerald-700">{importPreview.valid_count}</div>
                    <div className="text-[10px] text-emerald-700 font-bold uppercase">Valid Rows</div>
                  </div>
                  <div className="bg-blue-50 border border-blue-200 rounded-xl p-2.5">
                    <div className="text-lg font-black text-blue-700">{importPreview.new_customers_est}</div>
                    <div className="text-[10px] text-blue-700 font-bold uppercase">New Customers</div>
                  </div>
                  <div className="bg-rose-50 border border-rose-200 rounded-xl p-2.5">
                    <div className="text-lg font-black text-rose-700">{importPreview.errors_count}</div>
                    <div className="text-[10px] text-rose-700 font-bold uppercase">Errors</div>
                  </div>
                </div>

                {/* Sample Rows Table */}
                <div className="max-h-48 overflow-y-auto border border-slate-200 rounded-xl">
                  <table className="w-full text-left text-[11px] text-slate-700">
                    <thead className="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 border-b border-slate-200 sticky top-0">
                      <tr>
                        <th className="p-2">Row</th>
                        <th className="p-2">Customer / Phone</th>
                        <th className="p-2">Item</th>
                        <th className="p-2 text-right">Qty & Price</th>
                        <th className="p-2 text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {importPreview.rows?.map((r, i) => (
                        <tr key={i} className="hover:bg-slate-50">
                          <td className="p-2 font-mono text-slate-400 font-bold">#{r.row_number}</td>
                          <td className="p-2 font-bold text-slate-900">
                            <div>{r.customer_name}</div>
                            <div className="text-[10px] text-slate-400 font-mono">{r.customer_phone}</div>
                          </td>
                          <td className="p-2 text-slate-800 font-medium">{r.item_sku_or_name}</td>
                          <td className="p-2 text-right font-mono font-bold text-slate-900">
                            {r.item_quantity} &times; AED {parseFloat(r.item_price).toFixed(2)}
                          </td>
                          <td className="p-2 text-center">
                            <span className={`px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase ${
                              r.status_code === 'VALID' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                            }`}>
                              {r.status_code}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Execute Confirm Import Button */}
                <button
                  type="button"
                  onClick={handleConfirmImport}
                  disabled={importLoading || importPreview.valid_count === 0}
                  className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all shadow-md flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {importLoading ? (
                    <RefreshCw className="w-4 h-4 animate-spin" />
                  ) : (
                    <CheckCircle2 className="w-4 h-4" />
                  )}
                  <span>Confirm & Import {importPreview.valid_count} Valid Order Rows</span>
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
