import React, { createContext, useContext, useState, useEffect } from 'react';
import { getWallet, verifyPayment } from '../services/api';
import { useUser } from './UserContext';

const WalletContext = createContext();

export const useWallet = () => useContext(WalletContext);

export const WalletProvider = ({ children }) => {
  const { user } = useUser();
  const [balance, setBalance] = useState(0.00);
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);

  const refreshWallet = async () => {
    if (!user) return;
    try {
        const data = await getWallet();
        if (data.success) {
            setBalance(parseFloat(data.balance));
            setTransactions(data.transactions || []);
        }
    } catch (error) {
        console.error("Failed to refresh wallet", error);
    }
  };

  useEffect(() => {
    refreshWallet();
  }, [user]);

  const addTransaction = (transaction) => {
    setTransactions(prev => [transaction, ...prev]);
  };

  const deductBalance = (amount) => {
    setBalance(prev => prev - amount);
  };

  const verifyTopUp = async (reference) => {
    setLoading(true);
    try {
        const result = await verifyPayment(reference, 'wallet_topup');
        if (result.success) {
            await refreshWallet();
            return { success: true };
        } else {
            return { success: false, message: result.message };
        }
    } catch (error) {
        return { success: false, message: error.message };
    } finally {
        setLoading(false);
    }
  };

  return (
    <WalletContext.Provider value={{ 
      balance, 
      transactions, 
      deductBalance,
      verifyTopUp,
      refreshWallet,
      addTransaction,
      loading
    }}>
      {children}
    </WalletContext.Provider>
  );
};
