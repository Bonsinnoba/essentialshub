import React from 'react';
import { useWallet } from '../context/WalletContext';
import { History, ArrowUpRight, ArrowDownLeft, Receipt } from 'lucide-react';
import { useSettings } from '../context/SettingsContext';

export default function Transactions() {
  const { transactions } = useWallet();
  const { formatPrice } = useSettings();

  return (
    <div className="transactions-page" style={{ 
      display: 'flex', 
      flexDirection: 'column', 
      gap: '24px',
      width: '100%',
      maxWidth: '100%'
    }}>
      <div className="page-header" style={{ padding: '24px 0 8px', flexWrap: 'wrap', gap: '12px', width: '100%' }}>
        <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.5px', display: 'flex', alignItems: 'center', gap: '12px' }}>
          <Receipt size={32} className="text-blue" />
          Transactions
        </h1>
        <p style={{ color: 'var(--text-muted)', marginTop: '4px' }}>Track your spending and transaction history.</p>
      </div>

      {/* Row 1: Transaction History */}
      <div className="card glass" style={{ padding: '24px 16px', width: '100%', boxSizing: 'border-box' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
          <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: 0 }}>
            <History size={20} /> Transaction History
          </div>
          <button className="btn-secondary" style={{ fontSize: '13px', padding: '8px 16px' }}>View All Activity</button>
        </div>
        
        <div className="custom-scrollbar" style={{ 
          display: 'flex', 
          flexDirection: 'column', 
          gap: '12px',
          maxHeight: '440px',
          overflowY: 'auto',
          paddingRight: '8px'
        }}>
          {transactions.length > 0 ? transactions.map(tx => (
            <div key={tx.id} className="glass" style={{ 
              padding: '16px', 
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center'
            }}>
              <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                <div style={{ 
                  width: '40px', 
                  height: '40px', 
                  background: tx.type === 'credit' ? 'var(--success-bg)' : 'var(--danger-bg)', 
                  borderRadius: '12px', 
                  display: 'flex', 
                  alignItems: 'center', 
                  justifyContent: 'center', 
                  color: tx.type === 'credit' ? 'var(--success)' : 'var(--danger)' 
                }}>
                  {tx.type === 'credit' ? <ArrowDownLeft size={20} /> : <ArrowUpRight size={20} />}
                </div>
                <div>
                  <div style={{ fontWeight: 700 }}>{tx.title}</div>
                  <div style={{ fontSize: '12px', color: 'var(--text-muted)' }}>{tx.date} • {tx.details}</div>
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ color: tx.type === 'credit' ? 'var(--success)' : 'var(--text-main)', fontWeight: 800, fontSize: '18px' }}>
                  {tx.type === 'credit' ? '+' : '-'}{formatPrice(tx.amount)}
                </div>
                <div style={{ fontSize: '10px', color: tx.type === 'credit' ? 'var(--success)' : 'var(--text-muted)', fontWeight: 600 }}>{tx.status}</div>
              </div>
            </div>
          )) : (
            <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-muted)' }}>
                No recent transactions found.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
