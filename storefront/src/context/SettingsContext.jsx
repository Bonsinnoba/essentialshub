import React, { createContext, useContext, useState, useEffect } from 'react';
import { updateProfile } from '../services/api';
import { useUser } from './UserContext';

const SettingsContext = createContext();

export const useSettings = () => {
  const context = useContext(SettingsContext);
  if (!context) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return context;
};

export const SettingsProvider = ({ children }) => {
  const { user, updateUser } = useUser();
  const [siteSettings, setSiteSettings] = useState({
    siteName: 'ElectroCom',
    phone1: '0536683393',
    phone2: '0506408074',
    whatsapp: '233536683393',
    siteEmail: 'support@electrocom.com',
    maintenanceMode: false
  });

  const [settings, setSettings] = useState(() => {
    const saved = localStorage.getItem('ehub_settings_v2');
    return saved ? JSON.parse(saved) : {
      email_notif: true,
      push_notif: true,
      sms_tracking: true,
      currency: 'GHS',
      language: 'English (UK)',
      currencySymbol: 'GH₵',
      currencyRate: 1
    };
  });

  // Fetch site settings from backend
  useEffect(() => {
    const loadSiteSettings = async () => {
      try {
        const response = await fetch(`${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}/get_site_settings.php`);
        const result = await response.json();
        if (result.success) setSiteSettings(result.data);
      } catch (error) {
        console.error('Error loading site settings:', error);
      }
    };
    loadSiteSettings();
  }, []);

  // Sync with user object on load/change
  useEffect(() => {
    if (user) {
      setSettings(prev => ({
        ...prev,
        email_notif: user.email_notif ?? prev.email_notif,
        push_notif: user.push_notif ?? prev.push_notif,
        sms_tracking: user.sms_tracking ?? prev.sms_tracking
      }));
    }
  }, [user]);

  useEffect(() => {
    localStorage.setItem('ehub_settings_v2', JSON.stringify(settings));
  }, [settings]);

  const updateSetting = async (key, value) => {
    const prevValue = settings[key];
    setSettings(prev => ({ ...prev, [key]: value }));

    // Persist to backend if a user is logged in and it's a preference field
    const persistentKeys = ['email_notif', 'push_notif', 'sms_tracking'];
    if (user && persistentKeys.includes(key)) {
      try {
        const result = await updateProfile({ [key]: value });
        if (result.success) {
          updateUser({ ...user, [key]: value });
        } else {
          // Revert on failure
          setSettings(prev => ({ ...prev, [key]: prevValue }));
        }
      } catch (error) {
        setSettings(prev => ({ ...prev, [key]: prevValue }));
      }
    }
  };

  const updateCurrency = (currency) => {
    const symbols = { 'GHS': 'GH₵', 'USD': '$', 'EUR': '€', 'GBP': '£' };
    const rates = { 'GHS': 1, 'USD': 0.083, 'EUR': 0.077, 'GBP': 0.065 }; // Simple mock rates
    setSettings(prev => ({
      ...prev,
      currency,
      currencySymbol: symbols[currency] || '$',
      currencyRate: rates[currency] || 1
    }));
  };

  const formatPrice = (price) => {
    const converted = (price * settings.currencyRate).toFixed(2);
    return `${settings.currencySymbol}${converted}`;
  };

  return (
    <SettingsContext.Provider value={{ settings, siteSettings, updateSetting, updateCurrency, formatPrice }}>
      {children}
    </SettingsContext.Provider>
  );
};
