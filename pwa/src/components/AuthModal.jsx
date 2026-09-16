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
    <div class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-sm shadow-2xl space-y-4">
        
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
              <Phone class="w-4 h-4" />
            </div>
            <div>
              <h3 class="font-extrabold text-white text-sm">Customer Phone OTP</h3>
              <p class="text-[10px] text-slate-400">Baqqala Verification</p>
            </div>
          </div>
          <button onClick={onClose} class="text-slate-400 hover:text-white">&times;</button>
        </div>

        {error && (
          <div class="p-3 bg-rose-950/80 border border-rose-500/50 text-rose-300 rounded-xl text-xs font-semibold">
            {error}
          </div>
        )}

        {step === 'phone' ? (
          <form onSubmit={handleSendOtp} class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Mobile Phone Number *</label>
              <input
                type="text"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
                placeholder="e.g. 971501112233"
                class="w-full bg-slate-950 border border-slate-800 text-white font-mono font-bold rounded-xl px-3 py-2.5 outline-none focus:border-emerald-500"
              />
            </div>

            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800/80 text-[11px] text-emerald-400 font-mono">
              Demo Helper: OTP code <strong class="text-white">1234</strong> will be auto-generated.
            </div>

            <button
              type="submit"
              disabled={loading}
              class="w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-500/20"
            >
              {loading ? 'Sending Code...' : 'Send Verification OTP'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyOtp} class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Enter 4-Digit OTP Code *</label>
              <input
                type="text"
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                required
                placeholder="1234"
                class="w-full bg-slate-950 border border-slate-800 text-emerald-400 font-mono font-extrabold text-center tracking-widest text-lg rounded-xl py-2 outline-none focus:border-emerald-500"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Your Full Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Muhammed Al Nuaimi"
                class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3 py-2 outline-none focus:border-emerald-500"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Villa Number / Location</label>
              <input
                type="text"
                value={villaNumber}
                onChange={(e) => setVillaNumber(e.target.value)}
                placeholder="e.g. Villa 12, Zone A"
                class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3 py-2 outline-none focus:border-emerald-500"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              class="w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-500/20"
            >
              {loading ? 'Verifying...' : 'Verify & Access Profile'}
            </button>
          </form>
        )}

      </div>
    </div>
  );
};
