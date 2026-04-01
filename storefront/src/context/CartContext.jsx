import React, { createContext, useContext, useState, useEffect } from 'react';
import { useUser } from './UserContext';
import { secureStorage } from '../utils/secureStorage';
import { syncCart, validateCoupon } from '../services/api';
import { useNotifications } from './NotificationContext';

const CartContext = createContext();

export const useCart = () => {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
};

export const CartProvider = ({ children }) => {
  const { user } = useUser();
  const { addToast } = useNotifications();

  const [cartItems, setCartItems] = useState(() => {
    return secureStorage.getItem('cart', user?.id) || [];
  });

  const [appliedCoupon, setAppliedCoupon] = useState(() => {
    return secureStorage.getItem('appliedCoupon', user?.id) || null;
  });

  const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
  const [couponError, setCouponError] = useState('');

  useEffect(() => {
    secureStorage.setItem('cart', cartItems, user?.id);
    secureStorage.setItem('appliedCoupon', appliedCoupon, user?.id);

    // Sync to backend for Abandoned Cart recovery (fire and forget)
    if (user) {
        const performCartSync = async () => {
             try {
                 await syncCart(cartItems);
             } catch (e) {
                 // Silently fail, it's a background sync
             }
        };
        // Small debounce to avoid spamming if user clicks rapidly
        const timeoutId = setTimeout(performCartSync, 1000);
        return () => clearTimeout(timeoutId);
    }
  }, [cartItems, user]);

  const addToCart = (product, quantity = 1, color = 'Default') => {
    if (!user) {
        addToast('Please login to add items to an active cart', 'error');
        return;
    }
    setCartItems(prev => {
      const existingItemIndex = prev.findIndex(
        item => item.id === product.id && item.selectedColor === color
      );

      if (existingItemIndex > -1) {
        const updatedItems = [...prev];
        updatedItems[existingItemIndex].quantity += quantity;
        return updatedItems;
      }

      return [...prev, { ...product, quantity, selectedColor: color }];
    });
  };

  const removeFromCart = (itemId, color) => {
    setCartItems(prev => prev.filter(item => !(item.id === itemId && item.selectedColor === color)));
  };

  const updateQuantity = (itemId, color, delta) => {
    setCartItems(prev => {
      return prev.map(item => {
        if (item.id === itemId && item.selectedColor === color) {
          const newQty = Math.max(1, item.quantity + delta);
          return { ...item, quantity: newQty };
        }
        return item;
      });
    });
  };

  const clearCart = () => {
    setCartItems([]);
    setAppliedCoupon(null);
  };

  const applyCoupon = async (code) => {
    if (!code.trim()) return;
    setIsApplyingCoupon(true);
    setCouponError('');
    try {
      const result = await validateCoupon(code, subtotal);
      if (result.success) {
        setAppliedCoupon(result.coupon);
        addToast('Coupon applied successfully', 'success');
        return true;
      } else {
        setCouponError(result.error || 'Invalid coupon code');
        return false;
      }
    } catch (err) {
      setCouponError('Error validating coupon. Please try again.');
      return false;
    } finally {
      setIsApplyingCoupon(false);
    }
  };

  const removeCoupon = () => {
    setAppliedCoupon(null);
    setCouponError('');
    addToast('Coupon removed', 'info');
  };

  const subtotal = cartItems.reduce((acc, item) => acc + (parseFloat(item.price) * item.quantity), 0);
  const cartCount = cartItems.reduce((acc, item) => acc + item.quantity, 0);

  return (
    <CartContext.Provider value={{ 
      cartItems, 
      addToCart, 
      removeFromCart, 
      updateQuantity, 
      clearCart,
      subtotal,
      cartCount,
      appliedCoupon,
      applyCoupon,
      removeCoupon,
      isApplyingCoupon,
      couponError
    }}>
      {children}
    </CartContext.Provider>
  );
};
