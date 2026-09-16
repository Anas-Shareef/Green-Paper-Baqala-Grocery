import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

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
