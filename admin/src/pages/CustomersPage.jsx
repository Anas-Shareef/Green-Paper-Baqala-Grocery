import React, { useState, useEffect } from 'react';
import { Users, Search, ShoppingBag, MapPin, Phone, MessageSquare, ChevronRight, X } from 'lucide-react';
import { adminApi } from '../services/api';

export function CustomersPage() {
  const [customers, setCustomers] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [selectedCustomer, setSelectedCustomer] = useState(null);
  const [customerDetails, setCustomerDetails] = useState(null);
  const [loadingDetails, setLoadingDetails] = useState(false);

  const fetchCustomers = async (page = 1) => {
    setLoading(true);
    try {
      const res = await adminApi.getCustomers({ q: search, page });
      if (res && res.data) {
        setCustomers(res.data.data || res.data);
        if (res.data.current_page) {
          setPagination({
            current_page: res.data.current_page,
            last_page: res.data.last_page,
            total: res.data.total,
          });
        }
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCustomers(1);
  }, [search]);

  const handleSelectCustomer = async (cust) => {
    setSelectedCustomer(cust);
    setLoadingDetails(true);
    try {
      const res = await adminApi.getCustomer(cust.id);
      if (res && res.data) {
        setCustomerDetails(res.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingDetails(false);
    }
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Users className="w-6 h-6 text-emerald-600" />
            Customer Management
          </h1>
          <p className="text-xs text-slate-500 mt-1">Recognized phone profiles, saved addresses & order history</p>
        </div>

        {/* Search Bar */}
        <div className="relative w-full sm:w-72">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by name or phone..."
            className="w-full bg-white border border-slate-300 focus:border-emerald-600 text-slate-900 text-xs rounded-xl pl-9 pr-4 py-2.5 outline-none transition-colors shadow-xs"
          />
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
        </div>
      </div>

      {/* Customer List Table */}
      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-slate-50 border-b border-slate-200 font-bold text-slate-900 uppercase text-[10px] tracking-wider">
              <tr>
                <th className="p-4">Customer</th>
                <th className="p-4">Phone Number</th>
                <th className="p-4">Default Address</th>
                <th className="p-4 text-center">Total Orders</th>
                <th className="p-4 text-right">Total Spent</th>
                <th className="p-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="6" className="p-8 text-center text-slate-400">Loading customers...</td>
                </tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-8 text-center text-slate-400">No customers found</td>
                </tr>
              ) : (
                customers.map((c) => (
                  <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                    <td className="p-4 font-bold text-slate-900 flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center text-xs shrink-0">
                        {c.name ? c.name.charAt(0).toUpperCase() : 'C'}
                      </div>
                      <div>
                        <div>{c.name}</div>
                        <div className="text-[10px] text-slate-400">ID: #{c.id}</div>
                      </div>
                    </td>
                    <td className="p-4 font-mono font-semibold text-slate-900">{c.phone}</td>
                    <td className="p-4">
                      {c.default_address ? (
                        <div>
                          <div className="font-bold text-slate-900">{c.default_address.label}: {c.default_address.villa_number ? `Villa ${c.default_address.villa_number}` : ''}</div>
                          <div className="text-[10px] text-slate-500 line-clamp-1">{c.default_address.street_address}</div>
                        </div>
                      ) : (
                        <span className="text-slate-400 italic">No saved address</span>
                      )}
                    </td>
                    <td className="p-4 text-center">
                      <span className="bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full font-bold">
                        {c.total_orders || 0}
                      </span>
                    </td>
                    <td className="p-4 text-right font-mono font-bold text-emerald-700">
                      ₹{parseFloat(c.total_spent || 0).toFixed(2)}
                    </td>
                    <td className="p-4 text-right">
                      <button
                        onClick={() => handleSelectCustomer(c)}
                        className="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1"
                      >
                        Details <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Customer Detail Drawer Modal */}
      {selectedCustomer && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex justify-end">
          <div className="w-full max-w-lg bg-white border-l border-slate-200 h-full flex flex-col justify-between shadow-2xl p-6 overflow-y-auto space-y-6 text-slate-900">
            
            <div className="flex items-center justify-between border-b border-slate-100 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-2xl bg-emerald-600 text-white font-black text-xl flex items-center justify-center shadow-md">
                  {selectedCustomer.name ? selectedCustomer.name.charAt(0) : 'C'}
                </div>
                <div>
                  <h2 className="text-lg font-black text-slate-900">{selectedCustomer.name}</h2>
                  <div className="text-xs text-emerald-700 font-mono font-bold">{selectedCustomer.phone}</div>
                </div>
              </div>
              <button onClick={() => setSelectedCustomer(null)} className="text-slate-400 hover:text-slate-700 p-1">
                <X className="w-6 h-6" />
              </button>
            </div>

            {loadingDetails ? (
              <div className="py-12 text-center text-slate-400 text-xs">Loading customer details...</div>
            ) : (
              <div className="space-y-6 text-xs">
                
                {/* Stats Summary */}
                <div className="grid grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center">
                  <div>
                    <div className="text-slate-500 font-semibold">Total Orders</div>
                    <div className="text-lg font-black text-slate-900 font-mono">{customerDetails?.customer?.total_orders || 0}</div>
                  </div>
                  <div>
                    <div className="text-slate-500 font-semibold">Total Spent</div>
                    <div className="text-lg font-black text-emerald-700 font-mono">₹{parseFloat(customerDetails?.customer?.total_spent || 0).toFixed(2)}</div>
                  </div>
                  <div>
                    <div className="text-slate-500 font-semibold">Avg Order</div>
                    <div className="text-lg font-black text-slate-900 font-mono">₹{parseFloat(customerDetails?.customer?.average_order_value || 0).toFixed(2)}</div>
                  </div>
                </div>

                {/* Saved Address Book */}
                <div className="space-y-3">
                  <h3 className="font-extrabold text-slate-900 text-sm flex items-center gap-1.5">
                    <MapPin className="w-4 h-4 text-emerald-600" />
                    Saved Addresses ({customerDetails?.addresses?.length || 0})
                  </h3>
                  <div className="space-y-2">
                    {customerDetails?.addresses?.map((addr) => (
                      <div key={addr.id} className="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                        <div className="flex justify-between items-center font-bold text-slate-900">
                          <span>{addr.label}: {addr.villa_number ? `Villa ${addr.villa_number}` : ''}</span>
                          {addr.is_default && <span className="bg-emerald-100 text-emerald-800 text-[9px] px-2 py-0.5 rounded-full">Default</span>}
                        </div>
                        <div className="text-slate-600">{addr.street_address} {addr.zone ? `(${addr.zone})` : ''}</div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Order History */}
                <div className="space-y-3">
                  <h3 className="font-extrabold text-slate-900 text-sm flex items-center gap-1.5">
                    <ShoppingBag className="w-4 h-4 text-emerald-600" />
                    Order History ({customerDetails?.orders?.length || 0})
                  </h3>
                  <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
                    {customerDetails?.orders?.map((o) => (
                      <div key={o.id} className="p-3 bg-slate-50 border border-slate-200 rounded-xl flex justify-between items-center">
                        <div>
                          <div className="font-mono font-bold text-slate-900">{o.order_number}</div>
                          <div className="text-[10px] text-slate-500">{new Date(o.created_at).toLocaleDateString()}</div>
                        </div>
                        <div className="text-right">
                          <div className="font-mono font-bold text-emerald-700">₹{parseFloat(o.total_amount).toFixed(2)}</div>
                          <span className="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                            {o.status}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

              </div>
            )}

          </div>
        </div>
      )}

    </div>
  );
}
