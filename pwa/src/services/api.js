import axios from 'axios';

const BACKEND_DOMAIN = 'https://baqqala-admin.vercel.app/api';

const getApiUrl = (endpoint) => {
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  if (import.meta.env.VITE_API_BASE_URL) {
    return `${import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '')}${cleanEndpoint}`;
  }
  if (typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    return `http://localhost:8000/api${cleanEndpoint}`;
  }
  return `${BACKEND_DOMAIN}${cleanEndpoint}`;
};

const client = axios.create({
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export const api = {
  getHome: async () => {
    try {
      const res = await client.get(getApiUrl('/home'));
      return res.data;
    } catch (e) {
      console.error(e);
      return null;
    }
  },

  getProducts: async (params = {}) => {
    try {
      const res = await client.get(getApiUrl('/products'), { params });
      return res.data;
    } catch (e) {
      console.error(e);
      return { data: [] };
    }
  },

  searchProducts: async (q) => {
    try {
      const res = await client.get(getApiUrl('/products/search'), { params: { q } });
      return res.data;
    } catch (e) {
      console.error(e);
      return [];
    }
  },

  sendOtp: async (phone) => {
    const res = await client.post(getApiUrl('/auth/send-otp'), { phone });
    return res.data;
  },

  verifyOtp: async (payload) => {
    const res = await client.post(getApiUrl('/auth/verify-otp'), payload);
    return res.data;
  },

  submitOrder: async (payload) => {
    const res = await client.post(getApiUrl('/orders'), payload);
    return res.data;
  },

  getOrderTracking: async (orderNumber) => {
    const res = await client.get(getApiUrl(`/orders/${orderNumber}`));
    return res.data;
  },

  getCustomerHistory: async (phone) => {
    const res = await client.get(getApiUrl('/orders/history'), { params: { phone } });
    return res.data;
  }
};
