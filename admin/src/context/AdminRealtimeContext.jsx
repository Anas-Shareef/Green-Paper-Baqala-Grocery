import React, { createContext, useContext, useState, useEffect, useRef } from 'react';
import { adminApi } from '../services/api';

const AdminRealtimeContext = createContext(null);

// Audio synth chime utility for zero-dependency sound notifications
const playNotificationSound = () => {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'sine';
    osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
    osc.frequency.setValueAtTime(880, ctx.currentTime + 0.12); // A5

    gain.gain.setValueAtTime(0.25, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start();
    osc.stop(ctx.currentTime + 0.4);
  } catch (e) {
    console.error('Audio playback error:', e);
  }
};

export function AdminRealtimeProvider({ children }) {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [pendingOrdersCount, setPendingOrdersCount] = useState(0);
  const [latestPendingOrders, setLatestPendingOrders] = useState([]);
  const [toastNotification, setToastNotification] = useState(null);
  const [soundEnabled, setSoundEnabled] = useState(true);
  const [pushSupported, setPushSupported] = useState(false);
  const [pushSubscribed, setPushSubscribed] = useState(false);

  const lastNotificationIdRef = useRef(0);
  const isInitialFetchRef = useRef(true);

  // 1. Check Native Desktop Notification support (Admin does not use sw.js)
  useEffect(() => {
    if ('Notification' in window) {
      setPushSupported(true);
      if (Notification.permission === 'granted') {
        setPushSubscribed(true);
      }
    }
  }, []);

  // Request Native Desktop Notification Permission
  const requestPushPermission = async () => {
    if (!('Notification' in window)) return false;
    
    try {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        setPushSubscribed(true);
        return true;
      }
    } catch (e) {
      console.error('Desktop notification permission error:', e);
    }
    return false;
  };

  // 2. Perform Realtime Check with Backend
  const fetchRealtimeSync = async () => {
    try {
      const res = await adminApi.realtimeCheck(lastNotificationIdRef.current);
      if (res && res.data) {
        const { 
          notifications: allNotifs,
          new_notifications, 
          unread_count, 
          pending_orders_count, 
          latest_pending_orders,
          latest_notification_id 
        } = res.data;

        setUnreadCount(unread_count || 0);
        setPendingOrdersCount(pending_orders_count || 0);
        if (latest_pending_orders) {
          setLatestPendingOrders(latest_pending_orders);
        }
        if (allNotifs) {
          setNotifications(allNotifs);
        }

        // Handle newly arrived order notifications
        if (new_notifications && new_notifications.length > 0) {
          lastNotificationIdRef.current = Math.max(
            lastNotificationIdRef.current,
            latest_notification_id || 0
          );

          // Trigger sound, toast & browser notification if NOT initial page load
          if (!isInitialFetchRef.current) {
            const latestNotif = new_notifications[new_notifications.length - 1];

            if (soundEnabled) {
              playNotificationSound();
            }

            setToastNotification(latestNotif);

            // Trigger Browser Desktop Notification
            if (window.Notification && Notification.permission === 'granted') {
              try {
                new Notification('🛒 Baqqala New Order Received', {
                  body: latestNotif.message || `${latestNotif.order_number} received`,
                  icon: '/icon.png',
                });
              } catch (err) {}
            }
          }
        } else if (isInitialFetchRef.current) {
          // Initial load: fetch existing notification history
          lastNotificationIdRef.current = latest_notification_id || 0;
          const initialNotifs = await adminApi.getNotifications();
          if (initialNotifs && initialNotifs.data?.notifications) {
            setNotifications(initialNotifs.data.notifications);
            setUnreadCount(initialNotifs.data.unread_count || 0);
          }
        }

        isInitialFetchRef.current = false;
      }
    } catch (err) {
      console.error('Realtime check error:', err);
    }
  };

  // Start polling interval across ALL admin routes
  useEffect(() => {
    fetchRealtimeSync();
    const interval = setInterval(fetchRealtimeSync, 4000);
    return () => clearInterval(interval);
  }, [soundEnabled]);

  const markNotificationRead = async (id) => {
    try {
      const res = await adminApi.markNotificationRead(id);
      if (res && res.data) {
        setUnreadCount(res.data.unread_count || 0);
        setNotifications(prev => prev.map(n => n.id === id ? { ...n, is_read: true } : n));
      }
    } catch (e) {
      console.error(e);
    }
  };

  const markAllNotificationsRead = async () => {
    try {
      await adminApi.markAllNotificationsRead();
      setUnreadCount(0);
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <AdminRealtimeContext.Provider
      value={{
        notifications,
        unreadCount,
        pendingOrdersCount,
        latestPendingOrders,
        toastNotification,
        setToastNotification,
        soundEnabled,
        setSoundEnabled,
        pushSupported,
        pushSubscribed,
        requestPushPermission,
        markNotificationRead,
        markAllNotificationsRead,
        refreshRealtime: fetchRealtimeSync,
      }}
    >
      {children}
    </AdminRealtimeContext.Provider>
  );
}

export function useAdminRealtime() {
  const context = useContext(AdminRealtimeContext);
  if (!context) {
    throw new Error('useAdminRealtime must be used within an AdminRealtimeProvider');
  }
  return context;
}
