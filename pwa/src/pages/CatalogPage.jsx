import React, { useState } from 'react';
import { ProductCard } from '../components/ProductCard';

export const CatalogPage = ({ categories, products, cart, onAddToCart, onUpdateQuantity }) => {
  const [selectedCatId, setSelectedCatId] = useState(null);

  const filteredProducts = selectedCatId
    ? products.filter(p => p.category_id === selectedCatId)
    : products;

  const getCartQuantity = (productId) => {
    const item = cart.find(i => i.product.id === productId);
    return item ? item.quantity : 0;
  };

  return (
    <div className="space-y-4 pb-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-extrabold text-white">All Grocery Catalog</h2>
        <span className="text-xs text-slate-400 font-semibold">{filteredProducts.length} Items</span>
      </div>

      {/* Category Filter Pills */}
      <div className="flex items-center gap-2 overflow-x-auto pb-2">
        <button
          onClick={() => setSelectedCatId(null)}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 ${
            selectedCatId === null ? 'bg-emerald-500 text-slate-950 shadow-md' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
          }`}
        >
          All Items
        </button>

        {categories.map((c) => (
          <button
            key={c.id}
            onClick={() => setSelectedCatId(c.id)}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 ${
              selectedCatId === c.id ? 'bg-emerald-500 text-slate-950 shadow-md' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
            }`}
          >
            {c.name}
          </button>
        ))}
      </div>

      {/* Product Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5">
        {filteredProducts.map((p) => (
          <ProductCard
            key={p.id}
            product={p}
            cartQuantity={getCartQuantity(p.id)}
            onAddToCart={onAddToCart}
            onUpdateQuantity={onUpdateQuantity}
          />
        ))}
      </div>
    </div>
  );
};
