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

const getApiUrl = (endpoint) => {
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  return `${getApiBaseUrl()}${cleanEndpoint}`;
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
      return res.data?.data || res.data;
    } catch (e) {
      console.error(e);
      return null;
    }
  },

  getProducts: async (params = {}) => {
    try {
      const res = await client.get(getApiUrl('/products'), { params });
      return res.data?.data || res.data;
    } catch (e) {
      console.error(e);
      return { data: [] };
    }
  },

  searchProducts: async (q) => {
    try {
      const res = await client.get(getApiUrl('/products/search'), { params: { q } });
      return res.data?.data || res.data;
    } catch (e) {
      console.error(e);
      return [];
    }
  },

  // Customer Recognition & Address Book API
  recognizeCustomer: async (phone) => {
    const res = await client.post(getApiUrl('/customer/recognize'), { phone });
    return res.data?.data || res.data;
  },

  identifyCustomer: async (phone) => {
    const res = await client.post(getApiUrl('/customer/recognize'), { phone });
    return res.data?.data || res.data;
  },

  getCustomerAddresses: async (phone) => {
    const res = await client.get(getApiUrl('/customer/addresses'), { params: { phone } });
    return res.data?.data || res.data;
  },

  createCustomerAddress: async (payload) => {
    const res = await client.post(getApiUrl('/customer/addresses'), payload);
    return res.data?.data || res.data;
  },

  updateCustomerAddress: async (id, payload) => {
    const res = await client.put(getApiUrl(`/customer/addresses/${id}`), payload);
    return res.data?.data || res.data;
  },

  deleteCustomerAddress: async (id) => {
    const res = await client.delete(getApiUrl(`/customer/addresses/${id}`));
    return res.data?.data || res.data;
  },

  setDefaultAddress: async (id) => {
    const res = await client.post(getApiUrl(`/customer/addresses/${id}/default`));
    return res.data?.data || res.data;
  },

  sendOtp: async (phone) => {
    const res = await client.post(getApiUrl('/auth/send-otp'), { phone });
    return res.data?.data || res.data;
  },

  verifyOtp: async (payload) => {
    const res = await client.post(getApiUrl('/auth/verify-otp'), payload);
    return res.data?.data || res.data;
  },

  submitOrder: async (payload) => {
    const res = await client.post(getApiUrl('/orders'), payload);
    return res.data?.data || res.data;
  },

  getOrderTracking: async (orderNumber) => {
    const res = await client.get(getApiUrl(`/orders/${orderNumber}`));
    return res.data?.data || res.data;
  },

  getCustomerHistory: async (phone) => {
    const res = await client.get(getApiUrl('/orders/history'), { params: { phone } });
    return res.data?.data || res.data;
  }
};
