import React, { useState, useEffect, useRef } from 'react';
import { 
  Search, ShoppingCart, Plus, Minus, Trash2, 
  Banknote, Package, Zap,
  CheckCircle2, ArrowRight, Printer, 
  LayoutGrid, Filter, X, Barcode
} from 'lucide-react';
import { useNotifications } from '../context/NotificationContext';

import { API_BASE_URL, formatImageUrl } from '../services/api';

export default function POSInterface() {
  const { addToast } = useNotifications();
  const [products, setProducts] = useState([]);
  const [filteredProducts, setFilteredProducts] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [cart, setCart] = useState([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [customerEmail, setCustomerEmail] = useState('');
  const [showSuccess, setShowSuccess] = useState(false);
  const [isRequestMode, setIsRequestMode] = useState(false);
  const [notes, setNotes] = useState('');
  const [lastOrderId, setLastOrderId] = useState(null);
  const searchInputRef = useRef(null);

  useEffect(() => {
    fetchProducts();
  }, []);

  useEffect(() => {
    if (searchQuery.trim() === '') {
      setFilteredProducts([]);
      return;
    }
    const query = searchQuery.toLowerCase();
    const matches = products.filter(p => 
      (p.name || '').toLowerCase().includes(query) || 
      (p.product_code || '').toLowerCase().includes(query)
    );
    setFilteredProducts(matches);

    // Exact match auto-add (for barcode scanners)
    const exactMatch = products.find(p => 
      p.product_code?.toLowerCase() === searchQuery.trim().toLowerCase()
    );
    if (exactMatch) {
      addToCart(exactMatch);
      setSearchQuery('');
      addToast(`Added ${exactMatch.name}`, 'success');
    }
  }, [searchQuery, products]);

  const fetchProducts = async () => {
    try {
      const res = await fetch(`${API_BASE_URL}/get_products.php`);
      const data = await res.json();
      if (data.success) setProducts(data.data);
    } catch (error) {
      addToast('Inventory sync failed', 'error');
    } finally {
      setLoading(false);
    }
  };

  const addToCart = (product) => {
    // In request mode, allow any item regardless of stock level
    if (!isRequestMode && product.stock_quantity <= 0) {
      addToast(`${product.name} is out of stock!`, 'error');
      return;
    }
    setCart(prev => {
      const existing = prev.find(item => item.id === product.id);
      if (existing) {
        // In request mode, no stock ceiling — request as many as needed
        if (!isRequestMode && existing.quantity >= product.stock_quantity) {
          addToast('Limited availability', 'warning');
          return prev;
        }
        return prev.map(item => item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item);
      }
      return [{ ...product, quantity: 1 }, ...prev];
    });
  };

  const updateQuantity = (id, delta) => {
    setCart(prev => prev.map(item => {
      if (item.id === id) {
        const newQty = Math.max(0, item.quantity + delta);
        // In request mode, no stock ceiling — can request more than current stock
        if (!isRequestMode && newQty > item.stock_quantity) {
           addToast('Stock limit reached', 'warning');
           return item;
        }
        return { ...item, quantity: newQty };
      }
      return item;
    }).filter(item => item.quantity > 0));
  };

  const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  const handleStockRequest = async () => {
    if (cart.length === 0) return;
    setProcessing(true);
    try {
      const token = localStorage.getItem('ehub_token');
      const response = await fetch(`${API_BASE_URL}/stock_requests.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          items: cart.map(item => ({ product_id: item.id, quantity: item.quantity })),
          notes: notes
        })
      });
      const result = await response.json();
      if (result.success) {
        addToast('Stock request submitted!', 'success');
        setCart([]);
        setNotes('');
        setIsRequestMode(false);
      } else {
        addToast(result.message, 'error');
      }
    } catch (error) {
      addToast('Request failed', 'error');
    } finally {
      setProcessing(false);
    }
  };

  const handleCheckout = async () => {
    if (isRequestMode) return handleStockRequest();
    if (cart.length === 0) return;
    setProcessing(true);
    try {
      const token = localStorage.getItem('ehub_token');
      const response = await fetch(`${API_BASE_URL}/pos_checkout.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          items: cart.map(item => ({ id: item.id, quantity: item.quantity, price: item.price })),
          total_amount: total,
          payment_method: paymentMethod,
          customer_email: customerEmail
        })
      });
      const result = await response.json();
      if (result.success) {
        setLastOrderId(result.order_id);
        setShowSuccess(true);
        setCart([]);
        setCustomerEmail('');
        fetchProducts();
      } else {
        addToast(result.message, 'error');
      }
    } catch (error) {
      addToast('Checkout error', 'error');
    } finally {
      setProcessing(false);
    }
  };

  if (showSuccess) {
    return (
      <div className="card glass animate-fade-in" style={{ padding: '60px', textAlign: 'center', margin: '40px auto', maxWidth: '500px' }}>
         <div style={{ width: '80px', height: '80px', background: 'var(--success-bg)', color: 'var(--success)', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
            <CheckCircle2 size={48} />
         </div>
         <h1 style={{ fontSize: '28px', fontWeight: 900 }}>Record Saved</h1>
         <p style={{ color: 'var(--text-muted)', marginBottom: '40px' }}>Transaction #ORD-{lastOrderId} registered successfully.</p>
         <div style={{ display: 'flex', gap: '16px', justifyContent: 'center' }}>
            <button className="btn btn-primary" onClick={() => { setShowSuccess(false); setTimeout(() => searchInputRef.current?.focus(), 100); }}>
              NEW RECORD <Plus size={20} />
            </button>
            <button className="btn btn-secondary" onClick={() => window.print()}>
              <Printer size={20} /> PRINT RECEIPT
            </button>
         </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ fontSize: '32px', fontWeight: 800 }}>{isRequestMode ? 'Request Stock' : 'Record Sale'}</h1>
          <p style={{ color: 'var(--text-muted)' }}>{isRequestMode ? 'Request products from the main branch warehouse.' : 'Platform to enter records of physical sales and activities.'}</p>
        </div>
        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
           <button 
             className={`btn ${isRequestMode ? 'btn-secondary' : 'btn-outline'}`}
             onClick={() => { setIsRequestMode(!isRequestMode); setCart([]); }}
             style={{ height: '44px', fontWeight: 800, fontSize: '12px' }}
           >
             {isRequestMode ? 'CANCEL REQUEST' : 'STOCK REQUEST'} <Package size={18} />
           </button>
           <div style={{ display: 'flex', gap: '12px', background: 'var(--bg-surface)', padding: '6px 12px', borderRadius: '12px', border: '1px solid var(--border-light)' }}>
              <div style={{ width: '8px', height: '8px', background: 'var(--success)', borderRadius: '50%' }}></div>
              <span style={{ fontSize: '11px', fontWeight: 800 }}>READY</span>
           </div>
        </div>
      </header>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 400px', gap: '32px', alignItems: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px', position: 'relative' }}>
          <div className="card glass" style={{ padding: '4px', display: 'flex', alignItems: 'center', borderRadius: '16px', border: isRequestMode ? '2px solid var(--warning)' : '2px solid var(--primary-blue)' }}>
            <div style={{ padding: '16px' }}>
              <Barcode size={24} color={isRequestMode ? 'var(--warning)' : 'var(--primary-blue)'} />
            </div>
            <input 
              ref={searchInputRef}
              autoFocus
              type="text" 
              placeholder="Search product name or exact code..." 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              style={{ flex: 1, border: 'none', background: 'transparent', outline: 'none', fontSize: '18px', color: 'var(--text-main)', fontWeight: 700, padding: '16px 0' }}
            />
          </div>

          {searchQuery && filteredProducts.length > 0 && (
            <div className="card glass animate-fade-in" style={{ position: 'absolute', width: '100%', marginTop: '80px', zIndex: 100, padding: '8px', boxShadow: '0 10px 40px rgba(0,0,0,0.2)' }}>
               {filteredProducts.slice(0, 6).map(p => {
                 const isOutOfStock = p.stock_quantity <= 0;
                 return (
                   <div 
                     key={p.id} 
                     onClick={() => { addToCart(p); setSearchQuery(''); }}
                     style={{ padding: '12px 16px', borderRadius: '8px', cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center', opacity: (!isRequestMode && isOutOfStock) ? 0.4 : 1 }}
                     className="hover-bg"
                   >
                     <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{ width: '40px', height: '40px', background: 'var(--bg-surface-secondary)', borderRadius: '6px', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                           {p.image_url ? <img src={formatImageUrl(p.image_url)} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : <Package size={20} opacity={0.3} />}
                        </div>
                        <div>
                          <div style={{ fontWeight: 700, fontSize: '14px' }}>{p.name}</div>
                          <div style={{ fontSize: '11px', color: isRequestMode && isOutOfStock ? 'var(--warning)' : 'var(--text-muted)' }}>
                            Code: {p.product_code || '---'} | {isRequestMode && isOutOfStock ? '⚠ Out of stock — request to restock' : `Stock: ${p.stock_quantity}`}
                          </div>
                        </div>
                     </div>
                     {isRequestMode
                       ? <div style={{ fontSize: '10px', fontWeight: 900, padding: '2px 8px', borderRadius: '6px', background: 'var(--bg-surface-secondary)', color: 'var(--warning)', textTransform: 'uppercase' }}>REQUEST</div>
                       : <div style={{ fontWeight: 800, color: 'var(--primary-blue)' }}>GH₵ {p.price}</div>
                     }
                   </div>
                 );
               })}
            </div>
          )}

          <div className="card glass" style={{ padding: '0', minHeight: '400px' }}>
            <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-light)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
               <h3 style={{ fontSize: '14px', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{isRequestMode ? 'Requested Items' : 'Current Items'}</h3>
               <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>Items: {cart.length}</span>
            </div>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ textAlign: 'left', borderBottom: '1px solid var(--border-light)', fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                  <th style={{ padding: '12px 24px' }}>Description</th>
                  <th style={{ padding: '12px 24px' }}>Unit Price</th>
                  <th style={{ padding: '12px 24px', width: '120px' }}>Quantity</th>
                  {!isRequestMode && <th style={{ padding: '12px 24px', textAlign: 'right' }}>Extension</th>}
                  <th style={{ padding: '12px 24px', width: '50px' }}></th>
                </tr>
              </thead>
              <tbody>
                {cart.length === 0 ? (
                  <tr>
                    <td colSpan={isRequestMode ? 4 : 5} style={{ padding: '100px 0', textAlign: 'center' }}>
                       <div style={{ color: 'var(--text-muted)', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '16px' }}>
                          <Search size={48} opacity={0.2} />
                          <div style={{ maxWidth: '280px' }}>
                            <p style={{ fontWeight: 700, color: 'var(--text-main)', marginBottom: '4px' }}>No items added</p>
                            <p style={{ fontSize: '13px' }}>{isRequestMode ? 'Select items you need from the main branch.' : 'Scan a barcode or type a product name to begin.'}</p>
                          </div>
                       </div>
                    </td>
                  </tr>
                ) : (
                  cart.map(item => (
                    <tr key={item.id} style={{ borderBottom: '1px solid var(--border-light)' }}>
                      <td style={{ padding: '16px 24px' }}>
                        <div style={{ fontWeight: 700, fontSize: '14px' }}>{item.name}</div>
                        <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>{item.product_code || 'No Code'}</div>
                      </td>
                      <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: 600 }}>GH₵ {item.price}</td>
                      <td style={{ padding: '16px 24px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', background: 'var(--bg-surface-secondary)', borderRadius: '8px', padding: '4px', width: 'fit-content' }}>
                          <button className="btn" style={{ padding: '2px 8px', minWidth: 'unset', height: '28px', background: 'var(--bg-surface)' }} onClick={() => updateQuantity(item.id, -1)}>-</button>
                          <span style={{ fontWeight: 800, minWidth: '20px', textAlign: 'center' }}>{item.quantity}</span>
                          <button className="btn" style={{ padding: '2px 8px', minWidth: 'unset', height: '28px', background: 'var(--bg-surface)' }} onClick={() => updateQuantity(item.id, 1)}>+</button>
                        </div>
                      </td>
                      {!isRequestMode && (
                        <td style={{ padding: '16px 24px', textAlign: 'right', fontWeight: 800, fontSize: '15px' }}>
                          GH₵ {(item.price * item.quantity).toLocaleString()}
                        </td>
                      )}
                      <td style={{ padding: '16px 24px' }}>
                        <button onClick={() => updateQuantity(item.id, -item.quantity)} style={{ background: 'transparent', border: 'none', color: 'var(--danger)', cursor: 'pointer' }}>
                          <Trash2 size={18} />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px', position: 'sticky', top: '32px' }}>
           <div className="card glass" style={{ padding: '32px' }}>
              <h3 style={{ fontSize: '18px', fontWeight: 900, marginBottom: '24px' }}>{isRequestMode ? 'Request Summary' : 'Record Summary'}</h3>
              
              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginBottom: 32 }}>
                 <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--text-muted)', fontSize: '14px', fontWeight: 600 }}>
                    <span>Items Count</span>
                    <span>{cart.reduce((s, i) => s + i.quantity, 0)}</span>
                  </div>
                  {!isRequestMode && (
                    <div style={{ marginTop: '12px', padding: '16px 0', borderTop: '2px dashed var(--border-light)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end' }}>
                        <span style={{ fontSize: '12px', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Subtotal Due</span>
                        <span style={{ fontSize: '32px', fontWeight: 950, color: 'var(--primary-blue)', lineHeight: 1 }}>GH₵ {total.toLocaleString()}</span>
                      </div>
                    </div>
                  )}
              </div>

              {!isRequestMode ? (
                <>
                  <div style={{ marginBottom: '24px' }}>
                    <label style={{ fontSize: '11px', fontWeight: 800, color: 'var(--text-muted)', marginBottom: '8px', display: 'block' }}>PAYMENT METHOD</label>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                        <button className={`btn ${paymentMethod === 'cash' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setPaymentMethod('cash')} style={{ fontSize: '10px', height: '48px', padding: '0' }}><Banknote size={16} /> CASH</button>
                        <button className={`btn ${paymentMethod === 'momo' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setPaymentMethod('momo')} style={{ fontSize: '10px', height: '48px', padding: '0' }}><Zap size={16} /> MOMO</button>
                    </div>
                  </div>

                  <div style={{ marginBottom: '32px' }}>
                    <label style={{ fontSize: '11px', fontWeight: 800, color: 'var(--text-muted)', marginBottom: '8px', display: 'block' }}>CUSTOMER EMAIL (OPTIONAL)</label>
                    <input 
                      type="email" 
                      placeholder="e.g. customer@example.com" 
                      value={customerEmail}
                      onChange={(e) => setCustomerEmail(e.target.value)}
                      style={{ width: '100%', padding: '14px 16px', borderRadius: '12px', border: '1px solid var(--border-light)', background: 'var(--bg-surface)', fontSize: '13px', fontWeight: 600 }}
                    />
                  </div>
                </>
              ) : (
                <div style={{ marginBottom: '32px' }}>
                  <label style={{ fontSize: '11px', fontWeight: 800, color: 'var(--text-muted)', marginBottom: '8px', display: 'block' }}>NOTES / REASON FOR REQUEST</label>
                  <textarea 
                    placeholder="e.g. Stock low for the week..." 
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    style={{ width: '100%', padding: '14px 16px', borderRadius: '12px', border: '1px solid var(--border-light)', background: 'var(--bg-surface)', fontSize: '13px', fontWeight: 600, minHeight: '120px', resize: 'none' }}
                  />
                </div>
              )}

              <button 
                className={`btn ${isRequestMode ? 'btn-warning' : 'btn-primary'} ${processing ? 'spinning' : ''}`} 
                style={{ width: '100%', height: '64px', fontSize: '16px', fontWeight: 900, borderRadius: '16px' }}
                onClick={handleCheckout}
                disabled={cart.length === 0 || processing}
              >
                {processing ? 'WAITING...' : (isRequestMode ? 'SUBMIT STOCK REQUEST' : 'CONFIRM SALE RECORD')}
              </button>
           </div>
        </div>
      </div>
    </div>
  );
}
