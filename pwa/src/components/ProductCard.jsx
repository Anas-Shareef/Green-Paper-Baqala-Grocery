import React from 'react';
import { Plus, Minus } from 'lucide-react';

export const ProductCard = ({ product, cartQuantity = 0, onAddToCart, onUpdateQuantity }) => {
  const isOutOfStock = product.stock_quantity <= 0;

  return (
    <div className="bg-white border border-slate-200/80 hover:border-emerald-500/50 rounded-2xl p-3 flex flex-col justify-between transition-all group relative overflow-hidden shadow-xs hover:shadow-md">
      
      {/* Stock Badge */}
      <div className="absolute top-2 left-2 z-10">
        {isOutOfStock ? (
          <span className="bg-rose-600 text-white text-[9px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider shadow-xs">
            Out of Stock
          </span>
        ) : product.stock_quantity <= product.minimum_stock_level ? (
          <span className="bg-amber-500 text-white text-[9px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider shadow-xs">
            Low Stock: {product.stock_quantity}
          </span>
        ) : null}
      </div>

      {/* Product Image */}
      <div className="w-full h-32 rounded-xl overflow-hidden bg-slate-100 mb-2 relative">
        <img
          src={product.image || 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=300&q=80'}
          alt={product.name}
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          loading="lazy"
        />
      </div>

      {/* Details */}
      <div className="space-y-1">
        <div className="text-[10px] text-emerald-700 font-semibold uppercase tracking-wider truncate">
          {product.category?.name || product.brand || 'Grocery'}
        </div>
        <h3 className="text-xs font-bold text-slate-900 line-clamp-2 leading-snug group-hover:text-emerald-700 transition-colors">
          {product.name}
        </h3>
        <div className="text-[10px] text-slate-500 font-medium">
          {product.unit}
        </div>
      </div>

      {/* Price & Cart Actions */}
      <div className="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
        <div className="font-black text-sm text-emerald-700 font-mono">
          ₹{parseFloat(product.retail_price).toFixed(2)}
        </div>

        {isOutOfStock ? (
          <button disabled className="px-2.5 py-1 bg-slate-100 text-slate-400 text-[10px] font-bold rounded-lg cursor-not-allowed">
            Unavailable
          </button>
        ) : cartQuantity > 0 ? (
          <div className="flex items-center gap-1.5 bg-slate-50 border border-emerald-300 rounded-xl p-1">
            <button
              onClick={() => onUpdateQuantity(product.id, cartQuantity - 1)}
              className="w-5 h-5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 flex items-center justify-center font-bold text-xs"
            >
              <Minus className="w-3 h-3" />
            </button>
            <span className="w-5 text-center font-extrabold text-xs text-slate-900 font-mono">{cartQuantity}</span>
            <button
              onClick={() => onUpdateQuantity(product.id, cartQuantity + 1)}
              className="w-5 h-5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center font-bold text-xs"
            >
              <Plus className="w-3 h-3" />
            </button>
          </div>
        ) : (
          <button
            onClick={() => onAddToCart(product)}
            className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl transition-all shadow-xs flex items-center gap-1"
          >
            <Plus className="w-3.5 h-3.5" />
            Add
          </button>
        )}
      </div>

    </div>
  );
};
