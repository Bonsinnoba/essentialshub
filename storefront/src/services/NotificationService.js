/**
 * EssentialsHub - Push Notification Manager
 * Handles Service Worker registration and user subscription.
 */

const VAPID_PUBLIC_KEY = "BNv-Z7fA4B-Qh9jP9g-5ZlK8-7j-R-Z-L-K-8"; // Demo key, to be replaced by actual VAPID key

export const NotificationService = {
  /**
   * Register the Service Worker
   */
  async register() {
    if (!('serviceWorker' in navigator)) return null;
    
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      });
      console.log('Push Service Worker Registered:', registration);
      return registration;
    } catch (error) {
      console.error('Service Worker Registration Failed:', error);
      return null;
    }
  },

  /**
   * Request permission for notifications
   */
  async requestPermission() {
    if (!('Notification' in window)) return false;
    
    try {
      const permission = await Notification.requestPermission();
      return permission === 'granted';
    } catch (error) {
       console.error('Permission request failed:', error);
       return false;
    }
  },

  /**
   * Subscribe user to push notifications
   */
  async subscribe(registration) {
    if (!registration) return null;
    
    try {
      // Check if already subscribed
      const existingSub = await registration.pushManager.getSubscription();
      if (existingSub) return existingSub;

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
      });

      console.log('Push Subscription Created:', subscription);
      
      // Save subscription to backend
      await this.saveSubscription(subscription);
      
      return subscription;
    } catch (error) {
      console.error('Push Subscription Failed:', error);
      return null;
    }
  },

  /**
   * Save subscription to backend
   */
  async saveSubscription(subscription) {
    try {
        const token = localStorage.getItem('ehub_token');
        if (!token) return;

        const response = await fetch('http://localhost:8000/notifications_push_subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ subscription })
        });
        const data = await response.json();
        return data.success;
    } catch (error) {
        console.error('Failed to save push subscription:', error);
        return false;
    }
  },

  /**
   * Helper to convert VAPID key
   */
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }
};
