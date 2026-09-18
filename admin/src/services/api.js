import axios from 'axios';

const getApiBaseUrl = () => {
  if (import.meta.env.VITE_API_URL) {
    const base = import.meta.env.VITE_API_URL.replace(/\/$/, '');
    return base.endsWith('/v1') ? base : (base.endsWith('/api') ? `${base}/v1` : `${base}/api/v1`);
  }
  if (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    return 'http://localhost:8000/api/v1';
  }
  // Relative API route (resolved via Vercel proxy rewrite or same-origin)
  return '/api/v1';
};

const client = axios.create({
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('admin_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const adminApi = {
  // Auth
  login: async (email, password) => {
    const res = await client.post(`${getApiBaseUrl()}/auth/login`, { email, password });
    return res.data;
  },
  getMe: async () => {
    const res = await client.get(`${getApiBaseUrl()}/auth/me`);
    return res.data;
  },
  logout: async () => {
    const res = await client.post(`${getApiBaseUrl()}/auth/logout`);
    localStorage.removeItem('admin_token');
    return res.data;
  },

  // Dashboard
  getDashboard: async () => {
    const res = await client.get(`${getApiBaseUrl()}/admin/dashboard`);
    return res.data;
  },

  // Products
  getProducts: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/products`, { params });
    return res.data;
  },
  saveProduct: async (formData, id = null) => {
    const url = id ? `${getApiBaseUrl()}/admin/products/${id}` : `${getApiBaseUrl()}/admin/products`;
    const res = await client.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
  },
  deleteProduct: async (id) => {
    const res = await client.delete(`${getApiBaseUrl()}/admin/products/${id}`);
    return res.data;
  },
  bulkDeleteProducts: async (productIds) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/products/bulk-delete`, { product_ids: productIds });
    return res.data;
  },
  bulkChangeCategory: async (productIds, categoryId) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/products/bulk-category`, { product_ids: productIds, category_id: categoryId });
    return res.data;
  },
  bulkChangeStatus: async (productIds, status) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/products/bulk-status`, { product_ids: productIds, status });
    return res.data;
  },
  importProducts: async (formData) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/products/import`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
  },
  getProductImportTemplateUrl: () => `${getApiBaseUrl()}/admin/products/import-template`,
  getProductExportUrl: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return `${getApiBaseUrl()}/admin/products/export${query ? `?${query}` : ''}`;
  },

  // Categories
  getCategories: async () => {
    const res = await client.get(`${getApiBaseUrl()}/admin/categories`);
    return res.data;
  },
  saveCategory: async (formData, id = null) => {
    const url = id ? `${getApiBaseUrl()}/admin/categories/${id}` : `${getApiBaseUrl()}/admin/categories`;
    const res = await client.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
  },
  deleteCategory: async (id, params = {}) => {
    const res = await client.delete(`${getApiBaseUrl()}/admin/categories/${id}`, { params });
    return res.data;
  },

  // Inventory & Stock Adjustments
  getInventory: async () => {
    const res = await client.get(`${getApiBaseUrl()}/admin/inventory`);
    return res.data;
  },
  adjustStock: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/inventory/adjustments`, payload);
    return res.data;
  },

  // Orders
  getOrders: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/orders`, { params });
    return res.data;
  },
  getOrder: async (id) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/orders/${id}`);
    return res.data;
  },
  updateOrderStatus: async (id, status, paymentStatus = null, reason = null) => {
    const res = await client.put(`${getApiBaseUrl()}/admin/orders/${id}/status`, {
      status,
      payment_status: paymentStatus,
      reason,
    });
    return res.data;
  },
  confirmOrder: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/confirm`);
    return res.data;
  },
  prepareOrder: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/prepare`);
    return res.data;
  },
  readyOrder: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/ready`);
    return res.data;
  },
  assignDriver: async (id, driverId) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/assign-driver`, { driver_id: driverId });
    return res.data;
  },
  dispatchOrder: async (id, driverId = null) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/dispatch`, { driver_id: driverId });
    return res.data;
  },
  deliverOrder: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/deliver`);
    return res.data;
  },
  collectPayment: async (id, amount, diffReason = null) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/payment`, {
      amount,
      difference_reason: diffReason,
    });
    return res.data;
  },
  failDelivery: async (id, reason, notes = null) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/fail-delivery`, {
      reason,
      notes,
    });
    return res.data;
  },
  cancelOrder: async (id, reason) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/cancel`, { reason });
    return res.data;
  },
  pickItem: async (id, orderItemId, quantity) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/${id}/pick-item`, {
      order_item_id: orderItemId,
      quantity,
    });
    return res.data;
  },
  getOrderActivity: async (id) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/orders/${id}/activity`);
    return res.data;
  },
  bulkActionOrders: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/bulk-action`, payload);
    return res.data;
  },
  getOrderImportTemplateUrl: () => `${getApiBaseUrl()}/admin/orders/import-template`,
  getOrderExportUrl: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return `${getApiBaseUrl()}/admin/orders/export${query ? `?${query}` : ''}`;
  },
  importOrders: async (formData) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/orders/import`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
  },

  // Customers Management
  getCustomers: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/customers`, { params });
    return res.data;
  },
  getCustomer: async (id) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/customers/${id}`);
    return res.data;
  },
  updateCustomer: async (id, payload) => {
    const res = await client.put(`${getApiBaseUrl()}/admin/customers/${id}`, payload);
    return res.data;
  },

  // Expenses
  getExpenses: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/expenses`, { params });
    return res.data;
  },
  saveExpense: async (formData) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/expenses`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
  },
  deleteExpense: async (id) => {
    const res = await client.delete(`${getApiBaseUrl()}/admin/expenses/${id}`);
    return res.data;
  },

  // Reports
  getReports: async (period = 'this_month', startDate = null, endDate = null) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/reports`, {
      params: { period, start_date: startDate, end_date: endDate },
    });
    return res.data;
  },

  // Realtime Notifications & Push API
  getNotifications: async () => {
    const res = await client.get(`${getApiBaseUrl()}/admin/notifications`);
    return res.data;
  },
  realtimeCheck: async (sinceId = 0) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/realtime-check`, { params: { since_id: sinceId } });
    return res.data;
  },
  markNotificationRead: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/notifications/${id}/read`);
    return res.data;
  },
  markAllNotificationsRead: async () => {
    const res = await client.post(`${getApiBaseUrl()}/admin/notifications/mark-all-read`);
    return res.data;
  },
  subscribePush: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/push-subscriptions`, payload);
    return res.data;
  },

  // Stock Receiving Station / Goods Received Note (GRN) API
  getReceivingHistory: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/receiving`, { params });
    return res.data;
  },
  getReceivingKPIs: async () => {
    const res = await client.get(`${getApiBaseUrl()}/admin/receiving/kpis`);
    return res.data;
  },
  getReceivingDetails: async (id) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/receiving/${id}`);
    return res.data;
  },
  createReceiving: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving`, payload);
    return res.data;
  },
  updateReceiving: async (id, payload) => {
    const res = await client.put(`${getApiBaseUrl()}/admin/receiving/${id}`, payload);
    return res.data;
  },
  confirmReceiving: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving/${id}/confirm`);
    return res.data;
  },
  cancelReceiving: async (id) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving/${id}/cancel`);
    return res.data;
  },
  returnReceivingStock: async (id, payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving/${id}/return`, payload);
    return res.data;
  },
  getSuppliers: async (params = {}) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/receiving/suppliers`, { params });
    return res.data;
  },
  createSupplier: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving/suppliers`, payload);
    return res.data;
  },
  lookupBarcode: async (barcode) => {
    const res = await client.get(`${getApiBaseUrl()}/admin/products/barcode/${encodeURIComponent(barcode)}`);
    return res.data;
  },
  quickCreateProduct: async (payload) => {
    const res = await client.post(`${getApiBaseUrl()}/admin/receiving/quick-product`, payload);
    return res.data;
  },
};
