import React, { useState, useEffect } from 'react';
import { Header } from './components/Header';
import { Navbar } from './components/Navbar';
import { CartDrawer } from './components/CartDrawer';
import { AuthModal } from './components/AuthModal';

import { HomePage } from './pages/HomePage';
import { CatalogPage } from './pages/CatalogPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { TrackingPage } from './pages/TrackingPage';
import { ProfilePage } from './pages/ProfilePage';

import { api } from './services/api';

export function App() {
  const [activeTab, setActiveTab] = useState('home'); // home, catalog, checkout, tracking, profile
  const [homeData, setHomeData] = useState(null);
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [cart, setCart] = useState([]);
  const [customer, setCustomer] = useState(null);
  const [currentOrder, setCurrentOrder] = useState(null);

  const [isCartOpen, setIsCartOpen] = useState(false);
  const [isAuthOpen, setIsAuthOpen] = useState(false);

  useEffect(() => {
    api.getHome().then((data) => {
      if (data) {
        setHomeData(data);
        setCategories(data.categories || []);
        setProducts(data.featured_products || []);
      }
    });
  }, []);

  const handleSearch = (q) => {
    if (q.trim().length > 0) {
      api.searchProducts(q).then((res) => {
        setProducts(res || []);
        setActiveTab('catalog');
      });
    } else if (homeData) {
      setProducts(homeData.featured_products || []);
    }
  };

  const handleAddToCart = (product) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === product.id);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id ? { ...i, quantity: i.quantity + 1 } : i
        );
      }
      return [...prev, { product, quantity: 1 }];
    });
  };

  const handleUpdateQuantity = (productId, quantity) => {
    if (quantity <= 0) {
      setCart((prev) => prev.filter((i) => i.product.id !== productId));
    } else {
      setCart((prev) =>
        prev.map((i) => (i.product.id === productId ? { ...i, quantity } : i))
      );
    }
  };

  const handleOrderSuccess = (order) => {
    setCurrentOrder(order);
    setCart([]);
    setIsCartOpen(false);
    setActiveTab('tracking');
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col justify-between font-sans">
      
      {/* Top Header */}
      <Header
        onSearch={handleSearch}
        cartCount={cart.reduce((a, b) => a + b.quantity, 0)}
        onOpenCart={() => setIsCartOpen(true)}
        customer={customer}
        onOpenAuth={() => setIsAuthOpen(true)}
      />

      {/* Main View Container */}
      <main className="flex-1 p-4 max-w-6xl mx-auto w-full">
        {activeTab === 'home' && (
          <HomePage
            categories={categories}
            featuredProducts={products}
            cart={cart}
            onAddToCart={handleAddToCart}
            onUpdateQuantity={handleUpdateQuantity}
            onSelectCategory={(cat) => {
              api.getProducts({ category_id: cat.id }).then((res) => {
                setProducts(res.data || []);
                setActiveTab('catalog');
              });
            }}
            mov={homeData?.minimum_order_value || 300}
          />
        )}

        {activeTab === 'catalog' && (
          <CatalogPage
            categories={categories}
            products={products}
            cart={cart}
            onAddToCart={handleAddToCart}
            onUpdateQuantity={handleUpdateQuantity}
          />
        )}

        {activeTab === 'checkout' && (
          <CheckoutPage
            cart={cart}
            customer={customer}
            onOrderSuccess={handleOrderSuccess}
            onBackToCart={() => setIsCartOpen(true)}
          />
        )}

        {activeTab === 'tracking' && (
          <TrackingPage currentOrder={currentOrder} />
        )}

        {activeTab === 'profile' && (
          <ProfilePage
            customer={customer}
            onOpenAuth={() => setIsAuthOpen(true)}
            onSelectOrderForTracking={(o) => {
              setCurrentOrder(o);
              setActiveTab('tracking');
            }}
          />
        )}
      </main>

      {/* Cart Drawer Popup */}
      <CartDrawer
        isOpen={isCartOpen}
        onClose={() => setIsCartOpen(false)}
        cart={cart}
        onUpdateQuantity={handleUpdateQuantity}
        onProceedToCheckout={() => {
          setIsCartOpen(false);
          setActiveTab('checkout');
        }}
        mov={homeData?.minimum_order_value || 300}
      />

      {/* Auth OTP Modal Popup */}
      <AuthModal
        isOpen={isAuthOpen}
        onClose={() => setIsAuthOpen(false)}
        onAuthenticated={(cust) => setCustomer(cust)}
      />

      {/* Bottom Glassmorphic Navigation Bar */}
      <Navbar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        cartCount={cart.reduce((a, b) => a + b.quantity, 0)}
        onOpenCart={() => setIsCartOpen(true)}
      />

    </div>
  );
}

export default App;
