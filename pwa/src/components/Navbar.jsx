import React from 'react';
import { Home, Grid, ShoppingBag, Clock, User } from 'lucide-react';

export const Navbar = ({ activeTab, setActiveTab, cartCount, onOpenCart }) => {
  const tabs = [
    { id: 'home', label: 'Home', icon: Home },
    { id: 'catalog', label: 'Catalog', icon: Grid },
    { id: 'cart', label: 'Cart', icon: ShoppingBag, badge: cartCount },
    { id: 'tracking', label: 'Tracking', icon: Clock },
    { id: 'profile', label: 'Profile', icon: User },
  ];

  return (
    <nav className="sticky bottom-0 z-40 bg-white/95 backdrop-blur-xl border-t border-slate-200 px-2 py-1.5 flex items-center justify-around shadow-lg">
      {tabs.map((t) => {
        const Icon = t.icon;
        const isActive = activeTab === t.id;
        return (
          <button
            key={t.id}
            onClick={() => {
              if (t.id === 'cart') {
                onOpenCart();
              } else {
                setActiveTab(t.id);
              }
            }}
            className={`flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition-all relative ${
              isActive ? 'text-emerald-700 font-bold' : 'text-slate-500 hover:text-slate-900'
            }`}
          >
            <div className="relative">
              <Icon className="w-5 h-5" />
              {t.badge > 0 && (
                <span className="absolute -top-1.5 -right-2 bg-emerald-600 text-white font-black text-[10px] w-4 h-4 rounded-full flex items-center justify-center">
                  {t.badge}
                </span>
              )}
            </div>
            <span className="text-[10px] font-semibold">{t.label}</span>
          </button>
        );
      })}
    </nav>
  );
};
