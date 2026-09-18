import React, { useState, useEffect, useRef } from 'react';
import { 
  Scan, Plus, CheckCircle, AlertTriangle, Clock, DollarSign, 
  FileText, Printer, Download, RefreshCw, X, Search, Building2,
  Trash2, ArrowLeft, ArrowRight, ShieldCheck, HelpCircle, Eye,
  Package, AlertCircle, Sparkles, Check, ChevronDown
} from 'lucide-react';
import { adminApi } from '../services/api';

export function ReceivingPage() {
  // Tab / View Mode: 'station' (new / edit receipt) or 'history' (list of GRNs)
  const [viewMode, setViewMode] = useState('station');

  // KPIs
  const [kpis, setKpis] = useState({
    draft_count: 0,
    pending_count: 0,
    received_today_count: 0,
    received_today_value: 0,
    month_receipts_count: 0,
    month_total_value: 0,
  });

  // Receiving Session State
  const [currentReceiptId, setCurrentReceiptId] = useState(null);
  const [grnNumber, setGrnNumber] = useState('GRN-NEW');
  const [status, setStatus] = useState('draft');
  const [supplierId, setSupplierId] = useState('');
  const [supplierNameSnapshot, setSupplierNameSnapshot] = useState('');
  const [supplierInvoiceNumber, setSupplierInvoiceNumber] = useState('');
  const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().split('T')[0]);
  const [purchaseReference, setPurchaseReference] = useState('');
  const [receivingDate, setReceivingDate] = useState(new Date().toISOString().split('T')[0]);
  const [discount, setDiscount] = useState(0);
  const [otherCharges, setOtherCharges] = useState(0);
  const [paymentStatus, setPaymentStatus] = useState('unpaid');
  const [notes, setNotes] = useState('');
  const [items, setItems] = useState([]);

  // Suppliers List & Search
  const [suppliers, setSuppliers] = useState([]);
  const [supplierSearch, setSupplierSearch] = useState('');
  const [selectedSupplierObj, setSelectedSupplierObj] = useState(null);
  const [duplicateInvoiceWarning, setDuplicateInvoiceWarning] = useState(false);

  // Scanner State (Datalogic QuickScan Lite)
  const [scannerInput, setScannerInput] = useState('');
  const [scannerStatus, setScannerStatus] = useState('READY'); // READY, PROCESSING, FOUND, NOT_FOUND
  const [rapidScanMode, setRapidScanMode] = useState(true);
  const [lastScannedBarcode, setLastScannedBarcode] = useState('');
  const [lastScanTime, setLastScanTime] = useState(null);
  const [scanFlashId, setScanFlashId] = useState(null);
  const [showScannerTest, setShowScannerTest] = useState(false);
  const [showScannerHelp, setShowScannerHelp] = useState(false);

  // In-Memory Barcode Cache: barcode -> product object
  const barcodeCache = useRef(new Map());
  const scannerInputRef = useRef(null);

  // Modals
  const [showAddSupplierModal, setShowAddSupplierModal] = useState(false);
  const [newSupplierForm, setNewSupplierForm] = useState({ name: '', phone: '', email: '', address: '', tax_number: '' });
  const [showUnknownModal, setShowUnknownModal] = useState(false);
  const [unknownBarcode, setUnknownBarcode] = useState('');
  const [newProductForm, setNewProductForm] = useState({ name: '', unit: 'piece', wholesale_cost: 0, retail_price: 0 });
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [feedbackMsg, setFeedbackMsg] = useState(null);
  const [errorMsg, setErrorMsg] = useState(null);

  // History & Filters
  const [receiptsList, setReceiptsList] = useState([]);
  const [receiptsLoading, setReceiptsLoading] = useState(false);
  const [historySearch, setHistorySearch] = useState('');
  const [historyStatusFilter, setHistoryStatusFilter] = useState('');
  const [viewingGrn, setViewingGrn] = useState(null);
  const [showPrintModal, setShowPrintModal] = useState(false);
  const [printReceiptData, setPrintReceiptData] = useState(null);
  const [showReturnModal, setShowReturnModal] = useState(false);
  const [returnItem, setReturnItem] = useState(null);
  const [returnQty, setReturnQty] = useState(1);
  const [returnReason, setReturnReason] = useState('');

  // Audio Beep for fast scanning feedback
  const playScanBeep = (freq = 880, duration = 0.08) => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, ctx.currentTime);
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + duration);
    } catch (e) {
      // AudioContext not allowed or supported
    }
  };

  // Initial Load
  useEffect(() => {
    loadKPIs();
    loadSuppliers();
    loadHistory();
  }, []);

  const loadKPIs = async () => {
    try {
      const res = await adminApi.getReceivingKPIs();
      if (res?.data) {
        setKpis(res.data);
      }
    } catch (e) {
      console.error('Failed to load receiving KPIs:', e);
    }
  };

  const loadSuppliers = async () => {
    try {
      const res = await adminApi.getSuppliers();
      if (res?.data) {
        setSuppliers(res.data);
      }
    } catch (e) {
      console.error('Failed to load suppliers:', e);
    }
  };

  const loadHistory = async () => {
    setReceiptsLoading(true);
    try {
      const params = {};
      if (historyStatusFilter) params.status = historyStatusFilter;
      if (historySearch) params.q = historySearch;
      const res = await adminApi.getReceivingHistory(params);
      if (res?.data?.data) {
        setReceiptsList(res.data.data);
      }
    } catch (e) {
      console.error('Failed to load receiving history:', e);
    } finally {
      setReceiptsLoading(false);
    }
  };

  // Focus management: automatically focus scanner input when in station mode
  useEffect(() => {
    if (viewMode === 'station' && scannerInputRef.current) {
      scannerInputRef.current.focus();
    }
  }, [viewMode, items.length]);

  // Supplier selection handler
  const handleSelectSupplier = (id) => {
    setSupplierId(id);
    const supp = suppliers.find(s => s.id === parseInt(id));
    if (supp) {
      setSelectedSupplierObj(supp);
      setSupplierNameSnapshot(supp.name);
    } else {
      setSelectedSupplierObj(null);
      setSupplierNameSnapshot('');
    }
  };

  // Duplicate Invoice Check
  useEffect(() => {
    if (supplierId && supplierInvoiceNumber && receiptsList.length > 0) {
      const dup = receiptsList.some(r => 
        r.supplier_id === parseInt(supplierId) && 
        r.supplier_invoice_number?.trim().toLowerCase() === supplierInvoiceNumber.trim().toLowerCase() &&
        r.id !== currentReceiptId
      );
      setDuplicateInvoiceWarning(dup);
    } else {
      setDuplicateInvoiceWarning(false);
    }
  }, [supplierId, supplierInvoiceNumber, receiptsList, currentReceiptId]);

  // Barcode Scanner Event Listener (Datalogic QuickScan Lite USB Keyboard wedge)
  const handleScannerKeyDown = async (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const code = scannerInput.trim();
      if (!code) return;
      setScannerInput('');
      await processBarcode(code);
    }
  };

  // Process Scanned Barcode
  const processBarcode = async (barcode) => {
    setScannerStatus('PROCESSING');
    setLastScannedBarcode(barcode);
    setLastScanTime(new Date().toLocaleTimeString());

    // 1. Check if barcode is ALREADY in current receiving items list
    const existingIndex = items.findIndex(item => item.barcode === barcode);
    if (existingIndex !== -1) {
      // REPEATED SCAN: increment quantity +1 in place!
      const updated = [...items];
      updated[existingIndex].quantity_received += 1;
      updated[existingIndex].quantity_sellable = Math.max(0, updated[existingIndex].quantity_received - (updated[existingIndex].quantity_damaged || 0));
      // recalculate line subtotal
      const lineCost = updated[existingIndex].unit_cost;
      const lineDisc = updated[existingIndex].discount || 0;
      const lineTax = updated[existingIndex].tax_amount || 0;
      updated[existingIndex].subtotal = Math.max(0, (updated[existingIndex].quantity_received * lineCost) - lineDisc + lineTax);

      setItems(updated);
      setScannerStatus('FOUND');
      setScanFlashId(updated[existingIndex].product_id);
      playScanBeep(1046, 0.07); // High beep for increment
      setTimeout(() => setScanFlashId(null), 800);
      return;
    }

    // 2. Check local in-memory session cache
    let product = barcodeCache.current.get(barcode);

    // 3. If not in cache, query API
    if (!product) {
      try {
        const res = await adminApi.lookupBarcode(barcode);
        if (res?.data?.found && res.data.product) {
          product = res.data.product;
          barcodeCache.current.set(barcode, product);
        }
      } catch (err) {
        console.error('Barcode lookup error:', err);
      }
    }

    if (product) {
      // Add new row to items table
      const unitCost = Number(product.wholesale_cost || 0);
      const taxAmt = Number((unitCost * 0.05).toFixed(2)); // UAE 5% VAT default
      const subtotal = Number((unitCost + taxAmt).toFixed(2));

      const newItem = {
        product_id: product.id,
        barcode: product.barcode || barcode,
        product_name: product.name,
        sku: product.sku || `SKU-${product.id}`,
        unit: product.unit || 'piece',
        quantity_expected: 1,
        quantity_received: 1,
        quantity_damaged: 0,
        quantity_sellable: 1,
        unit_cost: unitCost,
        discount: 0,
        tax_amount: taxAmt,
        subtotal: subtotal,
        expiry_date: product.expiry_date || '',
        batch_number: '',
        notes: '',
      };

      setItems(prev => [newItem, ...prev]);
      setScannerStatus('FOUND');
      setScanFlashId(product.id);
      playScanBeep(880, 0.09);
      setTimeout(() => setScanFlashId(null), 800);
    } else {
      // Unknown barcode
      setScannerStatus('NOT_FOUND');
      playScanBeep(330, 0.18); // Low warning buzz
      setUnknownBarcode(barcode);
      setShowUnknownModal(true);
    }
  };

  // Item Field Changes
  const handleItemChange = (index, field, value) => {
    const updated = [...items];
    const item = { ...updated[index] };

    if (field === 'quantity_received') {
      item.quantity_received = Math.max(1, parseInt(value) || 1);
      item.quantity_sellable = Math.max(0, item.quantity_received - (item.quantity_damaged || 0));
    } else if (field === 'quantity_damaged') {
      item.quantity_damaged = Math.max(0, parseInt(value) || 0);
      item.quantity_sellable = Math.max(0, item.quantity_received - item.quantity_damaged);
    } else if (field === 'unit_cost') {
      item.unit_cost = Math.max(0, parseFloat(value) || 0);
    } else if (field === 'discount') {
      item.discount = Math.max(0, parseFloat(value) || 0);
    } else if (field === 'tax_amount') {
      item.tax_amount = Math.max(0, parseFloat(value) || 0);
    } else {
      item[field] = value;
    }

    // Recalculate line subtotal
    const lineCost = item.unit_cost || 0;
    const lineDisc = item.discount || 0;
    const lineTax = item.tax_amount || 0;
    item.subtotal = Number(Math.max(0, (item.quantity_received * lineCost) - lineDisc + lineTax).toFixed(2));

    updated[index] = item;
    setItems(updated);
  };

  const handleRemoveItem = (index) => {
    setItems(items.filter((_, i) => i !== index));
  };

  // Grand Totals Computation
  const subtotal = items.reduce((acc, item) => acc + (item.quantity_received * item.unit_cost), 0);
  const totalLineDiscount = items.reduce((acc, item) => acc + (item.discount || 0), 0);
  const totalTax = items.reduce((acc, item) => acc + (item.tax_amount || 0), 0);
  const totalItemsCount = items.length;
  const totalUnitsReceived = items.reduce((acc, item) => acc + item.quantity_received, 0);
  const totalUnitsDamaged = items.reduce((acc, item) => acc + (item.quantity_damaged || 0), 0);
  const totalUnitsSellable = items.reduce((acc, item) => acc + item.quantity_sellable, 0);
  const grandTotal = Number(Math.max(0, subtotal - totalLineDiscount - Number(discount || 0) + totalTax + Number(otherCharges || 0)).toFixed(2));

  // Save Draft (does NOT alter physical stock)
  const handleSaveDraft = async () => {
    if (items.length === 0) {
      setErrorMsg('Please scan or add at least one product before saving.');
      return;
    }

    setIsSubmitting(true);
    setErrorMsg(null);
    try {
      const payload = {
        supplier_id: supplierId ? parseInt(supplierId) : null,
        supplier_name_snapshot: supplierNameSnapshot,
        supplier_invoice_number: supplierInvoiceNumber,
        invoice_date: invoiceDate,
        purchase_reference: purchaseReference,
        receiving_date: receivingDate,
        status: 'draft',
        discount: Number(discount || 0),
        other_charges: Number(otherCharges || 0),
        payment_status: paymentStatus,
        notes: notes,
        items: items.map(it => ({
          product_id: it.product_id,
          barcode: it.barcode,
          quantity_expected: it.quantity_expected,
          quantity_received: it.quantity_received,
          quantity_damaged: it.quantity_damaged,
          unit_cost: it.unit_cost,
          discount: it.discount,
          tax_amount: it.tax_amount,
          expiry_date: it.expiry_date || null,
          batch_number: it.batch_number || null,
          notes: it.notes || null,
        })),
      };

      let res;
      if (currentReceiptId) {
        res = await adminApi.updateReceiving(currentReceiptId, payload);
      } else {
        res = await adminApi.createReceiving(payload);
      }

      if (res?.data) {
        setCurrentReceiptId(res.data.id);
        setGrnNumber(res.data.grn_number);
        setStatus('draft');
        setFeedbackMsg(`Draft ${res.data.grn_number} saved successfully. Physical stock remains untouched.`);
        loadKPIs();
        loadHistory();
      }
    } catch (e) {
      setErrorMsg(e.response?.data?.message || e.message || 'Failed to save draft');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Confirm Receipt (Atomic Database Transaction)
  const handleConfirmReceipt = async () => {
    if (items.length === 0) {
      setErrorMsg('Cannot confirm receipt with empty items list.');
      return;
    }

    setIsSubmitting(true);
    setErrorMsg(null);
    try {
      // First save draft if not saved
      let activeReceiptId = currentReceiptId;
      if (!activeReceiptId) {
        const payload = {
          supplier_id: supplierId ? parseInt(supplierId) : null,
          supplier_name_snapshot: supplierNameSnapshot,
          supplier_invoice_number: supplierInvoiceNumber,
          invoice_date: invoiceDate,
          purchase_reference: purchaseReference,
          receiving_date: receivingDate,
          status: 'draft',
          discount: Number(discount || 0),
          other_charges: Number(otherCharges || 0),
          payment_status: paymentStatus,
          notes: notes,
          items: items.map(it => ({
            product_id: it.product_id,
            barcode: it.barcode,
            quantity_expected: it.quantity_expected,
            quantity_received: it.quantity_received,
            quantity_damaged: it.quantity_damaged,
            unit_cost: it.unit_cost,
            discount: it.discount,
            tax_amount: it.tax_amount,
            expiry_date: it.expiry_date || null,
            batch_number: it.batch_number || null,
            notes: it.notes || null,
          })),
        };
        const draftRes = await adminApi.createReceiving(payload);
        activeReceiptId = draftRes.data.id;
        setCurrentReceiptId(activeReceiptId);
        setGrnNumber(draftRes.data.grn_number);
      }

      // Execute atomic confirmation
      const res = await adminApi.confirmReceiving(activeReceiptId);
      if (res?.data) {
        setStatus('received');
        setShowConfirmModal(false);
        setPrintReceiptData(res.data);
        setFeedbackMsg(`GRN ${res.data.grn_number} CONFIRMED! +${totalUnitsSellable} units committed to physical inventory.`);
        loadKPIs();
        loadHistory();
      }
    } catch (e) {
      setErrorMsg(e.response?.data?.message || e.message || 'Confirmation failed');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Reset Receiving Session
  const handleNewReceipt = () => {
    setCurrentReceiptId(null);
    setGrnNumber('GRN-NEW');
    setStatus('draft');
    setSupplierId('');
    setSupplierNameSnapshot('');
    setSelectedSupplierObj(null);
    setSupplierInvoiceNumber('');
    setInvoiceDate(new Date().toISOString().split('T')[0]);
    setPurchaseReference('');
    setReceivingDate(new Date().toISOString().split('T')[0]);
    setDiscount(0);
    setOtherCharges(0);
    setPaymentStatus('unpaid');
    setNotes('');
    setItems([]);
    setFeedbackMsg(null);
    setErrorMsg(null);
    setViewMode('station');
    if (scannerInputRef.current) {
      scannerInputRef.current.focus();
    }
  };

  // Edit an existing Draft GRN from history
  const handleEditDraft = (receipt) => {
    setCurrentReceiptId(receipt.id);
    setGrnNumber(receipt.grn_number);
    setStatus(receipt.status);
    setSupplierId(receipt.supplier_id ? String(receipt.supplier_id) : '');
    setSupplierNameSnapshot(receipt.supplier_name_snapshot || '');
    const supp = suppliers.find(s => s.id === receipt.supplier_id);
    setSelectedSupplierObj(supp || null);
    setSupplierInvoiceNumber(receipt.supplier_invoice_number || '');
    setInvoiceDate(receipt.invoice_date || new Date().toISOString().split('T')[0]);
    setPurchaseReference(receipt.purchase_reference || '');
    setReceivingDate(receipt.receiving_date || new Date().toISOString().split('T')[0]);
    setDiscount(receipt.discount || 0);
    setOtherCharges(receipt.other_charges || 0);
    setPaymentStatus(receipt.payment_status || 'unpaid');
    setNotes(receipt.notes || '');

    // Map items
    const mapped = (receipt.items || []).map(it => ({
      product_id: it.product_id,
      barcode: it.barcode,
      product_name: it.product_name || it.product?.name,
      sku: it.product?.sku || `SKU-${it.product_id}`,
      unit: it.product?.unit || 'piece',
      quantity_expected: it.quantity_expected || 1,
      quantity_received: it.quantity_received || 1,
      quantity_damaged: it.quantity_damaged || 0,
      quantity_sellable: it.quantity_sellable || 1,
      unit_cost: Number(it.unit_cost || 0),
      discount: Number(it.discount || 0),
      tax_amount: Number(it.tax_amount || 0),
      subtotal: Number(it.subtotal || 0),
      expiry_date: it.expiry_date || '',
      batch_number: it.batch_number || '',
      notes: it.notes || '',
    }));
    setItems(mapped);
    setViewMode('station');
  };

  // Create Supplier modal submission
  const handleSaveSupplier = async (e) => {
    e.preventDefault();
    try {
      const res = await adminApi.createSupplier(newSupplierForm);
      if (res?.data) {
        setSuppliers(prev => [...prev, res.data]);
        setSupplierId(String(res.data.id));
        setSelectedSupplierObj(res.data);
        setSupplierNameSnapshot(res.data.name);
        setShowAddSupplierModal(false);
        setNewSupplierForm({ name: '', phone: '', email: '', address: '', tax_number: '' });
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to create supplier');
    }
  };

  // Quick Create Product for unknown barcode
  const handleSaveUnknownProduct = async (e) => {
    e.preventDefault();
    try {
      const payload = {
        barcode: unknownBarcode,
        name: newProductForm.name,
        unit: newProductForm.unit || 'piece',
        wholesale_cost: parseFloat(newProductForm.wholesale_cost) || 0,
        retail_price: parseFloat(newProductForm.retail_price) || 0,
        supplier_name: supplierNameSnapshot || null,
      };

      const res = await adminApi.quickCreateProduct(payload);
      if (res?.data) {
        const prod = res.data;
        barcodeCache.current.set(prod.barcode, prod);

        // Add to current receiving items
        const unitCost = prod.wholesale_cost;
        const taxAmt = Number((unitCost * 0.05).toFixed(2));
        const newItem = {
          product_id: prod.id,
          barcode: prod.barcode,
          product_name: prod.name,
          sku: prod.sku,
          unit: prod.unit,
          quantity_expected: 1,
          quantity_received: 1,
          quantity_damaged: 0,
          quantity_sellable: 1,
          unit_cost: unitCost,
          discount: 0,
          tax_amount: taxAmt,
          subtotal: Number((unitCost + taxAmt).toFixed(2)),
          expiry_date: '',
          batch_number: '',
          notes: '',
        };

        setItems(prev => [newItem, ...prev]);
        setShowUnknownModal(false);
        setNewProductForm({ name: '', unit: 'piece', wholesale_cost: 0, retail_price: 0 });
        playScanBeep(880, 0.1);
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to create product');
    }
  };

  // Supplier Return from completed GRN
  const handleSupplierReturn = async () => {
    if (!returnItem || returnQty <= 0 || !returnReason.trim()) {
      alert('Please specify quantity and reason for return.');
      return;
    }

    try {
      await adminApi.returnReceivingStock(viewingGrn.id, {
        product_id: returnItem.product_id,
        quantity: returnQty,
        reason: returnReason,
      });
      alert(`Successfully returned ${returnQty} units of ${returnItem.product_name}. Inventory updated.`);
      setShowReturnModal(false);
      setReturnItem(null);
      setReturnQty(1);
      setReturnReason('');
      loadKPIs();
      loadHistory();
    } catch (err) {
      alert(err.response?.data?.message || 'Return failed');
    }
  };

  // CSV Export of History
  const exportHistoryCSV = () => {
    if (receiptsList.length === 0) return;
    const headers = ['GRN Number', 'Supplier', 'Invoice #', 'Receiving Date', 'Items Count', 'Grand Total (AED)', 'Status', 'Created By', 'Confirmed At'];
    const rows = receiptsList.map(r => [
      r.grn_number,
      r.supplier_name_snapshot || r.supplier?.name || 'N/A',
      r.supplier_invoice_number || 'N/A',
      r.receiving_date,
      r.items?.length || 0,
      r.total_amount,
      r.status,
      r.created_by || 'Admin',
      r.confirmed_at || 'N/A',
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.map(val => `"${val}"`).join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Baqqala_GRN_History_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      
      {/* Top Bar / KPI Cards */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            Stock Receiving Station
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
              <Scan className="w-3.5 h-3.5 text-emerald-600 animate-pulse" />
              Datalogic QuickScan Lite
            </span>
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Authoritative goods receipt note (GRN) counter. Repeated physical scans automatically accumulate product quantities in place.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <button
            onClick={() => setViewMode(viewMode === 'station' ? 'history' : 'station')}
            className="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 flex items-center gap-2 shadow-xs"
          >
            <FileText className="w-4 h-4 text-slate-500" />
            {viewMode === 'station' ? 'View GRN History' : 'Back to Receiving Station'}
          </button>

          <button
            onClick={handleNewReceipt}
            className="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-2 shadow-md shadow-emerald-600/20"
          >
            <Plus className="w-4 h-4" />
            + New Stock Receipt
          </button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3.5">
        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Draft Receipts</div>
          <div className="text-2xl font-black text-slate-800 mt-1">{kpis.draft_count}</div>
          <div className="text-[10px] text-slate-400 mt-0.5">Uncommitted stock</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="text-[11px] font-bold uppercase tracking-wider text-amber-500">Pending Review</div>
          <div className="text-2xl font-black text-amber-600 mt-1">{kpis.pending_count}</div>
          <div className="text-[10px] text-slate-400 mt-0.5">Awaiting manager check</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Received Today</div>
          <div className="text-2xl font-black text-emerald-700 mt-1">{kpis.received_today_count}</div>
          <div className="text-[10px] text-emerald-600 font-semibold mt-0.5">AED {kpis.received_today_value.toFixed(2)}</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
          <div className="text-[11px] font-bold uppercase tracking-wider text-indigo-600">This Month</div>
          <div className="text-2xl font-black text-indigo-700 mt-1">{kpis.month_receipts_count}</div>
          <div className="text-[10px] text-indigo-600 font-semibold mt-0.5">Completed intake</div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs col-span-2 md:col-span-1">
          <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Month Purchase Value</div>
          <div className="text-2xl font-black text-slate-900 mt-1 font-mono">AED {kpis.month_total_value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          <div className="text-[10px] text-slate-400 mt-0.5">Total wholesale value</div>
        </div>
      </div>

      {/* Feedback Alerts */}
      {feedbackMsg && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CheckCircle className="w-4 h-4 text-emerald-600" />
            {feedbackMsg}
          </div>
          <button onClick={() => setFeedbackMsg(null)} className="text-emerald-600 hover:text-emerald-900">&times;</button>
        </div>
      )}

      {errorMsg && (
        <div className="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-semibold flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-rose-600" />
            {errorMsg}
          </div>
          <button onClick={() => setErrorMsg(null)} className="text-rose-600 hover:text-rose-900">&times;</button>
        </div>
      )}

      {/* VIEW MODE 1: RECEIVING STATION */}
      {viewMode === 'station' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">

          {/* LEFT 8 COLS: Scanner Station & Items Table */}
          <div className="lg:col-span-8 space-y-5">

            {/* Datalogic QuickScan Lite Capture Bar */}
            <div className="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-5 shadow-lg border border-slate-700">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2.5">
                  <span className="relative flex h-3 w-3">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                  </span>
                  <span className="font-extrabold text-sm tracking-wide text-emerald-400 uppercase">
                    ⚡ Scanner Ready (USB Keyboard)
                  </span>
                </div>

                <div className="flex items-center gap-3 text-xs">
                  <button
                    onClick={() => setRapidScanMode(!rapidScanMode)}
                    className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all ${rapidScanMode ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-700 text-slate-300'}`}
                  >
                    ⚡ Rapid Scan Mode: {rapidScanMode ? 'ON' : 'OFF'}
                  </button>
                  <button
                    onClick={() => setShowScannerTest(!showScannerTest)}
                    className="text-slate-400 hover:text-white underline text-[11px]"
                  >
                    Test Scanner
                  </button>
                  <button
                    onClick={() => setShowScannerHelp(true)}
                    className="text-slate-400 hover:text-white"
                    title="How to configure Datalogic QuickScan Lite"
                  >
                    <HelpCircle className="w-4 h-4" />
                  </button>
                </div>
              </div>

              {/* Dedicated Scanner Input */}
              <div className="relative">
                <input
                  id="datalogicScannerInput"
                  ref={scannerInputRef}
                  type="text"
                  value={scannerInput}
                  onChange={(e) => setScannerInput(e.target.value)}
                  onKeyDown={handleScannerKeyDown}
                  placeholder="Scan product barcode (Datalogic QuickScan sends barcode + ENTER)..."
                  className="w-full bg-slate-950/80 border-2 border-emerald-500/60 rounded-xl px-4 py-3.5 pl-11 text-white font-mono font-bold text-base focus:outline-none focus:border-emerald-400 placeholder:text-slate-500 shadow-inner"
                  autoFocus
                />
                <Scan className="w-5 h-5 text-emerald-400 absolute left-3.5 top-4" />
              </div>

              {/* Status & Last Scan Bar */}
              <div className="flex items-center justify-between text-[11px] text-slate-400 mt-2.5 px-1 font-mono">
                <div>
                  Status: <span className={scannerStatus === 'FOUND' ? 'text-emerald-400 font-bold' : (scannerStatus === 'NOT_FOUND' ? 'text-rose-400 font-bold' : 'text-slate-300')}>{scannerStatus}</span>
                  {lastScannedBarcode && (
                    <span className="ml-3">Last Barcode: <span className="text-white font-bold">{lastScannedBarcode}</span> at {lastScanTime}</span>
                  )}
                </div>
                <div className="text-slate-500">
                  Multiple scans of same item increment quantity (+1) automatically
                </div>
              </div>

              {/* Scanner Diagnostic Box */}
              {showScannerTest && (
                <div className="mt-3 p-3 bg-slate-950 rounded-xl border border-slate-700 text-xs font-mono space-y-1 text-slate-300">
                  <div className="text-emerald-400 font-bold">Datalogic QuickScan Lite Diagnostic Tool:</div>
                  <div>Interface: USB Keyboard (HID Wedge) • Terminator: ENTER</div>
                  <div>Current Session Barcodes Cached: {barcodeCache.current.size} items</div>
                  <div className="text-[10px] text-slate-500">Tip: If scanner does not emit ENTER, scan the "Enter Suffix" barcode from the QuickScan reference sheet.</div>
                </div>
              )}
            </div>

            {/* Scanned Items Table */}
            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
              <div className="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                  <h2 className="font-bold text-base text-slate-900 flex items-center gap-2">
                    Received Goods Table
                    <span className="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 font-mono">
                      {items.length} products
                    </span>
                  </h2>
                </div>

                <div className="text-xs text-slate-500 font-medium">
                  Total Units: <span className="font-mono font-bold text-slate-900">{totalUnitsReceived}</span> (Sellable: <span className="font-mono font-bold text-emerald-600">{totalUnitsSellable}</span>)
                </div>
              </div>

              {items.length === 0 ? (
                <div className="p-12 text-center text-slate-400 space-y-3">
                  <Scan className="w-12 h-12 text-slate-300 mx-auto stroke-1 animate-bounce" />
                  <div className="font-semibold text-sm text-slate-600">Receiving Counter Empty</div>
                  <p className="text-xs max-w-sm mx-auto text-slate-400">
                    Scan goods with your Datalogic scanner or enter a barcode manually. Products will appear with unit purchase costs and batch/expiry tracking.
                  </p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-slate-50/80 text-slate-500 font-bold border-b border-slate-200/60 uppercase tracking-wider text-[10px]">
                      <tr>
                        <th className="py-3 px-4">Item & Barcode</th>
                        <th className="py-3 px-3 text-center w-24">Received Qty</th>
                        <th className="py-3 px-3 text-center w-20">Damaged</th>
                        <th className="py-3 px-3 text-right w-28">Unit Cost (AED)</th>
                        <th className="py-3 px-3 text-right w-20">Tax (5%)</th>
                        <th className="py-3 px-3 text-right w-28">Total (AED)</th>
                        <th className="py-3 px-3 text-center w-32">Expiry Date</th>
                        <th className="py-3 px-3 text-center w-8"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 font-medium">
                      {items.map((item, idx) => {
                        const isFlash = scanFlashId === item.product_id;
                        return (
                          <tr key={idx} className={`transition-colors ${isFlash ? 'bg-emerald-100/60' : 'hover:bg-slate-50/60'}`}>
                            
                            {/* Product Info */}
                            <td className="py-3.5 px-4">
                              <div className="font-extrabold text-slate-900 text-sm truncate max-w-[200px]">{item.product_name}</div>
                              <div className="text-[11px] font-mono text-slate-400 flex items-center gap-2 mt-0.5">
                                <span>{item.barcode}</span>
                                <span>•</span>
                                <span className="uppercase text-[10px] text-slate-500 font-semibold">{item.unit}</span>
                              </div>
                            </td>

                            {/* Received Qty */}
                            <td className="py-3 px-3 text-center">
                              <input
                                type="number"
                                min="1"
                                value={item.quantity_received}
                                onChange={(e) => handleItemChange(idx, 'quantity_received', e.target.value)}
                                className="w-16 px-2 py-1.5 text-center font-mono font-black text-sm bg-emerald-50/50 border border-emerald-300 rounded-lg text-emerald-900 focus:outline-none focus:border-emerald-500"
                              />
                            </td>

                            {/* Damaged on Arrival */}
                            <td className="py-3 px-3 text-center">
                              <input
                                type="number"
                                min="0"
                                value={item.quantity_damaged || 0}
                                onChange={(e) => handleItemChange(idx, 'quantity_damaged', e.target.value)}
                                className="w-14 px-2 py-1.5 text-center font-mono font-bold text-xs bg-slate-50 border border-slate-200 rounded-lg text-rose-600 focus:outline-none focus:border-rose-400"
                              />
                            </td>

                            {/* Unit Cost */}
                            <td className="py-3 px-3 text-right">
                              <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={item.unit_cost}
                                onChange={(e) => handleItemChange(idx, 'unit_cost', e.target.value)}
                                className="w-24 px-2 py-1.5 text-right font-mono font-bold text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:outline-none focus:border-emerald-500"
                              />
                            </td>

                            {/* Tax Amount */}
                            <td className="py-3 px-3 text-right font-mono text-slate-500 text-xs">
                              {item.tax_amount ? item.tax_amount.toFixed(2) : '0.00'}
                            </td>

                            {/* Line Subtotal */}
                            <td className="py-3 px-3 text-right font-mono font-extrabold text-slate-900 text-sm">
                              {item.subtotal ? item.subtotal.toFixed(2) : '0.00'}
                            </td>

                            {/* Expiry Date */}
                            <td className="py-3 px-3 text-center">
                              <input
                                type="date"
                                value={item.expiry_date || ''}
                                onChange={(e) => handleItemChange(idx, 'expiry_date', e.target.value)}
                                className="w-28 px-1.5 py-1 text-center font-mono text-[11px] bg-slate-50 border border-slate-200 rounded-lg text-slate-700"
                              />
                            </td>

                            {/* Actions */}
                            <td className="py-3 px-3 text-center">
                              <button
                                onClick={() => handleRemoveItem(idx)}
                                className="text-slate-400 hover:text-rose-600 p-1 rounded-md hover:bg-rose-50 transition-colors"
                                title="Remove line"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </td>

                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

          </div>

          {/* RIGHT 4 COLS: Header, Supplier & Totals Summary */}
          <div className="lg:col-span-4 space-y-5">
            
            {/* Receiving Header Card */}
            <div className="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                  <div className="text-[10px] uppercase font-bold tracking-wider text-slate-400">GRN Reference</div>
                  <div className="font-mono font-black text-lg text-slate-900">{grnNumber}</div>
                </div>

                <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold uppercase ${status === 'received' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'}`}>
                  {status}
                </span>
              </div>

              {/* Supplier Selection */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-slate-700">Supplier *</label>
                  <button
                    onClick={() => setShowAddSupplierModal(true)}
                    className="text-xs font-bold text-emerald-600 hover:text-emerald-700"
                  >
                    + Add Supplier
                  </button>
                </div>

                <select
                  value={supplierId}
                  onChange={(e) => handleSelectSupplier(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:border-emerald-500"
                >
                  <option value="">-- Select Registered Supplier --</option>
                  {suppliers.map(s => (
                    <option key={s.id} value={s.id}>
                      {s.name} {s.phone ? `(${s.phone})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Informational Supplier Card */}
              {selectedSupplierObj && (
                <div className="p-3 bg-emerald-50/50 border border-emerald-200/60 rounded-xl text-xs space-y-1 text-slate-600">
                  <div className="font-bold text-emerald-900">{selectedSupplierObj.name}</div>
                  {selectedSupplierObj.phone && <div className="text-[11px]">Phone: {selectedSupplierObj.phone}</div>}
                  {selectedSupplierObj.tax_number && <div className="text-[11px] font-mono">TRN: {selectedSupplierObj.tax_number}</div>}
                  <div className="text-[10px] text-slate-500 pt-1 flex justify-between border-t border-emerald-100">
                    <span>Past Receipts: {selectedSupplierObj.purchase_count}</span>
                    <span>Last: {selectedSupplierObj.last_purchase_date || 'N/A'}</span>
                  </div>
                </div>
              )}

              {/* Invoice Number & Date */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">Supplier Invoice #</label>
                  <input
                    type="text"
                    value={supplierInvoiceNumber}
                    onChange={(e) => setSupplierInvoiceNumber(e.target.value)}
                    placeholder="e.g. INV-9843"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                  />
                  {duplicateInvoiceWarning && (
                    <div className="text-[10px] font-bold text-amber-600 mt-1 flex items-center gap-1">
                      <AlertTriangle className="w-3 h-3" /> Possible duplicate invoice!
                    </div>
                  )}
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">Invoice Date</label>
                  <input
                    type="date"
                    value={invoiceDate}
                    onChange={(e) => setInvoiceDate(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              {/* Purchase Ref & Receiving Date */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">PO / Reference</label>
                  <input
                    type="text"
                    value={purchaseReference}
                    onChange={(e) => setPurchaseReference(e.target.value)}
                    placeholder="e.g. PO-102"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">Receiving Date</label>
                  <input
                    type="date"
                    value={receivingDate}
                    onChange={(e) => setReceivingDate(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              {/* Payment Status */}
              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">Payment Status</label>
                <select
                  value={paymentStatus}
                  onChange={(e) => setPaymentStatus(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                >
                  <option value="unpaid">Unpaid (Supplier Credit)</option>
                  <option value="partially_paid">Partially Paid</option>
                  <option value="paid">Paid</option>
                </select>
              </div>

              {/* Notes */}
              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">Receiving Notes</label>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="e.g. 2 cartons damaged on delivery, short delivery of 3 items..."
                  rows={2}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>

            {/* Totals & Grand Summary Card */}
            <div className="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
              <h3 className="font-extrabold text-sm text-slate-900 border-b border-slate-100 pb-2">
                Receipt Totals Summary
              </h3>

              <div className="space-y-2 text-xs font-medium text-slate-600">
                <div className="flex justify-between">
                  <span>Gross Subtotal:</span>
                  <span className="font-mono font-bold text-slate-900">AED {subtotal.toFixed(2)}</span>
                </div>

                <div className="flex justify-between">
                  <span>Line Discounts:</span>
                  <span className="font-mono text-rose-600">- AED {totalLineDiscount.toFixed(2)}</span>
                </div>

                <div className="flex justify-between items-center">
                  <span>Supplier Discount:</span>
                  <input
                    type="number"
                    min="0"
                    step="0.5"
                    value={discount}
                    onChange={(e) => setDiscount(e.target.value)}
                    className="w-20 px-2 py-1 text-right font-mono text-xs bg-slate-50 border border-slate-200 rounded-md"
                  />
                </div>

                <div className="flex justify-between">
                  <span>VAT / Tax (5%):</span>
                  <span className="font-mono font-bold text-slate-900">AED {totalTax.toFixed(2)}</span>
                </div>

                <div className="flex justify-between items-center">
                  <span>Other / Freight:</span>
                  <input
                    type="number"
                    min="0"
                    step="1"
                    value={otherCharges}
                    onChange={(e) => setOtherCharges(e.target.value)}
                    className="w-20 px-2 py-1 text-right font-mono text-xs bg-slate-50 border border-slate-200 rounded-md"
                  />
                </div>

                <div className="pt-3 border-t border-slate-200 flex justify-between items-baseline">
                  <span className="font-black text-sm text-slate-900">Grand Total:</span>
                  <span className="font-mono font-black text-xl text-emerald-600">AED {grandTotal.toFixed(2)}</span>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="pt-3 space-y-2.5">
                {status !== 'received' ? (
                  <>
                    <button
                      onClick={() => setShowConfirmModal(true)}
                      disabled={isSubmitting || items.length === 0}
                      className="w-full py-3.5 bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 font-black text-xs uppercase tracking-wider rounded-xl shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 transition-all"
                    >
                      <CheckCircle className="w-4 h-4" />
                      {isSubmitting ? 'Confirming...' : 'Confirm Stock Receipt & Update Stock'}
                    </button>

                    <button
                      onClick={handleSaveDraft}
                      disabled={isSubmitting || items.length === 0}
                      className="w-full py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 disabled:opacity-50 font-bold text-xs rounded-xl transition-all"
                    >
                      Save as Draft (No Stock Change)
                    </button>
                  </>
                ) : (
                  <div className="space-y-2">
                    <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-center text-xs font-bold text-emerald-800">
                      ✓ RECEIPT CONFIRMED & COMMITTED
                    </div>
                    <button
                      onClick={() => {
                        setPrintReceiptData({
                          grn_number: grnNumber,
                          supplier_name_snapshot: supplierNameSnapshot,
                          supplier_invoice_number: supplierInvoiceNumber,
                          receiving_date: receivingDate,
                          subtotal,
                          discount: Number(discount) + totalLineDiscount,
                          tax_amount: totalTax,
                          total_amount: grandTotal,
                          items,
                          created_by: 'Admin',
                        });
                        setShowPrintModal(true);
                      }}
                      className="w-full py-2.5 bg-slate-900 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-2"
                    >
                      <Printer className="w-4 h-4" /> Print GRN Document
                    </button>
                  </div>
                )}
              </div>

            </div>

          </div>

        </div>
      )}

      {/* VIEW MODE 2: GRN HISTORY & SEARCH */}
      {viewMode === 'history' && (
        <div className="space-y-4">
          
          {/* Filter Bar */}
          <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-3 w-full md:w-auto">
              <div className="relative flex-1 md:w-80">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                <input
                  type="text"
                  value={historySearch}
                  onChange={(e) => setHistorySearch(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && loadHistory()}
                  placeholder="Search GRN #, supplier, invoice #..."
                  className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500"
                />
              </div>

              <select
                value={historyStatusFilter}
                onChange={(e) => setHistoryStatusFilter(e.target.value)}
                className="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none"
              >
                <option value="">All Statuses</option>
                <option value="received">Received</option>
                <option value="draft">Draft</option>
                <option value="cancelled">Cancelled</option>
              </select>

              <button
                onClick={loadHistory}
                className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold flex items-center gap-1.5"
              >
                <RefreshCw className="w-3.5 h-3.5" /> Filter
              </button>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={exportHistoryCSV}
                className="px-3 py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 flex items-center gap-1.5"
              >
                <Download className="w-3.5 h-3.5 text-slate-500" /> Export CSV
              </button>
            </div>
          </div>

          {/* History Table */}
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            {receiptsLoading ? (
              <div className="p-12 text-center text-slate-400 font-medium text-xs">
                Loading receiving ledger records...
              </div>
            ) : receiptsList.length === 0 ? (
              <div className="p-12 text-center text-slate-400 text-xs">
                No receiving records match the search filter.
              </div>
            ) : (
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/60 uppercase tracking-wider text-[10px]">
                  <tr>
                    <th className="py-3 px-4">GRN Number</th>
                    <th className="py-3 px-4">Supplier</th>
                    <th className="py-3 px-3">Invoice #</th>
                    <th className="py-3 px-3">Date</th>
                    <th className="py-3 px-3 text-center">Items</th>
                    <th className="py-3 px-4 text-right">Total Amount</th>
                    <th className="py-3 px-3 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-medium">
                  {receiptsList.map(r => (
                    <tr key={r.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="py-3.5 px-4 font-mono font-black text-slate-900">{r.grn_number}</td>
                      <td className="py-3.5 px-4 font-bold text-slate-800">{r.supplier_name_snapshot || r.supplier?.name || 'Local Supplier'}</td>
                      <td className="py-3.5 px-3 font-mono text-slate-600">{r.supplier_invoice_number || '—'}</td>
                      <td className="py-3.5 px-3 font-mono text-slate-500">{r.receiving_date}</td>
                      <td className="py-3.5 px-3 text-center font-mono font-bold text-slate-700">{r.items?.length || 0}</td>
                      <td className="py-3.5 px-4 text-right font-mono font-extrabold text-slate-900">AED {Number(r.total_amount).toFixed(2)}</td>
                      <td className="py-3.5 px-3 text-center">
                        <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${r.status === 'received' ? 'bg-emerald-100 text-emerald-800' : (r.status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')}`}>
                          {r.status}
                        </span>
                      </td>
                      <td className="py-3.5 px-4 text-right space-x-2">
                        {r.status === 'draft' ? (
                          <button
                            onClick={() => handleEditDraft(r)}
                            className="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold"
                          >
                            Continue Draft
                          </button>
                        ) : (
                          <>
                            <button
                              onClick={() => {
                                setViewingGrn(r);
                              }}
                              className="text-slate-600 hover:text-slate-900 font-semibold text-xs"
                            >
                              View
                            </button>
                            <button
                              onClick={() => {
                                setPrintReceiptData(r);
                                setShowPrintModal(true);
                              }}
                              className="text-emerald-700 hover:text-emerald-900 font-semibold text-xs"
                            >
                              Print GRN
                            </button>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

        </div>
      )}

      {/* CONFIRMATION REVIEW MODAL */}
      {showConfirmModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-black text-lg text-slate-900 flex items-center gap-2">
                <ShieldCheck className="w-5 h-5 text-emerald-600" />
                Confirm Goods Receipt
              </h3>
              <button onClick={() => setShowConfirmModal(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div className="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 space-y-1">
              <div className="font-bold flex items-center gap-1.5">
                <AlertTriangle className="w-4 h-4 text-amber-600" />
                Inventory Increase Notice
              </div>
              <p>
                Confirming this receipt will add <strong className="font-mono text-amber-900">{totalUnitsSellable} sellable units</strong> across {totalItemsCount} products to physical stock, recalculate weighted-average cost, and record immutable ledger entries.
              </p>
            </div>

            <div className="p-4 bg-slate-50 rounded-2xl space-y-2 text-xs font-mono text-slate-700">
              <div className="flex justify-between"><span>Supplier:</span> <strong className="text-slate-900">{supplierNameSnapshot || 'Not selected'}</strong></div>
              <div className="flex justify-between"><span>Invoice #:</span> <span>{supplierInvoiceNumber || 'None'}</span></div>
              <div className="flex justify-between"><span>Total Items:</span> <span>{totalItemsCount} lines</span></div>
              <div className="flex justify-between"><span>Total Received:</span> <span>{totalUnitsReceived} units</span></div>
              {totalUnitsDamaged > 0 && <div className="flex justify-between text-rose-600"><span>Damaged on Arrival:</span> <span>-{totalUnitsDamaged} units</span></div>}
              <div className="flex justify-between font-bold text-emerald-700 pt-2 border-t border-slate-200 text-sm">
                <span>Grand Total:</span>
                <span>AED {grandTotal.toFixed(2)}</span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowConfirmModal(false)}
                className="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50"
              >
                Back to Edit
              </button>

              <button
                type="button"
                onClick={handleConfirmReceipt}
                disabled={isSubmitting}
                className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider shadow-md shadow-emerald-600/20"
              >
                {isSubmitting ? 'Confirming...' : 'Yes, Commit Stock to Ledger'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: UNKNOWN BARCODE CREATION */}
      {showUnknownModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-extrabold text-slate-900 text-base">Unknown Barcode Scanned</h3>
                <p className="text-xs text-slate-500">Create new product master or scan again.</p>
              </div>
              <button onClick={() => setShowUnknownModal(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form onSubmit={handleSaveUnknownProduct} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Scanned Barcode</label>
                <input
                  type="text"
                  value={unknownBarcode}
                  readOnly
                  className="w-full px-3 py-2 bg-amber-50 border border-amber-300 rounded-xl font-mono font-bold text-amber-900"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Product Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Al Rawabi Full Cream Milk 1L"
                  value={newProductForm.name}
                  onChange={(e) => setNewProductForm({ ...newProductForm, name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Wholesale Cost (AED) *</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={newProductForm.wholesale_cost}
                    onChange={(e) => setNewProductForm({ ...newProductForm, wholesale_cost: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Retail Price (AED) *</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={newProductForm.retail_price}
                    onChange={(e) => setNewProductForm({ ...newProductForm, retail_price: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowUnknownModal(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700"
                >
                  Create & Add to Receipt
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: QUICK ADD SUPPLIER */}
      {showAddSupplierModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-extrabold text-slate-900 text-base">Add New Supplier</h3>
              <button onClick={() => setShowAddSupplierModal(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form onSubmit={handleSaveSupplier} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Supplier Company Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Al Marai Distribution UAE"
                  value={newSupplierForm.name}
                  onChange={(e) => setNewSupplierForm({ ...newSupplierForm, name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Phone Number</label>
                  <input
                    type="text"
                    placeholder="+971 4 000 0000"
                    value={newSupplierForm.phone}
                    onChange={(e) => setNewSupplierForm({ ...newSupplierForm, phone: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">TRN / Tax Number</label>
                  <input
                    type="text"
                    placeholder="TRN-100xxxxxxx"
                    value={newSupplierForm.tax_number}
                    onChange={(e) => setNewSupplierForm({ ...newSupplierForm, tax_number: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Address / Location</label>
                <input
                  type="text"
                  placeholder="e.g. Dubai Industrial City, UAE"
                  value={newSupplierForm.address}
                  onChange={(e) => setNewSupplierForm({ ...newSupplierForm, address: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowAddSupplierModal(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700"
                >
                  Save Supplier
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: PRINTABLE GOODS RECEIVED NOTE (GRN) */}
      {showPrintModal && printReceiptData && (
        <div className="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-8 shadow-2xl border border-slate-200 space-y-6 max-h-[90vh] overflow-y-auto">
            
            {/* Header */}
            <div className="flex items-start justify-between border-b-2 border-slate-900 pb-5">
              <div>
                <h1 className="text-2xl font-black text-slate-900 tracking-tight">BAQQALA GROCERY</h1>
                <p className="text-xs text-slate-500 font-semibold uppercase tracking-widest mt-0.5">Goods Received Note (GRN)</p>
                <p className="text-[11px] text-slate-400">Dubai, United Arab Emirates • TRN: 100293847500003</p>
              </div>

              <div className="text-right">
                <div className="text-xs text-slate-400 font-bold uppercase">GRN Number</div>
                <div className="text-xl font-mono font-black text-slate-900">{printReceiptData.grn_number}</div>
                <div className="text-xs text-slate-500 mt-1 font-mono">Date: {printReceiptData.receiving_date}</div>
              </div>
            </div>

            {/* Supplier & Delivery Info */}
            <div className="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-2xl text-xs font-mono">
              <div>
                <div className="text-[10px] uppercase font-bold text-slate-400">Supplier:</div>
                <div className="font-bold text-slate-900 text-sm mt-0.5">{printReceiptData.supplier_name_snapshot || printReceiptData.supplier?.name || 'Local Supplier'}</div>
              </div>

              <div>
                <div className="text-[10px] uppercase font-bold text-slate-400">Supplier Invoice #:</div>
                <div className="font-bold text-slate-900 text-sm mt-0.5">{printReceiptData.supplier_invoice_number || 'N/A'}</div>
              </div>
            </div>

            {/* Items Table */}
            <table className="w-full text-left text-xs font-mono">
              <thead className="border-b border-slate-200 text-slate-500 uppercase text-[10px]">
                <tr>
                  <th className="py-2">Item Description</th>
                  <th className="py-2 text-center">Qty</th>
                  <th className="py-2 text-right">Unit Cost</th>
                  <th className="py-2 text-right">Total (AED)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {(printReceiptData.items || []).map((it, i) => (
                  <tr key={i}>
                    <td className="py-2">
                      <div className="font-bold text-slate-800">{it.product_name || it.product?.name}</div>
                      <div className="text-[10px] text-slate-400">{it.barcode}</div>
                    </td>
                    <td className="py-2 text-center font-bold">{it.quantity_received}</td>
                    <td className="py-2 text-right">AED {Number(it.unit_cost).toFixed(2)}</td>
                    <td className="py-2 text-right font-bold">AED {Number(it.subtotal).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Totals */}
            <div className="p-4 bg-slate-50 rounded-2xl text-xs font-mono space-y-1 text-slate-700">
              <div className="flex justify-between"><span>Subtotal:</span> <span>AED {Number(printReceiptData.subtotal).toFixed(2)}</span></div>
              <div className="flex justify-between"><span>Discount:</span> <span>- AED {Number(printReceiptData.discount || 0).toFixed(2)}</span></div>
              <div className="flex justify-between"><span>VAT / Tax (5%):</span> <span>AED {Number(printReceiptData.tax_amount || 0).toFixed(2)}</span></div>
              <div className="flex justify-between font-bold text-slate-900 text-sm pt-2 border-t border-slate-200">
                <span>Grand Total:</span>
                <span>AED {Number(printReceiptData.total_amount).toFixed(2)}</span>
              </div>
            </div>

            {/* Signatures */}
            <div className="grid grid-cols-2 gap-8 pt-6 border-t border-slate-200 text-xs font-mono text-center">
              <div>
                <div className="border-b border-slate-300 pb-8"></div>
                <div className="mt-2 text-slate-500">Delivered By (Supplier Rep)</div>
              </div>
              <div>
                <div className="border-b border-slate-300 pb-8"></div>
                <div className="mt-2 text-slate-500">Received & Inspected By (Baqqala Staff)</div>
              </div>
            </div>

            {/* Action Bar */}
            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
              <button
                onClick={() => setShowPrintModal(false)}
                className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs"
              >
                Close
              </button>
              <button
                onClick={() => window.print()}
                className="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center gap-2"
              >
                <Printer className="w-4 h-4" /> Print Document
              </button>
            </div>

          </div>
        </div>
      )}

      {/* MODAL: VIEW COMPLETED GRN DETAILS */}
      {viewingGrn && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <div className="text-[10px] font-bold uppercase text-slate-400">Completed Goods Receipt</div>
                <h3 className="font-mono font-black text-xl text-slate-900">{viewingGrn.grn_number}</h3>
              </div>
              <button onClick={() => setViewingGrn(null)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-2xl font-mono">
              <div><span>Supplier:</span> <strong className="text-slate-900 block">{viewingGrn.supplier_name_snapshot || viewingGrn.supplier?.name}</strong></div>
              <div><span>Invoice #:</span> <strong className="text-slate-900 block">{viewingGrn.supplier_invoice_number || 'N/A'}</strong></div>
              <div><span>Receiving Date:</span> <span className="block text-slate-700">{viewingGrn.receiving_date}</span></div>
              <div><span>Confirmed By:</span> <span className="block text-slate-700">{viewingGrn.confirmed_by || 'Admin'}</span></div>
            </div>

            <table className="w-full text-left text-xs font-medium">
              <thead className="border-b border-slate-100 text-slate-400 uppercase text-[10px]">
                <tr>
                  <th className="py-2">Item</th>
                  <th className="py-2 text-center">Received</th>
                  <th className="py-2 text-right">Cost (AED)</th>
                  <th className="py-2 text-right">Subtotal</th>
                  <th className="py-2 text-center">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {(viewingGrn.items || []).map((it, idx) => (
                  <tr key={idx}>
                    <td className="py-2">
                      <div className="font-bold text-slate-900">{it.product_name || it.product?.name}</div>
                      <div className="text-[10px] font-mono text-slate-400">{it.barcode}</div>
                    </td>
                    <td className="py-2 text-center font-mono font-bold text-emerald-700">+{it.quantity_received}</td>
                    <td className="py-2 text-right font-mono">AED {Number(it.unit_cost).toFixed(2)}</td>
                    <td className="py-2 text-right font-mono font-bold">AED {Number(it.subtotal).toFixed(2)}</td>
                    <td className="py-2 text-center">
                      <button
                        onClick={() => {
                          setReturnItem(it);
                          setShowReturnModal(true);
                        }}
                        className="text-[11px] font-bold text-rose-600 hover:underline"
                      >
                        Return Stock
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="flex justify-between items-center pt-3 border-t border-slate-100">
              <div className="font-mono text-sm font-black text-slate-900">
                Total Value: <span className="text-emerald-600">AED {Number(viewingGrn.total_amount).toFixed(2)}</span>
              </div>

              <button
                onClick={() => {
                  setPrintReceiptData(viewingGrn);
                  setShowPrintModal(true);
                }}
                className="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold flex items-center gap-2"
              >
                <Printer className="w-3.5 h-3.5" /> Print Note
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: SUPPLIER RETURN */}
      {showReturnModal && returnItem && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900">Record Supplier Return</h3>
              <button onClick={() => setShowReturnModal(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div>
              <label className="text-slate-500 block mb-1">Product:</label>
              <div className="font-bold text-slate-900">{returnItem.product_name}</div>
            </div>

            <div>
              <label className="text-slate-700 font-bold block mb-1">Quantity to Return (-)</label>
              <input
                type="number"
                min="1"
                max={returnItem.quantity_received}
                value={returnQty}
                onChange={(e) => setReturnQty(parseInt(e.target.value) || 1)}
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold"
              />
            </div>

            <div>
              <label className="text-slate-700 font-bold block mb-1">Reason for Return *</label>
              <input
                type="text"
                required
                placeholder="e.g. Damaged packaging, expired on delivery..."
                value={returnReason}
                onChange={(e) => setReturnReason(e.target.value)}
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
              />
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                onClick={() => setShowReturnModal(false)}
                className="px-3 py-2 rounded-xl border border-slate-200 font-bold text-slate-600"
              >
                Cancel
              </button>
              <button
                onClick={handleSupplierReturn}
                className="px-4 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700"
              >
                Confirm Return & Deduct Stock
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: DATALOGIC CONFIGURATION GUIDE */}
      {showScannerHelp && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-extrabold text-slate-900 text-base flex items-center gap-2">
                <Scan className="w-4 h-4 text-emerald-600" />
                Datalogic QuickScan Lite Setup
              </h3>
              <button onClick={() => setShowScannerHelp(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div className="space-y-2.5 text-slate-600 leading-relaxed">
              <p>To use the Datalogic QuickScan Lite with Baqqala Grocery:</p>
              <ol className="list-decimal list-inside space-y-1.5 pl-1 font-medium">
                <li>Plug scanner into computer via <strong>USB</strong>.</li>
                <li>Ensure scanner is in <strong>USB Keyboard / HID Wedge</strong> mode (standard default).</li>
                <li>Configure terminator suffix as <strong>ENTER (CR)</strong> from manual.</li>
                <li>Keep inter-character and inter-code delay at 0 (minimum).</li>
                <li>Click <strong>Scanner Ready</strong> or focus the scan input.</li>
                <li>Scan goods: repeated scans of identical items automatically increment quantity!</li>
              </ol>
            </div>

            <div className="pt-3 border-t border-slate-100 text-right">
              <button
                onClick={() => setShowScannerHelp(false)}
                className="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold"
              >
                Got It
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
