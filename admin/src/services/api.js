import axios from 'axios';

const getApiBaseUrl = () => {
  if (import.meta.env.VITE_API_URL) {
    const base = import.meta.env.VITE_API_URL.replace(/\/$/, '');
    return base.endsWith('/v1') ? base : `${base}/v1`;
  }
  if (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    return 'http://localhost:8000/api/v1';
  }
  return 'https://baqqala-admin.vercel.app/api/v1';
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
  deleteCategory: async (id) => {
    const res = await client.delete(`${getApiBaseUrl()}/admin/categories/${id}`);
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
  updateOrderStatus: async (id, status, paymentStatus = null) => {
    const res = await client.put(`${getApiBaseUrl()}/admin/orders/${id}/status`, {
      status,
      payment_status: paymentStatus,
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
};
