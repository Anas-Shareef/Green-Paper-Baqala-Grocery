import React, { useEffect, useState, useRef } from 'react';
import { 
  Warehouse, Plus, RefreshCw, AlertTriangle, ArrowUpRight, ArrowDownRight,
  Search, Upload, Download, Trash2, Edit2, CheckSquare, Square, Tags,
  Image, X, Check, CheckCircle, AlertCircle, Eye, ChevronRight, ChevronLeft, Layers,
  FileSpreadsheet, HelpCircle, Archive, ShieldCheck, History, Scale, FileText, CheckCircle2
} from 'lucide-react';
import { adminApi } from '../services/api';

export function InventoryPage() {
  // Navigation Tabs: 'products' | 'categories' | 'movements' | 'counts'
  const [activeTab, setActiveTab] = useState('products');

  // Products State
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [productsLoading, setProductsLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [stockStatusFilter, setStockStatusFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Pagination State (PRD Section 33)
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [totalProducts, setTotalProducts] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [fromItem, setFromItem] = useState(0);
  const [toItem, setToItem] = useState(0);

  // Product Stock Ledger Drawer (PRD Section 35)
  const [ledgerProduct, setLedgerProduct] = useState(null);
  const [ledgerMovements, setLedgerMovements] = useState([]);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [ledgerPage, setLedgerPage] = useState(1);
  const [ledgerTotalPages, setLedgerTotalPages] = useState(1);
  const [ledgerTotal, setLedgerTotal] = useState(0);

  // Stock Reconciliation Diagnostic Tool (PRD Section 62)
  const [reconcileModalOpen, setReconcileModalOpen] = useState(false);
  const [reconcileLoading, setReconcileLoading] = useState(false);
  const [reconcileReport, setReconcileReport] = useState(null);
  const [correctingProduct, setCorrectingProduct] = useState(null);
  const [correctionTarget, setCorrectionTarget] = useState('');
  const [correctionReason, setCorrectionReason] = useState('');
  const [correctionSubmitting, setCorrectionSubmitting] = useState(false);

  // Row Selection for Bulk Actions
  const [selectedProductIds, setSelectedProductIds] = useState([]);

  // Product Modals
  const [productModalOpen, setProductModalOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);
  const [productForm, setProductForm] = useState({
    name: '',
    category_id: '',
    sku: '',
    barcode: '',
    unit: 'piece',
    wholesale_cost: '',
    retail_price: '',
    stock_quantity: '0',
    minimum_stock_level: '5',
    description: '',
    status: 'active',
    image_url: '',
    image_file: null,
    remove_image: false,
  });
  const [productSubmitting, setProductSubmitting] = useState(false);
  const [imagePreview, setImagePreview] = useState(null);
  const [enlargedImage, setEnlargedImage] = useState(null);

  // Bulk Action Modals
  const [showBulkDeleteModal, setShowBulkDeleteModal] = useState(false);
  const [showBulkCategoryModal, setShowBulkCategoryModal] = useState(false);
  const [bulkTargetCategory, setBulkTargetCategory] = useState('');
  const [showBulkStatusModal, setShowBulkStatusModal] = useState(false);
  const [bulkTargetStatus, setBulkTargetStatus] = useState('active');
  const [bulkProcessing, setBulkProcessing] = useState(false);

  // Category State & Modals
  const [categoryModalOpen, setCategoryModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const [categoryForm, setCategoryForm] = useState({
    name: '',
    description: '',
    sort_order: 0,
    status: 'active',
    image_url: '',
    image_file: null,
    remove_image: false,
  });
  const [categoryImagePreview, setCategoryImagePreview] = useState(null);
  const [categorySubmitting, setCategorySubmitting] = useState(false);
  const [categoryDeleteModalOpen, setCategoryDeleteModalOpen] = useState(false);
  const [categoryToDelete, setCategoryToDelete] = useState(null);
  const [reassignCategoryTarget, setReassignCategoryTarget] = useState('');

  // Product Import State & Modals
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importPreviewData, setImportPreviewData] = useState(null);
  const [importLoading, setImportLoading] = useState(false);
  const [importError, setImportError] = useState(null);
  const [importSuccess, setImportSuccess] = useState(null);
  const [allowImportStock, setAllowImportStock] = useState(false);

  // Stock Movements & Adjustments
  const [movements, setMovements] = useState([]);
  const [adjustModalOpen, setAdjustModalOpen] = useState(false);
  const [adjustForm, setAdjustForm] = useState({ product_id: '', type: 'Correction', quantity: '10', reason: 'Stock Count Correction' });

  // Alerts
  const [alertMsg, setAlertMsg] = useState(null);

  // Fallback placeholder image SVG
  const fallbackImage = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="%2310b981" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>';

  // Debounced search to prevent request per keystroke
  const [debouncedSearch, setDebouncedSearch] = useState('');

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
    }, 350);
    return () => clearTimeout(timer);
  }, [search]);

  // Load Products with Server-Side Pagination (PRD Section 33)
  const fetchProducts = async (page = currentPage, pageSize = perPage) => {
    setProductsLoading(true);
    try {
      const params = {
        page,
        per_page: pageSize,
      };
      if (debouncedSearch) params.q = debouncedSearch;
      if (selectedCategory) params.category_id = selectedCategory;
      if (stockStatusFilter) params.stock_status = stockStatusFilter;
      if (statusFilter) params.status = statusFilter;

      const prodRes = await adminApi.getProducts(params);
      if (prodRes) {
        const items = Array.isArray(prodRes.data) ? prodRes.data : (prodRes.data?.data || []);
        const meta = prodRes.meta || prodRes.data?.meta || {};
        setProducts(items);
        setCurrentPage(meta.current_page || page);
        setTotalPages(meta.last_page || 1);
        setTotalProducts(meta.total !== undefined ? meta.total : items.length);
        setFromItem(meta.from || (items.length > 0 ? (page - 1) * pageSize + 1 : 0));
        setToItem(meta.to || (items.length > 0 ? (page - 1) * pageSize + items.length : 0));
      }

      // Cache categories: only fetch once if empty
      if (categories.length === 0) {
        const catRes = await adminApi.getCategories();
        if (catRes && catRes.data) {
          setCategories(catRes.data || []);
        }
      }
    } catch (e) {
      console.error('Fetch products error:', e);
    } finally {
      setProductsLoading(false);
    }
  };

  // Open Product Ledger Drawer
  const handleOpenLedger = async (product, page = 1) => {
    setLedgerProduct(product);
    setLedgerLoading(true);
    setLedgerPage(page);
    try {
      const res = await adminApi.getProductLedger(product.id, { page, per_page: 15 });
      if (res && res.data) {
        setLedgerMovements(res.data.movements || []);
        setLedgerTotalPages(res.data.pagination?.last_page || 1);
        setLedgerTotal(res.data.pagination?.total || 0);
      }
    } catch (e) {
      console.error('Failed to load product ledger:', e);
      setAlertMsg({ type: 'error', text: 'Failed to load product ledger: ' + (e.response?.data?.message || e.message) });
    } finally {
      setLedgerLoading(false);
    }
  };

  // Open Reconciliation Modal
  const handleOpenReconciliation = async () => {
    setReconcileModalOpen(true);
    setReconcileLoading(true);
    try {
      const res = await adminApi.getReconciliationReport();
      if (res && res.data) {
        setReconcileReport(res.data);
      }
    } catch (e) {
      console.error('Failed to load reconciliation report:', e);
      setAlertMsg({ type: 'error', text: 'Failed to load reconciliation report: ' + (e.response?.data?.message || e.message) });
    } finally {
      setReconcileLoading(false);
    }
  };

  // Apply Reconciliation Correction
  const handleApplyCorrection = async (e) => {
    e.preventDefault();
    if (!correctingProduct) return;
    if (!correctionReason.trim()) {
      alert('A valid reason is required for stock correction.');
      return;
    }

    setCorrectionSubmitting(true);
    try {
      const res = await adminApi.correctReconciliation(correctingProduct.id, {
        corrected_quantity: parseInt(correctionTarget, 10),
        reason: correctionReason.trim(),
      });
      if (res && res.success) {
        setAlertMsg({ type: 'success', text: `Discrepancy for '${correctingProduct.name}' corrected successfully.` });
        setCorrectingProduct(null);
        setCorrectionReason('');
        setCorrectionTarget('');
        handleOpenReconciliation();
        fetchProducts(currentPage, perPage);
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Correction failed');
    } finally {
      setCorrectionSubmitting(false);
    }
  };

  // Load Stock Movements (Lazy loaded)
  const fetchMovements = async () => {
    try {
      const res = await adminApi.getInventory();
      if (res && res.data && res.data.recent_movements) {
        setMovements(res.data.recent_movements);
      }
    } catch (e) {
      console.error('Fetch movements error:', e);
    }
  };

  useEffect(() => {
    setCurrentPage(1);
    fetchProducts(1, perPage);
  }, [debouncedSearch, selectedCategory, stockStatusFilter, statusFilter]);

  useEffect(() => {
    if (activeTab === 'movements') {
      fetchMovements();
    }
  }, [activeTab]);

  // Product Selection Handlers
  const handleSelectAll = (e) => {
    if (e.target.checked) {
      setSelectedProductIds(products.map(p => p.id));
    } else {
      setSelectedProductIds([]);
    }
  };

  const handleSelectProduct = (id) => {
    if (selectedProductIds.includes(id)) {
      setSelectedProductIds(selectedProductIds.filter(item => item !== id));
    } else {
      setSelectedProductIds([...selectedProductIds, id]);
    }
  };

  // Open Product Modal (Add or Edit)
  const handleOpenProductModal = (prod = null) => {
    if (prod) {
      setEditingProduct(prod);
      setProductForm({
        name: prod.name,
        category_id: prod.category_id,
        sku: prod.sku || '',
        barcode: prod.barcode || '',
        unit: prod.unit || 'piece',
        wholesale_cost: prod.wholesale_cost || '',
        retail_price: prod.retail_price || prod.price || '',
        stock_quantity: prod.stock_quantity || 0,
        minimum_stock_level: prod.minimum_stock_level || 5,
        description: prod.description || '',
        status: prod.status || 'active',
        image_url: prod.image_url || prod.image || '',
        image_file: null,
        remove_image: false,
      });
      setImagePreview(prod.image_url || prod.image || null);
    } else {
      setEditingProduct(null);
      setProductForm({
        name: '',
        category_id: categories[0]?.id || '',
        sku: '',
        barcode: '',
        unit: 'piece',
        wholesale_cost: '',
        retail_price: '',
        stock_quantity: '0',
        minimum_stock_level: '5',
        description: '',
        status: 'active',
        image_url: '',
        image_file: null,
        remove_image: false,
      });
      setImagePreview(null);
    }
    setProductModalOpen(true);
  };

  // Product Image Selection
  const handleProductImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setProductForm({ ...productForm, image_file: file, remove_image: false });
      setImagePreview(URL.createObjectURL(file));
    }
  };

  const handleRemoveProductImage = () => {
    setProductForm({ ...productForm, image_file: null, image_url: '', remove_image: true });
    setImagePreview(null);
  };

  // Save Product (Create or Update)
  const handleSaveProduct = async (e) => {
    e.preventDefault();
    setProductSubmitting(true);
    try {
      const formData = new FormData();
      Object.keys(productForm).forEach((key) => {
        if (key === 'image_file' && productForm.image_file) {
          formData.append('image_file', productForm.image_file);
        } else if (key === 'remove_image' && productForm.remove_image) {
          formData.append('remove_image', '1');
        } else if (productForm[key] !== null && productForm[key] !== '' && key !== 'image_file') {
          formData.append(key, productForm[key]);
        }
      });

      const res = await adminApi.saveProduct(formData, editingProduct?.id || null);
      if (res && res.success) {
        setProductModalOpen(false);
        setAlertMsg({ type: 'success', text: `Product '${productForm.name}' saved successfully.` });
        fetchProducts();
      }
    } catch (err) {
      setAlertMsg({ type: 'error', text: err.response?.data?.message || 'Failed to save product' });
    } finally {
      setProductSubmitting(false);
    }
  };

  // Bulk Delete Execution
  const handleExecuteBulkDelete = async () => {
    setBulkProcessing(true);
    try {
      const res = await adminApi.bulkDeleteProducts(selectedProductIds);
      if (res && res.success) {
        setShowBulkDeleteModal(false);
        setSelectedProductIds([]);
        setAlertMsg({ type: 'success', text: res.message || 'Bulk deletion completed.' });
        fetchProducts();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Bulk delete failed');
    } finally {
      setBulkProcessing(false);
    }
  };

  // Bulk Category Execution
  const handleExecuteBulkCategory = async () => {
    if (!bulkTargetCategory) return;
    setBulkProcessing(true);
    try {
      const res = await adminApi.bulkChangeCategory(selectedProductIds, bulkTargetCategory);
      if (res && res.success) {
        setShowBulkCategoryModal(false);
        setSelectedProductIds([]);
        setAlertMsg({ type: 'success', text: 'Category updated for selected products.' });
        fetchProducts();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Bulk category change failed');
    } finally {
      setBulkProcessing(false);
    }
  };

  // Bulk Status Execution
  const handleExecuteBulkStatus = async () => {
    setBulkProcessing(true);
    try {
      const res = await adminApi.bulkChangeStatus(selectedProductIds, bulkTargetStatus);
      if (res && res.success) {
        setShowBulkStatusModal(false);
        setSelectedProductIds([]);
        setAlertMsg({ type: 'success', text: `Status changed to '${bulkTargetStatus}' for selected products.` });
        fetchProducts();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Bulk status change failed');
    } finally {
      setBulkProcessing(false);
    }
  };

  // Category Modal Handlers
  const handleOpenCategoryModal = (cat = null) => {
    if (cat) {
      setEditingCategory(cat);
      setCategoryForm({
        name: cat.name,
        description: cat.description || '',
        sort_order: cat.sort_order || 0,
        status: cat.status || 'active',
        image_url: cat.image_url || cat.image || '',
        image_file: null,
        remove_image: false,
      });
      setCategoryImagePreview(cat.image_url || cat.image || null);
    } else {
      setEditingCategory(null);
      setCategoryForm({
        name: '',
        description: '',
        sort_order: 0,
        status: 'active',
        image_url: '',
        image_file: null,
        remove_image: false,
      });
      setCategoryImagePreview(null);
    }
    setCategoryModalOpen(true);
  };

  const handleCategoryImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setCategoryForm({ ...categoryForm, image_file: file, remove_image: false });
      setCategoryImagePreview(URL.createObjectURL(file));
    }
  };

  const handleSaveCategory = async (e) => {
    e.preventDefault();
    setCategorySubmitting(true);
    try {
      const formData = new FormData();
      Object.keys(categoryForm).forEach(k => {
        if (k === 'image_file' && categoryForm.image_file) {
          formData.append('image_file', categoryForm.image_file);
        } else if (k === 'remove_image' && categoryForm.remove_image) {
          formData.append('remove_image', '1');
        } else if (categoryForm[k] !== null && categoryForm[k] !== '' && k !== 'image_file') {
          formData.append(k, categoryForm[k]);
        }
      });

      const res = await adminApi.saveCategory(formData, editingCategory?.id || null);
      if (res && res.success) {
        setCategoryModalOpen(false);
        setAlertMsg({ type: 'success', text: `Category '${categoryForm.name}' saved successfully.` });
        fetchProducts();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to save category');
    } finally {
      setCategorySubmitting(false);
    }
  };

  // Safe Category Deletion Handler
  const handleInitiateDeleteCategory = (cat) => {
    setCategoryToDelete(cat);
    setReassignCategoryTarget(categories.find(c => c.id !== cat.id)?.id || '');
    setCategoryDeleteModalOpen(true);
  };

  const handleExecuteCategoryDelete = async (actionType) => {
    try {
      const params = {};
      if (actionType === 'reassign') {
        params.move_to_category_id = reassignCategoryTarget;
      } else if (actionType === 'archive') {
        params.archive = true;
      }

      const res = await adminApi.deleteCategory(categoryToDelete.id, params);
      if (res && res.success) {
        setCategoryDeleteModalOpen(false);
        setCategoryToDelete(null);
        setAlertMsg({ type: 'success', text: res.message || 'Category processed successfully.' });
        fetchProducts();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete category');
    }
  };

  // Product Import Handlers
  const handleSelectImportFile = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    setImportFile(file);
    setImportError(null);
    setImportSuccess(null);
    setImportLoading(true);

    try {
      // Step 1: Send preview request
      const formData = new FormData();
      formData.append('file', file);
      formData.append('confirm', '0');

      const res = await adminApi.importProducts(formData);
      if (res && res.data) {
        setImportPreviewData(res.data);
      }
    } catch (err) {
      setImportError(err.response?.data?.message || 'Failed to parse import file');
    } finally {
      setImportLoading(false);
    }
  };

  const handleConfirmImport = async () => {
    if (!importFile) return;
    setImportLoading(true);
    setImportError(null);

    try {
      const formData = new FormData();
      formData.append('file', importFile);
      formData.append('confirm', '1');
      if (allowImportStock) {
        formData.append('import_stock', '1');
      }

      const res = await adminApi.importProducts(formData);
      if (res && res.success) {
        setImportSuccess(res.message);
        fetchProducts();
      }
    } catch (err) {
      setImportError(err.response?.data?.message || 'Import execution failed');
    } finally {
      setImportLoading(false);
    }
  };

  // Manual Stock Adjustment Submit
  const handleAdjustSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await adminApi.adjustStock(adjustForm);
      if (res && res.success) {
        setAdjustModalOpen(false);
        setAlertMsg({ type: 'success', text: 'Stock adjusted successfully.' });
        fetchProducts();
        fetchMovements();
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Adjustment failed');
    }
  };

  return (
    <div className="space-y-6">

      {/* Top Header & Toolbar (PRD Section 16, 17, 51) */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            Inventory & Catalog Control
            <span className="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
              Master Station
            </span>
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Manage product catalog, high-res images, categories, stock levels, and Excel bulk imports/exports.
          </p>
        </div>

        {/* Primary Toolbar: [ + Add Product ] [ Import ] [ Export ] (No Receive Stock!) */}
        <div className="flex items-center gap-2.5">
          <button
            onClick={() => handleOpenProductModal()}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-md shadow-emerald-600/20"
          >
            <Plus className="w-4 h-4" />
            + Add Product
          </button>

          <button
            onClick={() => {
              setImportFile(null);
              setImportPreviewData(null);
              setImportError(null);
              setImportSuccess(null);
              setImportModalOpen(true);
            }}
            className="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-2 shadow-xs"
          >
            <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
            Import
          </button>

          <a
            href={adminApi.getProductExportUrl({
              q: search,
              category_id: selectedCategory,
              stock_status: stockStatusFilter,
              status: statusFilter,
            })}
            className="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-2 shadow-xs"
          >
            <Download className="w-4 h-4 text-slate-500" />
            Export
          </a>

          <button
            onClick={handleOpenReconciliation}
            className="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-colors shadow-xs"
            title="Detect & Correct Stock Ledger Discrepancies"
          >
            <ShieldCheck className="w-4 h-4 text-indigo-600" />
            Reconcile Stock
          </button>

          <button
            onClick={() => {
              setAdjustForm({ product_id: products[0]?.id || '', type: 'Correction', quantity: '10', reason: 'Stock Count Correction' });
              setAdjustModalOpen(true);
            }}
            className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs flex items-center gap-1.5"
            title="Manual stock correction"
          >
            Adjust Stock
          </button>
        </div>
      </div>

      {/* Alert Banner */}
      {alertMsg && (
        <div className={`p-4 rounded-2xl text-xs font-semibold flex items-center justify-between border ${
          alertMsg.type === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'
        }`}>
          <div className="flex items-center gap-2">
            {alertMsg.type === 'error' ? <AlertCircle className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
            {alertMsg.text}
          </div>
          <button onClick={() => setAlertMsg(null)} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
      )}

      {/* Navigation Sub-Tabs (PRD Section 25, 62, 82, 83) */}
      <div className="flex items-center gap-2 border-b border-slate-200 text-xs font-bold">
        <button
          onClick={() => setActiveTab('products')}
          className={`pb-3 px-4 flex items-center gap-2 border-b-2 transition-all ${
            activeTab === 'products'
              ? 'border-emerald-600 text-emerald-700 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Layers className="w-4 h-4" />
          Products ({products.length})
        </button>

        <button
          onClick={() => setActiveTab('categories')}
          className={`pb-3 px-4 flex items-center gap-2 border-b-2 transition-all ${
            activeTab === 'categories'
              ? 'border-emerald-600 text-emerald-700 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Tags className="w-4 h-4" />
          Categories ({categories.length})
        </button>

        <button
          onClick={() => setActiveTab('movements')}
          className={`pb-3 px-4 flex items-center gap-2 border-b-2 transition-all ${
            activeTab === 'movements'
              ? 'border-emerald-600 text-emerald-700 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <RefreshCw className="w-4 h-4" />
          Stock Movements Audit
        </button>
      </div>

      {/* TAB 1: PRODUCTS LIST & BULK ACTIONS */}
      {activeTab === 'products' && (
        <div className="space-y-4">
          
          {/* Filters Bar */}
          <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
              <div className="relative flex-1 md:w-64">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search name, SKU, barcode..."
                  className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 font-medium"
                />
              </div>

              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none"
              >
                <option value="">All Categories</option>
                {categories.map(c => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>

              <select
                value={stockStatusFilter}
                onChange={(e) => setStockStatusFilter(e.target.value)}
                className="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none"
              >
                <option value="">All Stock Levels</option>
                <option value="in_stock">In Stock (&gt; Min)</option>
                <option value="low_stock">Low Stock (≤ Min)</option>
                <option value="out_of_stock">Out of Stock (0)</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none"
              >
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>

              <button
                onClick={fetchProducts}
                className="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors"
                title="Refresh products"
              >
                <RefreshCw className={`w-4 h-4 ${productsLoading ? 'animate-spin' : ''}`} />
              </button>
            </div>
          </div>

          {/* Contextual Floating Bulk Action Toolbar (PRD Section 18-24) */}
          {selectedProductIds.length > 0 && (
            <div className="bg-slate-900 text-white p-3.5 rounded-2xl shadow-xl border border-slate-800 flex items-center justify-between animate-in fade-in slide-in-from-top-2 duration-200">
              <div className="flex items-center gap-3">
                <span className="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs flex items-center justify-center">
                  {selectedProductIds.length}
                </span>
                <span className="text-xs font-bold tracking-wide">
                  {selectedProductIds.length} {selectedProductIds.length === 1 ? 'product' : 'products'} selected
                </span>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={() => setShowBulkCategoryModal(true)}
                  className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-xl border border-slate-700 flex items-center gap-1.5"
                >
                  <Tags className="w-3.5 h-3.5 text-emerald-400" />
                  Change Category
                </button>

                <button
                  onClick={() => setShowBulkStatusModal(true)}
                  className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-xl border border-slate-700 flex items-center gap-1.5"
                >
                  Change Status
                </button>

                <button
                  onClick={() => setShowBulkDeleteModal(true)}
                  className="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-sm shadow-rose-600/30"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                  Delete / Archive
                </button>

                <button
                  onClick={() => setSelectedProductIds([])}
                  className="text-xs text-slate-400 hover:text-white px-2"
                >
                  Clear
                </button>
              </div>
            </div>
          )}

          {/* Products Table */}
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            {productsLoading ? (
              <div className="p-12 text-center text-slate-400 text-xs font-medium">
                Loading grocery catalog...
              </div>
            ) : products.length === 0 ? (
              <div className="p-12 text-center text-slate-400 text-xs space-y-2">
                <Warehouse className="w-10 h-10 text-slate-300 mx-auto" />
                <div className="font-semibold text-slate-600">No products found</div>
                <p>Try clearing filters or add a new product using the toolbar above.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200/60">
                    <tr>
                      <th className="py-3 px-3 text-center w-10">
                        <input
                          type="checkbox"
                          checked={selectedProductIds.length === products.length && products.length > 0}
                          onChange={handleSelectAll}
                          className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        />
                      </th>
                      <th className="py-3 px-3 text-center w-16">Image</th>
                      <th className="py-3 px-4">Product Name & SKU</th>
                      <th className="py-3 px-3">Barcode</th>
                      <th className="py-3 px-3">Category</th>
                      <th className="py-3 px-3 text-center" title="Current physical units on shelf">Physical</th>
                      <th className="py-3 px-3 text-center" title="Units locked in pending/active online orders">Reserved</th>
                      <th className="py-3 px-3 text-center" title="Sellable stock available for new orders">Available</th>
                      <th className="py-3 px-3 text-right">Cost (AED)</th>
                      <th className="py-3 px-3 text-right">Price (AED)</th>
                      <th className="py-3 px-3 text-center">Status</th>
                      <th className="py-3 px-4 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 font-medium">
                    {products.map((p) => {
                      const isSelected = selectedProductIds.includes(p.id);
                      const physical = Number(p.stock_quantity ?? 0);
                      const reserved = Number(p.reserved_quantity ?? 0);
                      const available = Math.max(0, physical - reserved);
                      const minStock = Number(p.minimum_stock_level ?? 5);
                      const isOutOfStock = available <= 0;
                      const isLowStock = available <= minStock && !isOutOfStock;
                      const imgSrc = p.image_url || p.image || null;

                      return (
                        <tr key={p.id} className={`transition-colors ${isSelected ? 'bg-emerald-50/50' : 'hover:bg-slate-50/70'}`}>
                          
                          {/* Checkbox */}
                          <td className="py-3 px-3 text-center">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => handleSelectProduct(p.id)}
                              className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                            />
                          </td>

                          {/* Image Thumbnail */}
                          <td className="py-2.5 px-3 text-center">
                            <div
                              onClick={() => imgSrc && setEnlargedImage({ url: imgSrc, name: p.name })}
                              className="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center cursor-pointer hover:opacity-80 transition-opacity mx-auto"
                            >
                              {imgSrc ? (
                                <img
                                  src={imgSrc}
                                  alt={p.name}
                                  className="w-full h-full object-cover"
                                  onError={(e) => { e.target.onerror = null; e.target.src = fallbackImage; }}
                                />
                              ) : (
                                <Image className="w-5 h-5 text-slate-300" />
                              )}
                            </div>
                          </td>

                          {/* Product & SKU */}
                          <td className="py-3 px-4">
                            <div className="font-extrabold text-slate-900 text-sm">{p.name}</div>
                            <div className="text-[11px] font-mono text-slate-400 mt-0.5 flex items-center gap-2">
                              <span>{p.sku || `SKU-${p.id}`}</span>
                              <span>•</span>
                              <span className="uppercase text-[10px] text-slate-500">{p.unit || 'piece'}</span>
                            </div>
                          </td>

                          {/* Barcode */}
                          <td className="py-3 px-3 font-mono text-slate-600 text-xs">
                            {p.barcode || '—'}
                          </td>

                          {/* Category */}
                          <td className="py-3 px-3 text-slate-700">
                            <span className="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-800 text-[11px] font-semibold">
                              {p.category?.name || 'General'}
                            </span>
                          </td>

                          {/* Physical Stock */}
                          <td className="py-3 px-3 text-center font-mono font-bold text-slate-800">
                            {physical}
                          </td>

                          {/* Reserved Stock */}
                          <td className="py-3 px-3 text-center font-mono">
                            {reserved > 0 ? (
                              <span className="px-2 py-0.5 rounded-full text-[11px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                {reserved}
                              </span>
                            ) : (
                              <span className="text-slate-400">0</span>
                            )}
                          </td>

                          {/* Available Stock */}
                          <td className="py-3 px-3 text-center font-mono">
                            <span className={`px-2.5 py-1 rounded-full text-xs font-black ${
                              isOutOfStock
                                ? 'bg-rose-100 text-rose-800 border border-rose-200'
                                : (isLowStock ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200')
                            }`}>
                              {available}
                            </span>
                          </td>

                          {/* Wholesale Cost */}
                          <td className="py-3 px-3 text-right font-mono text-slate-500">
                            AED {parseFloat(p.wholesale_cost || 0).toFixed(2)}
                          </td>

                          {/* Retail Selling Price */}
                          <td className="py-3 px-3 text-right font-mono font-extrabold text-emerald-700 text-sm">
                            AED {parseFloat(p.retail_price || p.price || 0).toFixed(2)}
                          </td>

                          {/* Status */}
                          <td className="py-3 px-3 text-center">
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                              p.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                            }`}>
                              {p.status || 'active'}
                            </span>
                          </td>

                          {/* Actions */}
                          <td className="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button
                              onClick={() => handleOpenLedger(p)}
                              className="p-1.5 text-slate-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors"
                              title="View stock movement ledger history"
                            >
                              <History className="w-3.5 h-3.5" />
                            </button>
                            <button
                              onClick={() => handleOpenProductModal(p)}
                              className="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors"
                              title="Edit product"
                            >
                              <Edit2 className="w-3.5 h-3.5" />
                            </button>
                            <button
                              onClick={async () => {
                                if (window.confirm(`Delete or archive '${p.name}'?`)) {
                                  await adminApi.deleteProduct(p.id);
                                  fetchProducts(currentPage, perPage);
                                }
                              }}
                              className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                              title="Delete product"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </td>

                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            {/* Pagination Controls Bar (PRD Section 33) */}
            <div className="p-4 bg-slate-50 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 font-medium">
              <div className="flex items-center gap-3">
                <span>
                  Showing <strong className="text-slate-800">{totalProducts > 0 ? fromItem : 0}</strong> to{' '}
                  <strong className="text-slate-800">{toItem}</strong> of{' '}
                  <strong className="text-slate-800">{totalProducts}</strong> products
                </span>
                <div className="flex items-center gap-1.5 ml-2">
                  <span>Per page:</span>
                  <select
                    value={perPage}
                    onChange={(e) => {
                      const newSize = parseInt(e.target.value, 10);
                      setPerPage(newSize);
                      setCurrentPage(1);
                      fetchProducts(1, newSize);
                    }}
                    className="px-2 py-1 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none cursor-pointer"
                  >
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <button
                  disabled={currentPage <= 1 || productsLoading}
                  onClick={() => {
                    const p = currentPage - 1;
                    setCurrentPage(p);
                    fetchProducts(p, perPage);
                  }}
                  className="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  title="Previous Page"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
                <span className="px-3 py-1 font-bold text-slate-700 bg-white border border-slate-200 rounded-lg shadow-2xs">
                  Page {currentPage} of {totalPages}
                </span>
                <button
                  disabled={currentPage >= totalPages || productsLoading}
                  onClick={() => {
                    const p = currentPage + 1;
                    setCurrentPage(p);
                    fetchProducts(p, perPage);
                  }}
                  className="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  title="Next Page"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

        </div>
      )}

      {/* TAB 2: CATEGORIES MANAGEMENT (PRD Section 26-34, 83) */}
      {activeTab === 'categories' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <p className="text-xs text-slate-500">
              Manage category taxonomy, upload category covers for the customer PWA, and control display sort orders.
            </p>

            <button
              onClick={() => handleOpenCategoryModal()}
              className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-md shadow-emerald-600/20"
            >
              <Plus className="w-4 h-4" />
              + Add Category
            </button>
          </div>

          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200/60">
                <tr>
                  <th className="py-3 px-4 text-center w-16">Cover</th>
                  <th className="py-3 px-4">Category Name & Slug</th>
                  <th className="py-3 px-4">Description</th>
                  <th className="py-3 px-3 text-center">Assigned Products</th>
                  <th className="py-3 px-3 text-center">Sort Order</th>
                  <th className="py-3 px-3 text-center">Status</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium">
                {categories.map((c) => {
                  const catImg = c.image_url || c.image || null;
                  return (
                    <tr key={c.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="py-2.5 px-4 text-center">
                        <div className="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center mx-auto">
                          {catImg ? (
                            <img src={catImg} alt={c.name} className="w-full h-full object-cover" />
                          ) : (
                            <Tags className="w-5 h-5 text-slate-300" />
                          )}
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="font-extrabold text-slate-900 text-sm">{c.name}</div>
                        <div className="text-[11px] font-mono text-slate-400 mt-0.5">{c.slug || c.name.toLowerCase()}</div>
                      </td>
                      <td className="py-3 px-4 text-slate-500 truncate max-w-xs">
                        {c.description || '—'}
                      </td>
                      <td className="py-3 px-3 text-center font-mono font-bold text-slate-800">
                        {c.products_count || 0}
                      </td>
                      <td className="py-3 px-3 text-center font-mono text-slate-600">
                        {c.sort_order || 0}
                      </td>
                      <td className="py-3 px-3 text-center">
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                          c.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                        }`}>
                          {c.status || 'active'}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right space-x-1">
                        <button
                          onClick={() => handleOpenCategoryModal(c)}
                          className="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors"
                          title="Edit category"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          onClick={() => handleInitiateDeleteCategory(c)}
                          className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                          title="Delete / Archive category"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: STOCK MOVEMENTS AUDIT */}
      {activeTab === 'movements' && (
        <div className="space-y-4">
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div className="p-4 border-b border-slate-100 font-bold text-slate-900 flex justify-between items-center">
              <span>Immutable Inventory Movement Ledger</span>
              <span className="text-xs text-slate-400 font-normal">Records every purchase receipt, customer sale, adjustment & return</span>
            </div>

            <table className="w-full text-left text-xs font-mono">
              <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] border-b border-slate-200/60">
                <tr>
                  <th className="py-3 px-4">Movement Type</th>
                  <th className="py-3 px-4">Qty Diff</th>
                  <th className="py-3 px-4">Before &rarr; After</th>
                  <th className="py-3 px-4">Unit Cost</th>
                  <th className="py-3 px-4">Reason / Reference</th>
                  <th className="py-3 px-4">User</th>
                  <th className="py-3 px-4">Timestamp</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {movements.map((m) => {
                  const isPositive = m.quantity > 0;
                  return (
                    <tr key={m.id} className="hover:bg-slate-50/70">
                      <td className="py-3 px-4">
                        <span className={`px-2 py-0.5 rounded-md font-sans text-[10px] font-bold uppercase ${
                          m.type === 'Purchase' || m.type === 'GRN' ? 'bg-emerald-100 text-emerald-800' :
                          (m.type === 'Sale' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')
                        }`}>
                          {m.type}
                        </span>
                      </td>
                      <td className={`py-3 px-4 font-black ${isPositive ? 'text-emerald-600' : 'text-rose-600'}`}>
                        {isPositive ? `+${m.quantity}` : m.quantity}
                      </td>
                      <td className="py-3 px-4 text-slate-600">
                        {m.stock_before} &rarr; <strong>{m.stock_after}</strong>
                      </td>
                      <td className="py-3 px-4 text-slate-600">
                        AED {parseFloat(m.unit_cost || 0).toFixed(2)}
                      </td>
                      <td className="py-3 px-4 font-sans text-slate-700">
                        {m.reason} {m.reference_type ? `(${m.reference_type} #${m.reference_id})` : ''}
                      </td>
                      <td className="py-3 px-4 font-sans text-slate-500">
                        {m.created_by || 'System'}
                      </td>
                      <td className="py-3 px-4 text-slate-400">
                        {m.created_at ? new Date(m.created_at).toLocaleString() : '—'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* MODAL: ADD / EDIT PRODUCT (PRD Section 64, 65) */}
      {productModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-black text-lg text-slate-900">
                {editingProduct ? `Edit Product: ${editingProduct.name}` : 'Create New Product Master'}
              </h3>
              <button onClick={() => setProductModalOpen(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form onSubmit={handleSaveProduct} className="space-y-4 text-xs">
              
              {/* SECTION 1: BASIC INFORMATION */}
              <div className="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                <div className="font-bold uppercase tracking-wider text-[10px] text-slate-400">1. Basic Information</div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div className="md:col-span-2">
                    <label className="block font-bold text-slate-700 mb-1">Product Title *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Farm Fresh Organic Eggs (30 Pack)"
                      value={productForm.name}
                      onChange={(e) => setProductForm({ ...productForm, name: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Category *</label>
                    <select
                      required
                      value={productForm.category_id}
                      onChange={(e) => setProductForm({ ...productForm, category_id: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-emerald-500"
                    >
                      <option value="">-- Select Category --</option>
                      {categories.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">SKU (Stock Keeping Unit)</label>
                    <input
                      type="text"
                      placeholder="e.g. EGGS-30P"
                      value={productForm.sku}
                      onChange={(e) => setProductForm({ ...productForm, sku: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono focus:outline-none focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Barcode (EAN-13 / UPC)</label>
                    <input
                      type="text"
                      placeholder="e.g. 6291030012345"
                      value={productForm.barcode}
                      onChange={(e) => setProductForm({ ...productForm, barcode: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono focus:outline-none focus:border-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Unit of Measure</label>
                    <input
                      type="text"
                      placeholder="e.g. piece, pack, 1L, 500g"
                      value={productForm.unit}
                      onChange={(e) => setProductForm({ ...productForm, unit: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                </div>
              </div>

              {/* SECTION 2: PRICING & INVENTORY */}
              <div className="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                <div className="font-bold uppercase tracking-wider text-[10px] text-slate-400">2. Pricing & Stock Thresholds</div>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Selling Price (AED) *</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      value={productForm.retail_price}
                      onChange={(e) => setProductForm({ ...productForm, retail_price: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono font-bold text-emerald-700 focus:outline-none"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Wholesale Cost (AED)</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={productForm.wholesale_cost}
                      onChange={(e) => setProductForm({ ...productForm, wholesale_cost: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Current Stock Quantity</label>
                    <input
                      type="number"
                      min="0"
                      value={productForm.stock_quantity}
                      onChange={(e) => setProductForm({ ...productForm, stock_quantity: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono font-bold text-slate-900"
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Low Stock Threshold</label>
                    <input
                      type="number"
                      min="0"
                      value={productForm.minimum_stock_level}
                      onChange={(e) => setProductForm({ ...productForm, minimum_stock_level: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono"
                    />
                  </div>
                </div>
              </div>

              {/* SECTION 3: IMAGE & CATALOG (PRD Section 4, 5, 65) */}
              <div className="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                <div className="font-bold uppercase tracking-wider text-[10px] text-slate-400">3. Catalog Presentation & Image</div>

                <div className="flex flex-col sm:flex-row items-center gap-4">
                  {/* Image Preview */}
                  <div className="w-24 h-24 rounded-2xl bg-white border-2 border-slate-200 overflow-hidden flex items-center justify-center shrink-0 shadow-xs relative">
                    {imagePreview ? (
                      <img
                        src={imagePreview}
                        alt="Product preview"
                        className="w-full h-full object-cover"
                        onError={(e) => { e.target.onerror = null; e.target.src = fallbackImage; }}
                      />
                    ) : (
                      <div className="text-center p-2 text-slate-300">
                        <Image className="w-8 h-8 mx-auto" />
                        <span className="text-[9px] block mt-1 font-bold">No Image</span>
                      </div>
                    )}
                  </div>

                  {/* Upload Actions */}
                  <div className="space-y-2 flex-1">
                    <label className="block text-xs font-bold text-slate-700">Product Image (JPG, PNG, WEBP &lt; 5MB)</label>
                    <div className="flex items-center gap-2">
                      <label className="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-100 rounded-xl cursor-pointer text-xs font-bold text-slate-700 flex items-center gap-1.5 shadow-xs">
                        <Upload className="w-3.5 h-3.5 text-emerald-600" />
                        {imagePreview ? 'Replace Image' : 'Upload Image'}
                        <input
                          type="file"
                          accept="image/png, image/jpeg, image/webp"
                          onChange={handleProductImageChange}
                          className="hidden"
                        />
                      </label>

                      {imagePreview && (
                        <button
                          type="button"
                          onClick={handleRemoveProductImage}
                          className="px-3 py-2 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold rounded-xl text-xs flex items-center gap-1"
                        >
                          <X className="w-3.5 h-3.5" /> Remove
                        </button>
                      )}
                    </div>
                    <p className="text-[11px] text-slate-400">
                      Uploaded image will be stored in Supabase Storage (<span className="font-mono">product-images</span>) and immediately appear on the Customer React PWA.
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Catalog Status</label>
                    <select
                      value={productForm.status}
                      onChange={(e) => setProductForm({ ...productForm, status: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium"
                    >
                      <option value="active">Active (Visible in Customer App)</option>
                      <option value="inactive">Inactive (Hidden from Customers)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Description</label>
                    <input
                      type="text"
                      placeholder="Optional product description or brand notes"
                      value={productForm.description}
                      onChange={(e) => setProductForm({ ...productForm, description: e.target.value })}
                      className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium"
                    />
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setProductModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={productSubmitting}
                  className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50 flex items-center gap-1.5"
                >
                  <Check className="w-4 h-4" />
                  {productSubmitting ? 'Saving...' : 'Save Product'}
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

      {/* MODAL: ADD / EDIT CATEGORY (PRD Section 27-30) */}
      {categoryModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-black text-base text-slate-900">
                {editingCategory ? `Edit Category: ${editingCategory.name}` : 'Create Category'}
              </h3>
              <button onClick={() => setCategoryModalOpen(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form onSubmit={handleSaveCategory} className="space-y-3">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Category Title *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dairy & Eggs"
                  value={categoryForm.name}
                  onChange={(e) => setCategoryForm({ ...categoryForm, name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Description</label>
                <textarea
                  placeholder="Milk, cheeses, yogurts and dairy essentials..."
                  rows={2}
                  value={categoryForm.description}
                  onChange={(e) => setCategoryForm({ ...categoryForm, description: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Sort Order (Display)</label>
                  <input
                    type="number"
                    value={categoryForm.sort_order}
                    onChange={(e) => setCategoryForm({ ...categoryForm, sort_order: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Status</label>
                  <select
                    value={categoryForm.status}
                    onChange={(e) => setCategoryForm({ ...categoryForm, status: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </div>

              {/* Category Image */}
              <div className="space-y-2 pt-2 border-t border-slate-100">
                <label className="block font-bold text-slate-700">Category Cover Image</label>
                <div className="flex items-center gap-3">
                  <div className="w-16 h-16 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                    {categoryImagePreview ? (
                      <img src={categoryImagePreview} alt="Category preview" className="w-full h-full object-cover" />
                    ) : (
                      <Tags className="w-6 h-6 text-slate-300" />
                    )}
                  </div>

                  <label className="px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl cursor-pointer text-xs font-bold text-slate-700 flex items-center gap-1">
                    <Upload className="w-3.5 h-3.5" /> Upload Cover
                    <input type="file" accept="image/*" onChange={handleCategoryImageChange} className="hidden" />
                  </label>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setCategoryModalOpen(false)}
                  className="px-3 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={categorySubmitting}
                  className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50"
                >
                  {categorySubmitting ? 'Saving...' : 'Save Category'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: SAFE CATEGORY DELETION (PRD Section 33) */}
      {categoryDeleteModalOpen && categoryToDelete && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-black text-slate-900 text-base flex items-center gap-2">
                <AlertTriangle className="w-5 h-5 text-amber-500" />
                Category Deletion Protection
              </h3>
              <button onClick={() => setCategoryDeleteModalOpen(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <p className="text-slate-600 leading-relaxed">
              Category <strong>'{categoryToDelete.name}'</strong> currently contains <strong>{categoryToDelete.products_count || 0} products</strong>. To maintain database integrity, products cannot be orphaned.
            </p>

            {categoryToDelete.products_count > 0 ? (
              <div className="space-y-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                <label className="block font-bold text-slate-700">Reassign Products To Another Category:</label>
                <select
                  value={reassignCategoryTarget}
                  onChange={(e) => setReassignCategoryTarget(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium"
                >
                  {categories.filter(c => c.id !== categoryToDelete.id).map(c => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>

                <div className="flex items-center justify-between pt-2">
                  <button
                    onClick={() => handleExecuteCategoryDelete('archive')}
                    className="text-slate-600 hover:text-slate-900 underline font-bold"
                  >
                    Archive Category Instead
                  </button>

                  <button
                    onClick={() => handleExecuteCategoryDelete('reassign')}
                    className="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl"
                  >
                    Move Products & Delete
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  onClick={() => setCategoryDeleteModalOpen(false)}
                  className="px-3 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                >
                  Cancel
                </button>
                <button
                  onClick={() => handleExecuteCategoryDelete('delete')}
                  className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl"
                >
                  Delete Category
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL: SAFE BULK DELETE (PRD Section 19-22) */}
      {showBulkDeleteModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-black text-slate-900 text-base flex items-center gap-2">
                <Trash2 className="w-5 h-5 text-rose-600" />
                Delete / Archive {selectedProductIds.length} Products?
              </h3>
              <button onClick={() => setShowBulkDeleteModal(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div className="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl space-y-1.5">
              <div className="font-bold flex items-center gap-1.5">
                <ShieldCheck className="w-4 h-4 text-amber-600" />
                Historical Data Protection Active
              </div>
              <p className="leading-relaxed">
                Products referenced by customer orders, stock movement logs, or receiving vouchers <strong>cannot be permanently deleted</strong> and will be safely archived (<span className="font-mono text-amber-950">inactive</span>) to protect reports and ledger history. Unreferenced products will be permanently removed.
              </p>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                onClick={() => setShowBulkDeleteModal(false)}
                className="px-3 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
              >
                Cancel
              </button>
              <button
                onClick={handleExecuteBulkDelete}
                disabled={bulkProcessing}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl disabled:opacity-50"
              >
                {bulkProcessing ? 'Processing...' : `Continue Safe Deletion`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: BULK CHANGE CATEGORY (PRD Section 24) */}
      {showBulkCategoryModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900">Change Category for {selectedProductIds.length} Products</h3>
              <button onClick={() => setShowBulkCategoryModal(false)} className="text-slate-400">&times;</button>
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Select New Category:</label>
              <select
                value={bulkTargetCategory}
                onChange={(e) => setBulkTargetCategory(e.target.value)}
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
              >
                <option value="">-- Choose Category --</option>
                {categories.map(c => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button onClick={() => setShowBulkCategoryModal(false)} className="btn-outline">Cancel</button>
              <button onClick={handleExecuteBulkCategory} disabled={!bulkTargetCategory || bulkProcessing} className="btn-glow">
                Apply Category
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: BULK CHANGE STATUS */}
      {showBulkStatusModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900">Change Status for {selectedProductIds.length} Products</h3>
              <button onClick={() => setShowBulkStatusModal(false)} className="text-slate-400">&times;</button>
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Select Target Status:</label>
              <select
                value={bulkTargetStatus}
                onChange={(e) => setBulkTargetStatus(e.target.value)}
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
              >
                <option value="active">Active (Visible in Store)</option>
                <option value="inactive">Inactive (Hidden from Customers)</option>
              </select>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2">
              <button onClick={() => setShowBulkStatusModal(false)} className="btn-outline">Cancel</button>
              <button onClick={handleExecuteBulkStatus} disabled={bulkProcessing} className="btn-glow">
                Apply Status
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: PRODUCT EXCEL / CSV IMPORT (PRD Section 35-49, 84) */}
      {importModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-black text-lg text-slate-900 flex items-center gap-2">
                  <FileSpreadsheet className="w-5 h-5 text-emerald-600" />
                  Import Products from Spreadsheet
                </h3>
                <p className="text-xs text-slate-500">Bulk create and update catalog products via Excel / CSV template.</p>
              </div>
              <button onClick={() => setImportModalOpen(false)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            {importSuccess ? (
              <div className="p-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-center space-y-3">
                <CheckCircle className="w-12 h-12 text-emerald-600 mx-auto" />
                <h4 className="font-bold text-base text-emerald-900">Import Completed Successfully!</h4>
                <p className="text-xs text-emerald-700 font-medium">{importSuccess}</p>
                <button
                  onClick={() => setImportModalOpen(false)}
                  className="px-5 py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs"
                >
                  Close & View Catalog
                </button>
              </div>
            ) : (
              <div className="space-y-4 text-xs">
                
                {/* STEP 1: DOWNLOAD TEMPLATE */}
                <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                  <div>
                    <div className="font-bold text-slate-800 text-sm">Step 1: Download Official Import Template</div>
                    <div className="text-slate-500 text-[11px] mt-0.5">Includes required headers (product_name, sku, category, prices) with sample rows.</div>
                  </div>
                  <a
                    href={adminApi.getProductImportTemplateUrl()}
                    className="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 rounded-xl font-bold text-slate-800 flex items-center gap-2 shadow-xs shrink-0"
                  >
                    <Download className="w-4 h-4 text-emerald-600" /> Download Excel Template
                  </a>
                </div>

                {/* STEP 2: UPLOAD FILE */}
                <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                  <div className="font-bold text-slate-800 text-sm">Step 2: Upload Completed File (.csv or .xlsx)</div>
                  <input
                    type="file"
                    accept=".csv, text/csv, application/vnd.ms-excel"
                    onChange={handleSelectImportFile}
                    className="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
                  />
                </div>

                {/* STEP 3: PREVIEW & VALIDATION STATS */}
                {importLoading && (
                  <div className="p-6 text-center text-slate-400 font-medium">
                    Validating spreadsheet rows...
                  </div>
                )}

                {importError && (
                  <div className="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl font-semibold flex items-center gap-2">
                    <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                    {importError}
                  </div>
                )}

                {importPreviewData && (
                  <div className="space-y-3">
                    <div className="font-bold text-slate-800 text-sm">Step 3: Validation Preview</div>
                    
                    {/* Stat Badges */}
                    <div className="grid grid-cols-4 gap-2 text-center">
                      <div className="p-2.5 bg-slate-100 rounded-xl">
                        <div className="text-[10px] text-slate-500 uppercase font-bold">Total Rows</div>
                        <div className="text-lg font-black text-slate-900">{importPreviewData.total_rows}</div>
                      </div>
                      <div className="p-2.5 bg-emerald-50 rounded-xl border border-emerald-200">
                        <div className="text-[10px] text-emerald-700 uppercase font-bold">New Products</div>
                        <div className="text-lg font-black text-emerald-800">{importPreviewData.new_count}</div>
                      </div>
                      <div className="p-2.5 bg-blue-50 rounded-xl border border-blue-200">
                        <div className="text-[10px] text-blue-700 uppercase font-bold">Updates</div>
                        <div className="text-lg font-black text-blue-800">{importPreviewData.update_count}</div>
                      </div>
                      <div className="p-2.5 bg-rose-50 rounded-xl border border-rose-200">
                        <div className="text-[10px] text-rose-700 uppercase font-bold">Errors</div>
                        <div className="text-lg font-black text-rose-800">{importPreviewData.errors_count}</div>
                      </div>
                    </div>

                    {/* Stock Protection Checkbox (PRD Section 47-48) */}
                    <label className="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={allowImportStock}
                        onChange={(e) => setAllowImportStock(e.target.checked)}
                        className="rounded border-amber-400 text-amber-600 focus:ring-amber-500"
                      />
                      <span>
                        <strong>Import as Opening Stock Balance</strong> (Unchecked: ignores stock column for existing products so live stock is preserved)
                      </span>
                    </label>

                    {/* Preview Table */}
                    <div className="max-h-48 overflow-y-auto border border-slate-200 rounded-xl">
                      <table className="w-full text-left text-[11px]">
                        <thead className="bg-slate-100 uppercase text-[9px] font-bold text-slate-600 sticky top-0">
                          <tr>
                            <th className="p-2">Row</th>
                            <th className="p-2">Product Name</th>
                            <th className="p-2">SKU</th>
                            <th className="p-2">Category</th>
                            <th className="p-2">Action</th>
                            <th className="p-2">Validation</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {importPreviewData.rows.map((r, i) => (
                            <tr key={i} className={r.status_code === 'ERROR' ? 'bg-rose-50/70' : ''}>
                              <td className="p-2 font-mono">{r.row_number}</td>
                              <td className="p-2 font-bold text-slate-800">{r.name || '—'}</td>
                              <td className="p-2 font-mono">{r.sku || '—'}</td>
                              <td className="p-2">{r.category || '—'}</td>
                              <td className="p-2">
                                <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${r.action === 'CREATE' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}>
                                  {r.action}
                                </span>
                              </td>
                              <td className="p-2 font-medium">
                                {r.status_code === 'VALID' ? (
                                  <span className="text-emerald-700 font-bold">✓ Valid</span>
                                ) : (
                                  <span className="text-rose-600 font-bold">{r.message}</span>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>

                    {/* Step 4 Action Buttons */}
                    <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                      <button
                        type="button"
                        onClick={() => setImportModalOpen(false)}
                        className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold"
                      >
                        Cancel
                      </button>

                      <button
                        type="button"
                        onClick={handleConfirmImport}
                        disabled={importLoading || importPreviewData.valid_count === 0}
                        className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50 flex items-center gap-2 shadow-md shadow-emerald-600/20"
                      >
                        <Check className="w-4 h-4" />
                        {importLoading ? 'Importing...' : `Confirm & Import (${importPreviewData.valid_count} Products)`}
                      </button>
                    </div>

                  </div>
                )}

              </div>
            )}

          </div>
        </div>
      )}

      {/* MODAL: ENLARGED IMAGE PREVIEW */}
      {enlargedImage && (
        <div
          className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 cursor-pointer"
          onClick={() => setEnlargedImage(null)}
        >
          <div className="bg-white rounded-3xl p-4 max-w-lg w-full shadow-2xl border border-slate-700 space-y-3" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <h4 className="font-extrabold text-slate-900 text-sm">{enlargedImage.name}</h4>
              <button onClick={() => setEnlargedImage(null)} className="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <div className="w-full h-80 bg-slate-100 rounded-2xl overflow-hidden flex items-center justify-center">
              <img
                src={enlargedImage.url}
                alt={enlargedImage.name}
                className="w-full h-full object-contain"
                onError={(e) => { e.target.onerror = null; e.target.src = fallbackImage; }}
              />
            </div>
          </div>
        </div>
      )}

      {/* MODAL / DRAWER: PRODUCT STOCK LEDGER (PRD Section 35) */}
      {ledgerProduct && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-end">
          <div className="bg-white h-full w-full max-w-3xl shadow-2xl border-l border-slate-200 flex flex-col animate-in slide-in-from-right duration-300">
            
            {/* Drawer Header */}
            <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-100 border border-indigo-200 flex items-center justify-center text-indigo-700">
                  <History className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-black text-base text-slate-900 tracking-tight flex items-center gap-2">
                    {ledgerProduct.name}
                    <span className="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-200 text-slate-700">
                      {ledgerProduct.sku || `ID #${ledgerProduct.id}`}
                    </span>
                  </h3>
                  <p className="text-xs text-slate-500 font-medium mt-0.5">
                    Immutable chronological stock movement ledger
                  </p>
                </div>
              </div>
              <button
                onClick={() => setLedgerProduct(null)}
                className="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 rounded-xl transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Stock Snapshot Cards */}
            <div className="p-5 grid grid-cols-3 gap-3 border-b border-slate-100 bg-white">
              <div className="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Physical Stock</div>
                <div className="text-xl font-black text-slate-800 font-mono mt-0.5">
                  {ledgerProduct.stock_quantity ?? 0}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">Units on shelf</div>
              </div>

              <div className="p-3 bg-amber-50/60 rounded-xl border border-amber-200">
                <div className="text-[10px] uppercase font-bold text-amber-700 tracking-wider">Reserved Stock</div>
                <div className="text-xl font-black text-amber-900 font-mono mt-0.5">
                  {ledgerProduct.reserved_quantity ?? 0}
                </div>
                <div className="text-[10px] text-amber-600 mt-0.5">Active online orders</div>
              </div>

              <div className="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200">
                <div className="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Available Stock</div>
                <div className="text-xl font-black text-emerald-800 font-mono mt-0.5">
                  {Math.max(0, (ledgerProduct.stock_quantity ?? 0) - (ledgerProduct.reserved_quantity ?? 0))}
                </div>
                <div className="text-[10px] text-emerald-600 mt-0.5">Sellable quantity</div>
              </div>
            </div>

            {/* Ledger Movements List */}
            <div className="flex-1 overflow-y-auto p-5">
              {ledgerLoading ? (
                <div className="p-12 text-center text-slate-400 text-xs font-medium space-y-2">
                  <RefreshCw className="w-6 h-6 animate-spin mx-auto text-indigo-500" />
                  <div>Loading ledger entries...</div>
                </div>
              ) : ledgerMovements.length === 0 ? (
                <div className="p-12 text-center text-slate-400 text-xs space-y-2">
                  <FileText className="w-8 h-8 text-slate-300 mx-auto" />
                  <div className="font-bold text-slate-600">No stock movements found</div>
                  <p>Every purchase receipt, order reservation, and adjustment will appear here.</p>
                </div>
              ) : (
                <div className="rounded-2xl border border-slate-200/80 overflow-hidden shadow-2xs">
                  <table className="w-full text-left text-xs font-mono">
                    <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] border-b border-slate-200/60 font-bold">
                      <tr>
                        <th className="py-2.5 px-3">Type</th>
                        <th className="py-2.5 px-3 text-center">Qty Diff</th>
                        <th className="py-2.5 px-3">Before &rarr; After</th>
                        <th className="py-2.5 px-3">Unit Cost</th>
                        <th className="py-2.5 px-3">Reason / Ref</th>
                        <th className="py-2.5 px-3">Date</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 font-medium">
                      {ledgerMovements.map((m) => {
                        const isPositive = Number(m.quantity) > 0;
                        const typeNormalized = (m.type || '').toLowerCase();
                        
                        let badgeClass = 'bg-slate-100 text-slate-700';
                        if (typeNormalized.includes('purchase') || typeNormalized === 'grn') {
                          badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        } else if (typeNormalized.includes('sale')) {
                          badgeClass = 'bg-blue-100 text-blue-800 border-blue-300';
                        } else if (typeNormalized === 'reservation') {
                          badgeClass = 'bg-amber-100 text-amber-800 border-amber-300';
                        } else if (typeNormalized.includes('release')) {
                          badgeClass = 'bg-indigo-100 text-indigo-800 border-indigo-300';
                        } else if (typeNormalized.includes('damaged') || typeNormalized.includes('expired')) {
                          badgeClass = 'bg-rose-100 text-rose-800 border-rose-300';
                        } else if (typeNormalized.includes('correction')) {
                          badgeClass = 'bg-purple-100 text-purple-800 border-purple-300';
                        } else if (typeNormalized.includes('return')) {
                          badgeClass = 'bg-teal-100 text-teal-800 border-teal-300';
                        }

                        return (
                          <tr key={m.id} className="hover:bg-slate-50/70 transition-colors">
                            <td className="py-2.5 px-3 font-sans">
                              <span className={`px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border ${badgeClass}`}>
                                {m.type?.replace('_', ' ')}
                              </span>
                            </td>
                            <td className={`py-2.5 px-3 text-center font-black ${isPositive ? 'text-emerald-600' : 'text-rose-600'}`}>
                              {isPositive ? `+${m.quantity}` : m.quantity}
                            </td>
                            <td className="py-2.5 px-3 text-slate-600">
                              {m.stock_before} &rarr; <strong className="text-slate-900">{m.stock_after}</strong>
                            </td>
                            <td className="py-2.5 px-3 text-slate-500">
                              AED {parseFloat(m.unit_cost || 0).toFixed(2)}
                            </td>
                            <td className="py-2.5 px-3 font-sans text-slate-700 max-w-xs truncate">
                              <span title={m.reason}>{m.reason || '—'}</span>
                              {m.reference_type && (
                                <span className="ml-1 text-[10px] text-slate-400">
                                  ({m.reference_type} #{m.reference_id})
                                </span>
                              )}
                            </td>
                            <td className="py-2.5 px-3 text-slate-400 text-[11px]">
                              {m.created_at ? new Date(m.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* Drawer Footer with Pagination */}
            <div className="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
              <div>
                Total: <strong className="text-slate-800">{ledgerTotal}</strong> recorded movements
              </div>
              <div className="flex items-center gap-2">
                <button
                  disabled={ledgerPage <= 1 || ledgerLoading}
                  onClick={() => handleOpenLedger(ledgerProduct, ledgerPage - 1)}
                  className="px-2.5 py-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-slate-700"
                >
                  &larr; Prev
                </button>
                <span className="font-bold text-slate-700">
                  Page {ledgerPage} of {ledgerTotalPages}
                </span>
                <button
                  disabled={ledgerPage >= ledgerTotalPages || ledgerLoading}
                  onClick={() => handleOpenLedger(ledgerProduct, ledgerPage + 1)}
                  className="px-2.5 py-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-slate-700"
                >
                  Next &rarr;
                </button>
              </div>
            </div>

          </div>
        </div>
      )}

      {/* MODAL: STOCK RECONCILIATION DIAGNOSTIC (PRD Section 62) */}
      {reconcileModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
            
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-100 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-2xl bg-indigo-100 border border-indigo-200 flex items-center justify-center text-indigo-700">
                  <Scale className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-black text-lg text-slate-900 tracking-tight">
                    Inventory Reconciliation Diagnostic
                  </h3>
                  <p className="text-xs text-slate-500 font-medium">
                    Audits physical shelf quantities against chronological stock movement ledgers.
                  </p>
                </div>
              </div>
              <button
                onClick={() => {
                  setReconcileModalOpen(false);
                  setCorrectingProduct(null);
                }}
                className="text-slate-400 hover:text-slate-600 p-2 rounded-xl"
              >
                &times;
              </button>
            </div>

            {/* Diagnostic Content */}
            {reconcileLoading ? (
              <div className="p-12 text-center text-slate-400 text-xs font-medium space-y-2">
                <RefreshCw className="w-6 h-6 animate-spin mx-auto text-indigo-500" />
                <div>Running diagnostic reconciliation check...</div>
              </div>
            ) : reconcileReport ? (
              <div className="space-y-4">
                
                {/* Summary Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="p-3.5 bg-slate-50 rounded-2xl border border-slate-200">
                    <div className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Catalog Audited</div>
                    <div className="text-xl font-black text-slate-800 font-mono mt-1">
                      {reconcileReport.summary?.total_products ?? 0}
                    </div>
                    <div className="text-[10px] text-slate-400 mt-0.5">Active catalog items</div>
                  </div>

                  <div className={`p-3.5 rounded-2xl border ${
                    (reconcileReport.summary?.discrepancies_count ?? 0) > 0
                      ? 'bg-rose-50 border-rose-200 text-rose-900'
                      : 'bg-emerald-50 border-emerald-200 text-emerald-900'
                  }`}>
                    <div className="text-[10px] uppercase font-bold tracking-wider opacity-75">
                      Discrepancies Found
                    </div>
                    <div className="text-xl font-black font-mono mt-1">
                      {reconcileReport.summary?.discrepancies_count ?? 0}
                    </div>
                    <div className="text-[10px] mt-0.5 opacity-75">
                      {(reconcileReport.summary?.discrepancies_count ?? 0) > 0 ? 'Requires attention' : 'Clean audit match'}
                    </div>
                  </div>

                  <div className="p-3.5 bg-indigo-50/60 rounded-2xl border border-indigo-200">
                    <div className="text-[10px] uppercase font-bold text-indigo-700 tracking-wider">Movements Audited</div>
                    <div className="text-xl font-black text-indigo-900 font-mono mt-1">
                      {reconcileReport.summary?.total_movements_checked ?? 0}
                    </div>
                    <div className="text-[10px] text-indigo-600 mt-0.5">Immutable ledger records</div>
                  </div>
                </div>

                {/* Discrepancies List / Clean State */}
                {reconcileReport.discrepancies?.length === 0 ? (
                  <div className="p-8 bg-emerald-50/60 border border-emerald-200 rounded-2xl text-center space-y-2">
                    <CheckCircle2 className="w-10 h-10 text-emerald-600 mx-auto" />
                    <div className="font-black text-emerald-900 text-sm">100% Reconciliation Integrity</div>
                    <p className="text-xs text-emerald-700 max-w-md mx-auto">
                      All physical shelf quantities match computed stock movement ledgers perfectly. Zero untracked variances found.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    <div className="text-xs font-bold text-slate-800 flex items-center justify-between">
                      <span>Discrepant Products ({reconcileReport.discrepancies.length})</span>
                      <span className="text-[11px] text-slate-400 font-normal">
                        Select a product to apply an audit-safe correction
                      </span>
                    </div>

                    <div className="rounded-2xl border border-slate-200 overflow-hidden shadow-2xs">
                      <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200">
                          <tr>
                            <th className="py-2.5 px-3">Product</th>
                            <th className="py-2.5 px-3 text-center font-mono">Current Stock</th>
                            <th className="py-2.5 px-3 text-center font-mono">Ledger Sum</th>
                            <th className="py-2.5 px-3 text-center font-mono">Variance</th>
                            <th className="py-2.5 px-3 text-right">Action</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 font-medium">
                          {reconcileReport.discrepancies.map((d) => (
                            <tr key={d.product_id} className="hover:bg-slate-50/70 transition-colors">
                              <td className="py-2.5 px-3">
                                <div className="font-extrabold text-slate-900">{d.product_name}</div>
                                <div className="text-[11px] font-mono text-slate-400">SKU: {d.sku}</div>
                              </td>
                              <td className="py-2.5 px-3 text-center font-mono font-bold text-slate-800">
                                {d.current_stock}
                              </td>
                              <td className="py-2.5 px-3 text-center font-mono font-bold text-indigo-700">
                                {d.computed_stock}
                              </td>
                              <td className={`py-2.5 px-3 text-center font-mono font-black ${
                                d.difference > 0 ? 'text-emerald-600' : 'text-rose-600'
                              }`}>
                                {d.difference > 0 ? `+${d.difference}` : d.difference}
                              </td>
                              <td className="py-2.5 px-3 text-right">
                                <button
                                  onClick={() => {
                                    setCorrectingProduct(d);
                                    setCorrectionTarget(d.computed_stock.toString());
                                    setCorrectionReason(`Reconciliation variance correction: shelf was ${d.current_stock}, ledger computed ${d.computed_stock}`);
                                  }}
                                  className="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-[11px] transition-colors shadow-xs"
                                >
                                  Correct
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}

                {/* Sub-form: Apply Correction */}
                {correctingProduct && (
                  <div className="p-4 bg-indigo-50/70 border border-indigo-200 rounded-2xl space-y-3 animate-in fade-in duration-200 text-xs">
                    <div className="font-extrabold text-indigo-950 flex items-center justify-between">
                      <span>Apply Audit Correction: {correctingProduct.product_name}</span>
                      <button
                        onClick={() => setCorrectingProduct(null)}
                        className="text-slate-400 hover:text-slate-600"
                      >
                        &times;
                      </button>
                    </div>

                    <form onSubmit={handleApplyCorrection} className="space-y-3">
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label className="block font-bold text-slate-700 mb-1">Target Stock Quantity *</label>
                          <input
                            type="number"
                            required
                            min="0"
                            value={correctionTarget}
                            onChange={(e) => setCorrectionTarget(e.target.value)}
                            className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono font-bold text-slate-800"
                          />
                        </div>
                        <div>
                          <label className="block font-bold text-slate-700 mb-1">Audit Reason * (Mandatory)</label>
                          <input
                            type="text"
                            required
                            placeholder="e.g. Physical inventory count verified by Store Manager"
                            value={correctionReason}
                            onChange={(e) => setCorrectionReason(e.target.value)}
                            className="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium text-slate-800"
                          />
                        </div>
                      </div>

                      <div className="flex items-center justify-end gap-2 pt-2">
                        <button
                          type="button"
                          onClick={() => setCorrectingProduct(null)}
                          className="px-3 py-1.5 bg-white border border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50"
                        >
                          Cancel
                        </button>
                        <button
                          type="submit"
                          disabled={correctionSubmitting}
                          className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold flex items-center gap-1.5 shadow-sm shadow-indigo-600/30 disabled:opacity-50"
                        >
                          {correctionSubmitting ? <RefreshCw className="w-3.5 h-3.5 animate-spin" /> : <ShieldCheck className="w-3.5 h-3.5" />}
                          Commit Audit Correction
                        </button>
                      </div>
                    </form>
                  </div>
                )}

              </div>
            ) : null}

            {/* Modal Footer */}
            <div className="flex items-center justify-between pt-3 border-t border-slate-100 text-xs text-slate-500">
              <button
                onClick={handleOpenReconciliation}
                className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold flex items-center gap-1.5 transition-colors"
              >
                <RefreshCw className="w-3.5 h-3.5" />
                Re-Run Check
              </button>
              <button
                onClick={() => setReconcileModalOpen(false)}
                className="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold transition-colors"
              >
                Done
              </button>
            </div>

          </div>
        </div>
      )}

      {/* MODAL: MANUAL STOCK ADJUSTMENT */}
      {adjustModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900 text-base">Manual Stock Adjustment</h3>
              <button onClick={() => setAdjustModalOpen(false)} className="text-slate-400">&times;</button>
            </div>

            <form onSubmit={handleAdjustSubmit} className="space-y-3">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Select Product *</label>
                <select
                  required
                  value={adjustForm.product_id}
                  onChange={(e) => setAdjustForm({ ...adjustForm, product_id: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium"
                >
                  <option value="">-- Choose Product --</option>
                  {products.map(p => (
                    <option key={p.id} value={p.id}>{p.name} (Current Stock: {p.stock_quantity})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Adjustment Type</label>
                  <select
                    value={adjustForm.type}
                    onChange={(e) => setAdjustForm({ ...adjustForm, type: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium"
                  >
                    <option value="Correction">Correction / Count</option>
                    <option value="Damage">Damage Loss</option>
                    <option value="Expiry">Expired Waste</option>
                    <option value="Purchase">Inbound Addition</option>
                  </select>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">New Stock Level *</label>
                  <input
                    type="number"
                    min="0"
                    required
                    value={adjustForm.quantity}
                    onChange={(e) => setAdjustForm({ ...adjustForm, quantity: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold"
                  />
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Mandatory Reason *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Broken carton, stock discrepancy, expired item"
                  value={adjustForm.reason}
                  onChange={(e) => setAdjustForm({ ...adjustForm, reason: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onClick={() => setAdjustModalOpen(false)} className="btn-outline">Cancel</button>
                <button type="submit" className="btn-glow">Record & Update Stock</button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
