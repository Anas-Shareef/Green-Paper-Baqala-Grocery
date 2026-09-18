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

const memCache = new Map();

export const api = {
  getHome: async () => {
    try {
      const res = await client.get(getApiUrl('/home'));
      const data = res.data?.data || res.data;
      if (data) {
        try {
          localStorage.setItem('baqqala_home_cache', JSON.stringify(data));
        } catch (_) {}
      }
      return data;
    } catch (e) {
      console.error('getHome error, attempting local cache fallback:', e);
      try {
        const saved = localStorage.getItem('baqqala_home_cache');
        if (saved) return JSON.parse(saved);
      } catch (_) {}
      return null;
    }
  },

  getProducts: async (params = {}) => {
    const key = `products_${JSON.stringify(params)}`;
    if (memCache.has(key)) {
      const entry = memCache.get(key);
      if (Date.now() - entry.time < 60000) {
        return entry.data;
      }
    }
    try {
      const res = await client.get(getApiUrl('/products'), { params });
      const data = res.data?.data || res.data;
      memCache.set(key, { data, time: Date.now() });
      return data;
    } catch (e) {
      console.error(e);
      return { data: [] };
    }
  },

  searchProducts: async (q) => {
    const key = `search_${q.toLowerCase().trim()}`;
    if (memCache.has(key)) {
      const entry = memCache.get(key);
      if (Date.now() - entry.time < 30000) {
        return entry.data;
      }
    }
    try {
      const res = await client.get(getApiUrl('/products/search'), { params: { q } });
      const data = res.data?.data || res.data;
      memCache.set(key, { data, time: Date.now() });
      return data;
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
