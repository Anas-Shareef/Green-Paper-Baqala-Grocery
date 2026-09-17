import React, { useState } from 'react';
import { X, Phone, Lock, CheckCircle2 } from 'lucide-react';
import { api } from '../services/api';

export const AuthModal = ({ isOpen, onClose, onAuthenticated }) => {
  const [step, setStep] = useState('phone'); // phone, otp
  const [phone, setPhone] = useState('971501112233');
  const [otp, setOtp] = useState('1234');
  const [name, setName] = useState('');
  const [villaNumber, setVillaNumber] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  if (!isOpen) return null;

  const handleSendOtp = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api.sendOtp(phone);
      if (res.success) {
        setStep('otp');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to send verification code.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api.verifyOtp({ phone, otp, name, villa_number: villaNumber });
      if (res.success) {
        onAuthenticated(res.customer);
        onClose();
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid OTP code. Try demo code 1234.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-sm shadow-2xl space-y-4 text-slate-900">
        
        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
              <Phone className="w-4 h-4" />
            </div>
            <div>
              <h3 className="font-extrabold text-slate-900 text-sm">Customer Phone OTP</h3>
              <p className="text-[10px] text-slate-500 font-medium">Baqqala Verification</p>
            </div>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700 font-bold">&times;</button>
        </div>

        {error && (
          <div className="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-semibold">
            {error}
          </div>
        )}

        {step === 'phone' ? (
          <form onSubmit={handleSendOtp} className="space-y-3 text-xs">
            <div>
              <label className="block font-bold text-slate-700 mb-1">Mobile Phone Number *</label>
              <input
                type="text"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
                placeholder="e.g. 971501112233"
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 font-mono font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <div className="bg-emerald-50 p-3 rounded-xl border border-emerald-100 text-[11px] text-emerald-800 font-mono">
              Demo Helper: OTP code <strong className="text-emerald-950 font-bold">1234</strong> will be auto-generated.
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20"
            >
              {loading ? 'Sending Code...' : 'Send Verification OTP'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyOtp} className="space-y-3 text-xs">
            <div>
              <label className="block font-bold text-slate-700 mb-1">Enter 4-Digit OTP Code *</label>
              <input
                type="text"
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                required
                placeholder="1234"
                className="w-full bg-slate-50 border border-slate-300 text-emerald-700 font-mono font-extrabold text-center tracking-widest text-lg rounded-xl py-2 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Your Full Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Muhammed Al Nuaimi"
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <div>
              <label className="block font-bold text-slate-700 mb-1">Villa Number / Location</label>
              <input
                type="text"
                value={villaNumber}
                onChange={(e) => setVillaNumber(e.target.value)}
                placeholder="e.g. Villa 12, Zone A"
                className="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl px-3 py-2 outline-none focus:border-emerald-600 focus:bg-white transition-colors"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20"
            >
              {loading ? 'Verifying...' : 'Verify & Access Profile'}
            </button>
          </form>
        )}

      </div>
    </div>
  );
};
