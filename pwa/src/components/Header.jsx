import React, { useState } from 'react';
import { Search, ShoppingBag, MapPin } from 'lucide-react';

export const Header = ({ onSearch, cartCount, onOpenCart, customer, onOpenAuth }) => {
  const [searchTerm, setSearchTerm] = useState('');

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    onSearch(searchTerm);
  };

  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-xl border-b border-slate-200/80 shadow-xs">
      
      {/* Top Delivery & Customer Address Bar */}
      <div className="bg-emerald-50/80 px-4 py-2 border-b border-emerald-100 flex items-center justify-between text-xs">
        <div className="flex items-center gap-2 text-slate-700">
          <MapPin className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
          <span className="truncate">
            Delivering to: <strong className="text-slate-900">{customer?.villa_number ? `${customer.villa_number}, ${customer.zone || 'Zone A'}` : 'Set Villa Location'}</strong>
          </span>
        </div>
        
        <button onClick={onOpenAuth} className="text-emerald-700 hover:underline font-semibold flex items-center gap-1">
          {customer ? customer.name.split(' ')[0] : 'Sign In / OTP'}
        </button>
      </div>

      {/* Main Header Bar */}
      <div className="px-4 py-3 flex items-center justify-between gap-3">
        
        {/* Brand Logo */}
        <div className="flex items-center gap-2 shrink-0">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-emerald-500 flex items-center justify-center font-black text-xl text-white shadow-md shadow-emerald-600/20">
            B
          </div>
          <div>
            <h1 className="font-extrabold text-base text-slate-900 tracking-tight leading-none">Baqqala</h1>
            <p className="text-[10px] text-emerald-700 font-semibold mt-0.5">Everyday Grocery</p>
          </div>
        </div>

        {/* Instant Search Bar */}
        <form onSubmit={handleSearchSubmit} className="flex-1 max-w-md relative">
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value);
              onSearch(e.target.value);
            }}
            placeholder="Search milk, bread, apples, chips..."
            className="w-full bg-slate-50 border border-slate-300 focus:border-emerald-600 text-slate-900 text-xs rounded-xl pl-9 pr-4 py-2.5 outline-none transition-all shadow-xs"
          />
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
        </form>

        {/* Cart Icon Trigger */}
        <button
          onClick={onOpenCart}
          className="relative p-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl transition-all flex items-center justify-center shrink-0"
        >
          <ShoppingBag className="w-5 h-5" />
          {cartCount > 0 && (
            <span className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-emerald-600 text-white font-black text-[11px] rounded-full flex items-center justify-center shadow-md shadow-emerald-600/30">
              {cartCount}
            </span>
          )}
        </button>

      </div>
    </header>
  );
};
