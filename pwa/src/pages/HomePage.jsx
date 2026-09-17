import React from 'react';
import { ProductCard } from '../components/ProductCard';
import { Truck, Clock, Zap } from 'lucide-react';

export const HomePage = ({
  categories,
  featuredProducts,
  cart,
  onAddToCart,
  onUpdateQuantity,
  onSelectCategory,
  mov = 300
}) => {

  const getCartQuantity = (productId) => {
    const item = cart.find(i => i.product.id === productId);
    return item ? item.quantity : 0;
  };

  return (
    <div className="space-y-6 pb-6">
      
      {/* HERO PROMOTIONAL BANNER */}
      <section className="relative rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 border border-emerald-500/20 p-6 sm:p-8 shadow-xl text-white">
        <div className="max-w-md space-y-3 relative z-10">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-xs font-extrabold uppercase tracking-wider">
            <Zap className="w-3.5 h-3.5" />
            Fast Villa Delivery
          </div>
          
          <h2 className="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
            Your Everyday Grocery, Delivered to Your Villa.
          </h2>
          
          <p className="text-xs text-emerald-100 leading-relaxed font-medium">
            Fresh milk, eggs, bakery, beverages & fresh produce. Minimum order value AED {mov} for free delivery.
          </p>

          <div className="pt-2 flex items-center gap-4 text-xs font-bold text-emerald-100">
            <div className="flex items-center gap-1.5">
              <Truck className="w-4 h-4 text-white" />
              Free Delivery &ge; AED {mov}
            </div>
            <div className="flex items-center gap-1.5">
              <Clock className="w-4 h-4 text-white" />
              30-45 Mins
            </div>
          </div>
        </div>

        <div className="absolute right-0 bottom-0 top-0 opacity-20 pointer-events-none overflow-hidden hidden sm:block">
          <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80" alt="Grocery Produce" className="w-full h-full object-cover" />
        </div>
      </section>

      {/* CATEGORIES SCROLLABLE CHIPS */}
      <section className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="font-extrabold text-slate-900 text-base tracking-tight">Shop by Category</h3>
        </div>

        <div className="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-none">
          {categories.map((c) => (
            <button
              key={c.id}
              onClick={() => onSelectCategory(c)}
              className="flex flex-col items-center gap-2 p-3 bg-white border border-slate-200/80 hover:border-emerald-500/50 rounded-2xl shrink-0 w-24 transition-all group shadow-xs hover:shadow-md"
            >
              <img
                src={c.image || 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=150&q=80'}
                alt={c.name}
                className="w-12 h-12 rounded-xl object-cover group-hover:scale-110 transition-transform bg-slate-100"
              />
              <span className="text-[11px] font-bold text-slate-800 group-hover:text-emerald-700 text-center line-clamp-1">
                {c.name}
              </span>
            </button>
          ))}
        </div>
      </section>

      {/* FEATURED GROCERY ITEMS GRID */}
      <section className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="font-extrabold text-slate-900 text-base tracking-tight">Popular Daily Essentials</h3>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5">
          {featuredProducts.map((p) => (
            <ProductCard
              key={p.id}
              product={p}
              cartQuantity={getCartQuantity(p.id)}
              onAddToCart={onAddToCart}
              onUpdateQuantity={onUpdateQuantity}
            />
          ))}
        </div>
      </section>

    </div>
  );
};
