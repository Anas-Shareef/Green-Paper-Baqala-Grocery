import React, { useState, useEffect } from 'react';

export function PwaInstallBanner() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [showBanner, setShowBanner] = useState(false);
  const [isIos, setIsIos] = useState(false);
  const [showIosModal, setShowIosModal] = useState(false);
  const [showDesktopModal, setShowDesktopModal] = useState(false);

  useEffect(() => {
    // Check if dismissed previously
    const dismissed = localStorage.getItem('baqqala_install_prompt_dismissed');
    
    // Check if running in standalone mode (already installed)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    
    if (isStandalone) return;

    // Detect iOS
    const userAgent = window.navigator.userAgent.toLowerCase();
    const iosDevice = /iphone|ipad|ipod/.test(userAgent);
    setIsIos(iosDevice);

    // Show banner by default for all platforms on first visit unless dismissed
    if (!dismissed) {
      setShowBanner(true);
    }

    const handleBeforeInstallPrompt = (e) => {
      e.preventDefault();
      setDeferredPrompt(e);
      if (!dismissed) {
        setShowBanner(true);
      }
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    };
  }, []);

  const handleInstallClick = () => {
    if (isIos) {
      setShowIosModal(true);
      return;
    }

    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          setShowBanner(false);
        }
        setDeferredPrompt(null);
      });
    } else {
      setShowDesktopModal(true);
    }
  };

  const handleDismiss = () => {
    localStorage.setItem('baqqala_install_prompt_dismissed', 'true');
    setShowBanner(false);
  };

  if (!showBanner) return null;

  return (
    <>
      {/* Onboarding Install Banner - Prominently Displayed */}
      <div className="bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 text-white p-4 rounded-3xl shadow-xl border border-emerald-400/30 mb-5 flex items-center justify-between gap-3 animate-fade-in">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl shrink-0 shadow-inner">
            🛒
          </div>
          <div>
            <h4 className="font-black text-sm text-white leading-tight">Install Baqqala App</h4>
            <p className="text-xs text-emerald-100 font-medium">Quick access from home screen & order updates.</p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button
            onClick={handleInstallClick}
            className="bg-white text-emerald-800 hover:bg-emerald-50 text-xs font-black px-4 py-2.5 rounded-xl shadow-md transition active:scale-95 flex items-center gap-1.5"
          >
            <span>Install Baqqala</span>
          </button>
          <button
            onClick={handleDismiss}
            className="text-emerald-200 hover:text-white p-1 text-xs font-bold"
            title="Maybe Later"
          >
            ✕
          </button>
        </div>
      </div>

      {/* iOS Installation Instruction Modal */}
      {showIosModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 text-slate-800 shadow-2xl border border-slate-100">
            <div className="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600 text-2xl mx-auto mb-3">
              📱
            </div>
            <h3 className="text-lg font-bold text-slate-900 text-center mb-1">Install Baqqala on iPhone / iPad</h3>
            <p className="text-xs text-slate-500 text-center mb-5">Follow these 3 quick steps in Safari:</p>

            <div className="space-y-3 mb-6 text-sm">
              <div className="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-100">
                <span className="w-7 h-7 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shrink-0">1</span>
                <span className="text-slate-700 font-medium">Tap the <strong>Share</strong> button <span className="text-emerald-600 font-bold">⎋</span> or <span className="text-emerald-600 font-bold">↗</span></span>
              </div>
              <div className="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-100">
                <span className="w-7 h-7 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shrink-0">2</span>
                <span className="text-slate-700 font-medium">Scroll down & tap <strong>Add to Home Screen</strong></span>
              </div>
              <div className="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-100">
                <span className="w-7 h-7 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shrink-0">3</span>
                <span className="text-slate-700 font-medium">Tap <strong>Add</strong> in top right</span>
              </div>
            </div>

            <button
              onClick={() => {
                setShowIosModal(false);
                handleDismiss();
              }}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-md transition active:scale-95"
            >
              Got it!
            </button>
          </div>
        </div>
      )}

      {/* Desktop / Chromium Fallback Modal */}
      {showDesktopModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 text-slate-800 shadow-2xl border border-slate-100 text-center">
            <div className="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600 text-2xl mx-auto mb-3">
              💻
            </div>
            <h3 className="text-lg font-bold text-slate-900 mb-1">Install Baqqala App</h3>
            <p className="text-xs text-slate-600 mb-4">
              Look at your browser's address bar or menu and click the <strong>Install Baqqala</strong> icon <span className="text-emerald-600 font-bold">⊕</span> to add Baqqala to your desktop/phone.
            </p>
            <button
              onClick={() => {
                setShowDesktopModal(false);
                handleDismiss();
              }}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-md transition active:scale-95"
            >
              Okay, Understood
            </button>
          </div>
        </div>
      )}
    </>
  );
}
