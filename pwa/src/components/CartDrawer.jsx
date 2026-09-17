import React from 'react';
import { X, ShoppingBag, ArrowRight, Plus, Minus, AlertCircle } from 'lucide-react';

export const CartDrawer = ({ isOpen, onClose, cart, onUpdateQuantity, onProceedToCheckout, mov = 300 }) => {
  if (!isOpen) return null;

  const subtotal = cart.reduce((acc, item) => acc + (item.product.retail_price * item.quantity), 0);
  const remainingForMov = Math.max(0, mov - subtotal);
  const movPercentage = Math.min(100, (subtotal / mov) * 100);
  const isMovSatisfied = subtotal >= mov;

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex justify-end transition-opacity">
      <div className="w-full max-w-md bg-white border-l border-slate-200 h-full flex flex-col justify-between shadow-2xl">
        
        {/* Cart Header */}
        <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-white">
          <div className="flex items-center gap-2">
            <ShoppingBag className="w-5 h-5 text-emerald-600" />
            <h2 className="font-extrabold text-slate-900 text-base">Your Grocery Cart</h2>
            <span className="bg-emerald-100 text-emerald-800 text-xs font-bold px-2.5 py-0.5 rounded-full">
              {cart.length} Items
            </span>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700 p-1">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* MOV Minimum Order Value Progress Bar */}
        <div className="bg-emerald-50/80 p-4 border-b border-emerald-100 space-y-2">
          {isMovSatisfied ? (
            <div className="flex items-center gap-2 text-xs font-extrabold text-emerald-800">
              <span className="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
              Minimum Order Value ₹{mov} Unlocked! FREE Villa Delivery
            </div>
          ) : (
            <div className="flex items-center justify-between text-xs font-bold text-amber-800">
              <span className="flex items-center gap-1">
                <AlertCircle className="w-3.5 h-3.5 text-amber-600" />
                Add ₹{remainingForMov.toFixed(0)} more to unlock delivery
              </span>
              <span className="font-mono">₹{subtotal.toFixed(0)} / ₹{mov}</span>
            </div>
          )}

          <div className="w-full bg-emerald-100 rounded-full h-2.5 overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-500 ${isMovSatisfied ? 'bg-emerald-600' : 'bg-amber-500'}`}
              style={{ width: `${movPercentage}%` }}
            ></div>
          </div>
        </div>

        {/* Cart Items List */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3 divide-y divide-slate-100">
          {cart.length === 0 ? (
            <div className="h-64 flex flex-col items-center justify-center text-center text-slate-500 space-y-2">
              <ShoppingBag className="w-16 h-16 text-slate-300" />
              <div className="font-bold text-slate-700 text-sm">Your cart is empty</div>
              <p className="text-xs text-slate-500 max-w-xs">Explore our fresh grocery produce and add items to start your order.</p>
            </div>
          ) : (
            cart.map((item) => (
              <div key={item.product.id} className="pt-3 first:pt-0 flex items-center justify-between gap-3">
                <img
                  src={item.product.image || 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=150&q=80'}
                  alt={item.product.name}
                  className="w-12 h-12 rounded-xl object-cover bg-slate-100 border border-slate-200 shrink-0"
                />
                <div className="flex-1 min-w-0">
                  <div className="text-xs font-bold text-slate-900 truncate">{item.product.name}</div>
                  <div className="text-[10px] text-emerald-700 font-mono font-bold mt-0.5">
                    ₹{parseFloat(item.product.retail_price).toFixed(2)} / {item.product.unit}
                  </div>
                </div>

                <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl p-1">
                  <button
                    onClick={() => onUpdateQuantity(item.product.id, item.quantity - 1)}
                    className="w-5 h-5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 flex items-center justify-center font-bold text-xs"
                  >
                    <Minus className="w-3 h-3" />
                  </button>
                  <span className="w-5 text-center font-extrabold text-xs text-slate-900 font-mono">{item.quantity}</span>
                  <button
                    onClick={() => onUpdateQuantity(item.product.id, item.quantity + 1)}
                    className="w-5 h-5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center font-bold text-xs"
                  >
                    <Plus className="w-3 h-3" />
                  </button>
                </div>

                <div className="text-right font-black text-xs text-emerald-700 font-mono w-16">
                  ₹{(item.product.retail_price * item.quantity).toFixed(2)}
                </div>
              </div>
            ))
          )}
        </div>

        {/* Cart Footer */}
        {cart.length > 0 && (
          <div className="p-4 bg-white border-t border-slate-100 space-y-3">
            <div className="space-y-1.5 text-xs text-slate-600 font-medium">
              <div className="flex justify-between">
                <span>Subtotal:</span>
                <span className="font-mono font-bold text-slate-900">₹{subtotal.toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span>Delivery Charge:</span>
                <span className="font-mono text-emerald-700 font-bold">FREE (MOV unlocked)</span>
              </div>
              <div className="flex justify-between items-center text-base font-black text-slate-900 pt-2 border-t border-slate-100">
                <span>TOTAL:</span>
                <span className="text-xl font-black text-emerald-700 font-mono">₹{subtotal.toFixed(2)}</span>
              </div>
            </div>

            <button
              disabled={!isMovSatisfied}
              onClick={onProceedToCheckout}
              className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2"
            >
              <span>Proceed to Delivery Checkout</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </div>
        )}

      </div>
    </div>
  );
};
