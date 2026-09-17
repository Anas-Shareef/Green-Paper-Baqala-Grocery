import React, { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { 
  LayoutDashboard, Package, Tags, Warehouse, ShoppingBag, 
  Receipt, BarChart3, LogOut, Store, Menu, X, Bell, Users 
} from 'lucide-react';
import { adminApi } from '../services/api';

export function AdminLayout({ children }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navItems = [
    { label: 'Dashboard', icon: LayoutDashboard, path: '/dashboard' },
    { label: 'Products', icon: Package, path: '/products' },
    { label: 'Categories', icon: Tags, path: '/categories' },
    { label: 'Inventory', icon: Warehouse, path: '/inventory' },
    { label: 'Orders', icon: ShoppingBag, path: '/orders' },
    { label: 'Customers', icon: Users, path: '/customers' },
    { label: 'Expenses', icon: Receipt, path: '/expenses' },
    { label: 'Reports', icon: BarChart3, path: '/reports' },
  ];

  const handleLogout = async () => {
    try {
      await adminApi.logout();
    } catch (e) {
      console.error(e);
    }
    localStorage.removeItem('admin_token');
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col md:flex-row">
      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 transform ${mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'} md:translate-x-0 transition-transform duration-200 ease-in-out flex flex-col shadow-xs`}>
        <div className="p-5 border-b border-slate-100 flex items-center justify-between">
          <Link to="/dashboard" className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-700 font-bold">
              <Store className="w-5 h-5" />
            </div>
            <div>
              <h1 className="font-bold text-lg text-slate-900 leading-tight">Baqqala Admin</h1>
              <p className="text-xs text-emerald-700 font-semibold">Grocery Portal</p>
            </div>
          </Link>
          <button className="md:hidden text-slate-400 hover:text-slate-700" onClick={() => setMobileMenuOpen(false)}>
            <X className="w-6 h-6" />
          </button>
        </div>

        <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
          {navItems.map((item) => {
            const isActive = location.pathname === item.path;
            const Icon = item.icon;
            return (
              <Link
                key={item.path}
                to={item.path}
                onClick={() => setMobileMenuOpen(false)}
                className={`flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm transition-all ${
                  isActive
                    ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/20'
                    : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-800'
                }`}
              >
                <Icon className="w-5 h-5" />
                {item.label}
              </Link>
            );
          })}
        </nav>

        <div className="p-4 border-t border-slate-100">
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-rose-600 hover:bg-rose-50 transition-colors"
          >
            <LogOut className="w-5 h-5" />
            Sign Out
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 md:pl-64 flex flex-col min-h-screen">
        {/* Top Header */}
        <header className="h-16 border-b border-slate-200 bg-white/90 backdrop-blur sticky top-0 z-40 px-6 flex items-center justify-between shadow-xs">
          <div className="flex items-center gap-4">
            <button className="md:hidden text-slate-500 hover:text-slate-800" onClick={() => setMobileMenuOpen(true)}>
              <Menu className="w-6 h-6" />
            </button>
            <h2 className="text-sm font-bold text-slate-800 capitalize">
              {location.pathname.replace('/', '') || 'Dashboard'}
            </h2>
          </div>

          <div className="flex items-center gap-4">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
              <span className="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
              Live Admin
            </span>
          </div>
        </header>

        {/* Main Body */}
        <main className="flex-1 p-6 md:p-8 space-y-6">
          {children}
        </main>
      </div>
    </div>
  );
}
