import axios from 'axios';

const getApiBaseUrl = () => {
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL;
  }
  if (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    return 'http://localhost:8000/api';
  }
  return 'https://baqqala-admin.vercel.app/api/index.php/api';
};

const API_BASE_URL = getApiBaseUrl();

const client = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export const api = {
  getHome: async () => {
    try {
      const res = await client.get('/home');
      return res.data;
    } catch (e) {
      console.error(e);
      return null;
    }
  },

  getProducts: async (params = {}) => {
    try {
      const res = await client.get('/products', { params });
      return res.data;
    } catch (e) {
      console.error(e);
      return { data: [] };
    }
  },

  searchProducts: async (q) => {
    try {
      const res = await client.get('/products/search', { params: { q } });
      return res.data;
    } catch (e) {
      console.error(e);
      return [];
    }
  },

  sendOtp: async (phone) => {
    const res = await client.post('/auth/send-otp', { phone });
    return res.data;
  },

  verifyOtp: async (payload) => {
    const res = await client.post('/auth/verify-otp', payload);
    return res.data;
  },

  submitOrder: async (payload) => {
    const res = await client.post('/orders', payload);
    return res.data;
  },

  getOrderTracking: async (orderNumber) => {
    const res = await client.get(`/orders/${orderNumber}`);
    return res.data;
  },

  getCustomerHistory: async (phone) => {
    const res = await client.get('/orders/history', { params: { phone } });
    return res.data;
  }
};
