import React, { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { 
  LayoutDashboard, Package, Tags, Warehouse, ShoppingBag, 
  Receipt, BarChart3, LogOut, Store, Menu, X, Bell, Users, Volume2, VolumeX, ShieldAlert
} from 'lucide-react';
import { adminApi } from '../services/api';
import { useAdminRealtime } from '../context/AdminRealtimeContext';

export function AdminLayout({ children }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [notifDropdownOpen, setNotifDropdownOpen] = useState(false);

  const {
    notifications,
    unreadCount,
    toastNotification,
    setToastNotification,
    soundEnabled,
    setSoundEnabled,
    pushSupported,
    pushSubscribed,
    requestPushPermission,
    markNotificationRead,
    markAllNotificationsRead,
  } = useAdminRealtime();

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
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col md:flex-row relative">
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

          <div className="flex items-center gap-3">
            
            {/* Audio Sound Toggle */}
            <button
              onClick={() => setSoundEnabled(!soundEnabled)}
              title={soundEnabled ? 'Mute Notification Chime' : 'Enable Notification Chime'}
              className="p-2 text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition-colors"
            >
              {soundEnabled ? <Volume2 className="w-5 h-5 text-emerald-600" /> : <VolumeX className="w-5 h-5 text-slate-400" />}
            </button>

            {/* Web Push Subscribe Button */}
            {pushSupported && !pushSubscribed && (
              <button
                onClick={requestPushPermission}
                className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-bold transition-colors"
              >
                <Bell className="w-3.5 h-3.5 text-amber-600" />
                Enable Notifications
              </button>
            )}

            {/* Notification Bell Dropdown Button */}
            <div className="relative">
              <button
                onClick={() => setNotifDropdownOpen(!notifDropdownOpen)}
                className="p-2.5 text-slate-600 hover:text-slate-900 rounded-xl hover:bg-slate-100 relative transition-colors"
              >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                  <span className="absolute top-1 right-1 bg-rose-600 text-white font-extrabold text-[10px] w-4 h-4 rounded-full flex items-center justify-center animate-pulse">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </button>

              {/* Notification Dropdown Menu */}
              {notifDropdownOpen && (
                <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden text-xs">
                  <div className="p-3.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between font-bold">
                    <span className="flex items-center gap-1.5 text-slate-900">
                      <Bell className="w-4 h-4 text-emerald-600" />
                      Notifications
                      {unreadCount > 0 && <span className="bg-rose-100 text-rose-800 text-[10px] px-2 py-0.5 rounded-full font-black">{unreadCount} new</span>}
                    </span>
                    {unreadCount > 0 && (
                      <button
                        onClick={markAllNotificationsRead}
                        className="text-[11px] text-emerald-700 hover:underline font-semibold"
                      >
                        Mark all read
                      </button>
                    )}
                  </div>

                  <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
                    {notifications.length === 0 ? (
                      <div className="p-6 text-center text-slate-400 italic">No notifications yet</div>
                    ) : (
                      notifications.map((n) => (
                        <div
                          key={n.id}
                          className={`p-3.5 transition-colors flex items-start justify-between gap-3 ${
                            n.is_read ? 'bg-white' : 'bg-emerald-50/50 font-medium'
                          }`}
                        >
                          <div className="space-y-1">
                            <div className="font-bold text-slate-900 flex items-center gap-1.5">
                              🛒 {n.title || 'New Order'}
                              {!n.is_read && <span className="w-2 h-2 rounded-full bg-emerald-600"></span>}
                            </div>
                            <div className="text-[11px] text-slate-600 leading-snug">{n.message}</div>
                            <div className="text-[10px] text-slate-400 font-mono">
                              {new Date(n.created_at || Date.now()).toLocaleTimeString()}
                            </div>
                          </div>

                          <button
                            onClick={() => {
                              markNotificationRead(n.id);
                              setNotifDropdownOpen(false);
                              navigate('/orders');
                            }}
                            className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] uppercase tracking-wider shrink-0 transition-colors"
                          >
                            View Order
                          </button>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              )}
            </div>

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

      {/* Slide-In Desktop Toast Notification Popup (PRD Section 20) */}
      {toastNotification && (
        <div className="fixed bottom-6 right-6 z-50 max-w-sm w-full bg-white border-2 border-emerald-600 rounded-3xl shadow-2xl p-5 space-y-3 animate-in slide-in-from-bottom-5 duration-300 text-slate-900">
          <div className="flex items-center justify-between border-b border-slate-100 pb-2">
            <span className="text-xs font-black text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
              🛒 NEW ORDER RECEIVED
            </span>
            <button
              onClick={() => setToastNotification(null)}
              className="text-slate-400 hover:text-slate-700 p-0.5"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="space-y-1">
            <div className="text-lg font-black font-mono text-slate-900">
              {toastNotification.order_number}
            </div>
            <div className="text-xs text-slate-700 font-bold">
              {toastNotification.message}
            </div>
          </div>

          <div className="pt-2 flex gap-2">
            <button
              onClick={() => {
                setToastNotification(null);
                navigate('/orders');
              }}
              className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-sm text-center transition-colors"
            >
              View Order
            </button>
            <button
              onClick={() => setToastNotification(null)}
              className="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
            >
              Dismiss
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
