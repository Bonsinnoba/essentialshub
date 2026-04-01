import React, { useState, useEffect } from 'react';
import { 
  Package, CheckCircle2, XCircle, Clock, 
  ArrowRight, Filter, Search, ClipboardList,
  AlertCircle
} from 'lucide-react';
import { fetchStockRequests, updateStockRequestStatus } from '../services/api';
import { useNotifications } from '../context/NotificationContext';

export default function StockManagement() {
  const { addToast } = useNotifications();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    loadRequests();
  }, []);

  const loadRequests = async () => {
    setLoading(true);
    try {
      const res = await fetchStockRequests();
      if (res.success) setRequests(res.data);
    } catch (error) {
      addToast('Failed to load requests', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleStatusUpdate = async (id, status) => {
    try {
      const res = await updateStockRequestStatus(id, status);
      if (res.success) {
        addToast(`Request marked as ${status}`, 'success');
        loadRequests();
      } else {
        addToast(res.message, 'error');
      }
    } catch (error) {
      addToast('Status update failed', 'error');
    }
  };

  const filteredRequests = requests.filter(req => {
    const matchesFilter = filter === 'all' || req.status === filter;
    const matchesSearch = req.requester_name?.toLowerCase().includes(searchTerm.toLowerCase()) || 
                          req.id.toString().includes(searchTerm);
    return matchesFilter && matchesSearch;
  });

  const getStatusColor = (status) => {
    switch (status) {
      case 'pending': return 'var(--warning)';
      case 'approved': return 'var(--primary-blue)';
      case 'fulfilled': return 'var(--success)';
      case 'rejected': return 'var(--danger)';
      default: return 'var(--text-muted)';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending': return <Clock size={16} />;
      case 'approved': return <CheckCircle2 size={16} />;
      case 'fulfilled': return <Package size={16} />;
      case 'rejected': return <XCircle size={16} />;
      default: return null;
    }
  };

  if (loading) return <div className="animate-pulse" style={{ padding: '40px', textAlign: 'center' }}>Loading Logistics...</div>;

  return (
    <div className="animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ fontSize: '32px', fontWeight: 800 }}>Stock Management</h1>
          <p style={{ color: 'var(--text-muted)' }}>Manage and fulfill inter-branch stock requests.</p>
        </div>
        <div style={{ display: 'flex', gap: '12px' }}>
           <button className="btn btn-secondary" onClick={loadRequests}><Clock size={20} /> REFRESH</button>
        </div>
      </header>

      {/* Filters & Search */}
      <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
        <div className="card glass" style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '12px', flex: 1, minWidth: '300px' }}>
          <Search size={18} color="var(--text-muted)" />
          <input 
            type="text" 
            placeholder="Search by requester or ID..." 
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={{ flex: 1, border: 'none', background: 'transparent', outline: 'none', color: 'var(--text-main)', fontWeight: 600 }}
          />
        </div>
        <div style={{ display: 'flex', gap: '8px' }}>
          {['all', 'pending', 'approved', 'fulfilled', 'rejected'].map(s => (
            <button 
              key={s}
              className={`btn ${filter === s ? 'btn-primary' : 'btn-outline'}`}
              onClick={() => setFilter(s)}
              style={{ padding: '0 16px', textTransform: 'capitalize', height: '44px' }}
            >
              {s}
            </button>
          ))}
        </div>
      </div>

      {/* Request List */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        {filteredRequests.length === 0 ? (
          <div className="card glass" style={{ padding: '80px', textAlign: 'center' }}>
             <ClipboardList size={48} opacity={0.2} style={{ margin: '0 auto 16px' }} />
             <p style={{ fontWeight: 700, color: 'var(--text-muted)' }}>No stock requests found matching your criteria.</p>
          </div>
        ) : (
          filteredRequests.map(req => (
            <div key={req.id} className="card glass animate-fade-in" style={{ padding: '24px' }}>
               <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
                  <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                     <div style={{ padding: '12px', background: 'var(--bg-surface-secondary)', borderRadius: '12px' }}>
                        <Package size={24} color="var(--primary-blue)" />
                     </div>
                     <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                           <span style={{ fontWeight: 900, fontSize: '18px' }}>Request #SR-{req.id}</span>
                           <span style={{ 
                             fontSize: '10px', 
                             fontWeight: 900, 
                             padding: '2px 8px', 
                             borderRadius: '6px', 
                             background: 'var(--bg-surface-secondary)',
                             color: getStatusColor(req.status),
                             display: 'flex',
                             alignItems: 'center',
                             gap: '4px',
                             textTransform: 'uppercase'
                           }}>
                             {getStatusIcon(req.status)} {req.status}
                           </span>
                        </div>
                        <div style={{ fontSize: '13px', color: 'var(--text-muted)', fontWeight: 600 }}>
                           From Branch #{req.branch_id} | Requested by {req.requester_name}
                        </div>
                     </div>
                  </div>
                  <div style={{ display: 'flex', gap: '8px' }}>
                     {req.status === 'pending' && (
                       <>
                         <button className="btn btn-primary" onClick={() => handleStatusUpdate(req.id, 'approved')}>APPROVE</button>
                         <button className="btn btn-outline" style={{ color: 'var(--danger)' }} onClick={() => handleStatusUpdate(req.id, 'rejected')}>REJECT</button>
                       </>
                     )}
                     {req.status === 'approved' && (
                        <button className="btn btn-primary" onClick={() => handleStatusUpdate(req.id, 'fulfilled')}>MARK FULFILLED</button>
                     )}
                  </div>
               </div>

               {/* Items Grid */}
               <div style={{ background: 'var(--bg-surface-secondary)', borderRadius: '12px', padding: '16px', marginBottom: '16px' }}>
                  <div style={{ fontSize: '11px', fontWeight: 900, color: 'var(--text-muted)', marginBottom: '12px', textTransform: 'uppercase' }}>Requested Items</div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
                     {req.items.map(item => (
                       <div key={item.id} style={{ background: 'var(--bg-surface)', padding: '8px 16px', borderRadius: '8px', border: '1px solid var(--border-light)', display: 'flex', alignItems: 'center', gap: '12px' }}>
                          <div style={{ fontWeight: 800, color: 'var(--primary-blue)' }}>{item.quantity}x</div>
                          <div>
                             <div style={{ fontWeight: 700, fontSize: '13px' }}>{item.product_name}</div>
                             <div style={{ fontSize: '10px', color: 'var(--text-muted)' }}>SKU: {item.sku}</div>
                          </div>
                       </div>
                     ))}
                  </div>
               </div>

               {req.notes && (
                 <div style={{ display: 'flex', gap: '8px', alignItems: 'center', color: 'var(--text-muted)', fontSize: '13px', fontStyle: 'italic' }}>
                    <AlertCircle size={14} />
                    <span>"{req.notes}"</span>
                 </div>
               )}

               <div style={{ textAlign: 'right', fontSize: '11px', color: 'var(--text-muted)', marginTop: '16px' }}>
                  Last updated: {new Date(req.updated_at).toLocaleString()}
               </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
