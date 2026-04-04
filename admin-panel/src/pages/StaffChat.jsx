import React, { useState, useEffect, useRef } from 'react';
import { fetchBackend, formatImageUrl } from '../services/api';
import { Search, Send, MapPin, Check, CheckCheck, MessageSquare, Megaphone, Users, User, Clock, Bell, ChevronLeft, ChevronDown, MoreVertical, Paperclip, Loader, CheckCircle, X, Hash, Info, Settings, Trash2, Database, ShieldAlert, FileSearch } from 'lucide-react';
import { useNotifications } from '../context/NotificationContext';
import { useConfirm } from '../context/ConfirmContext';

export default function StaffChat() {
  const { confirm } = useConfirm();
  const [users, setUsers] = useState([]);
  const [messages, setMessages] = useState([]);
  const [activeChat, setActiveChat] = useState('global'); // 'global' or user ID
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [attachmentBase64, setAttachmentBase64] = useState(null);
  const [attachmentPreview, setAttachmentPreview] = useState(null);
  const [replyTo, setReplyTo] = useState(null); // { id, message, name }
  const [showScrollButton, setShowScrollButton] = useState(false);
  
  // Broadcast options for admins sending in the global channel
  const [broadcastOptions, setBroadcastOptions] = useState({
    isPinned: false,
    sendEmail: false,
    sendSms: false
  });

  const { addToast } = useNotifications();
  const messagesEndRef = useRef(null);
  const fileInputRef = useRef(null);
  const chatContainerRef = useRef(null);
  
  const currentUser = JSON.parse(localStorage.getItem('ehub_user') || '{}');
  const isAdminOrManager = ['super', 'admin', 'manager'].includes(currentUser.role);
  const isSuper = currentUser.role === 'super';

  // Maintenance State
  const [showMaintenance, setShowMaintenance] = useState(false);
  const [maintStats, setMaintStats] = useState(null);
  const [maintLoading, setMaintLoading] = useState(false);

  const fetchUsers = async () => {
    try {
      const data = await fetchBackend('/admin_chat.php?action=users');
      if (data.success) {
        setUsers(data.users || []);
      }
    } catch (err) {
      addToast('Error fetching staff list', 'error');
    }
  };

  const fetchMessages = async () => {
    try {
      const withUser = activeChat === 'global' ? 'global' : activeChat;
      const data = await fetchBackend(`/admin_chat.php?action=history&with_user=${withUser}`);
      if (data.success) {
        setMessages(data.messages || []);
        if (activeChat !== 'global') {
          // Mark as read when entering a DM
          await fetchBackend('/admin_chat.php?action=mark_read', {
            method: 'POST',
            body: JSON.stringify({ with_user: activeChat })
          });
          // Update unread count locally
          setUsers(prev => prev.map(u => u.id === activeChat ? { ...u, unread_count: 0 } : u));
        }
      }
    } catch (err) {
      addToast('Error fetching messages', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
    // Start polling users for unread counts every 10 seconds
    const interval = setInterval(fetchUsers, 10000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchMessages();
    // Polling messages every 3 seconds for active chat
    const interval = setInterval(fetchMessages, 3000);
    return () => clearInterval(interval);
  }, [activeChat]);

  useEffect(() => {
    // Only auto-scroll if we are near the bottom already
    if (chatContainerRef.current) {
      const { scrollTop, scrollHeight, clientHeight } = chatContainerRef.current;
      const isAtBottom = scrollHeight - scrollTop <= clientHeight + 150; // 150px threshold
      
      if (isAtBottom) {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
      } else {
        setShowScrollButton(true);
      }
    }
  }, [messages]);

  const handleScroll = () => {
    if (chatContainerRef.current) {
      const { scrollTop, scrollHeight, clientHeight } = chatContainerRef.current;
      const isAtBottom = scrollHeight - scrollTop <= clientHeight + 50;
      if (isAtBottom) {
        setShowScrollButton(false);
      }
    }
  };

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    setShowScrollButton(false);
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim() && !attachmentBase64) return;

    const payload = {
      message: newMessage.trim() || (attachmentBase64 ? 'Sent an attachment' : ''),
      receiver_id: activeChat === 'global' ? 'global' : activeChat,
      is_pinned: activeChat === 'global' ? broadcastOptions.isPinned : false,
      send_email: activeChat === 'global' ? broadcastOptions.sendEmail : false,
      send_sms: activeChat === 'global' ? broadcastOptions.sendSms : false,
      attachment_base64: attachmentBase64,
      reply_to_id: replyTo?.id || null
    };

    setNewMessage('');
    setAttachmentBase64(null);
    setAttachmentPreview(null);
    setReplyTo(null);
    // Optimistic UI could happen here
    try {
      const data = await fetchBackend('/admin_chat.php?action=send', {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      if (data.success) {
        if (data.broadcast) {
           addToast(data.broadcast, 'success');
        }
        // Reset broadcast options
        setBroadcastOptions({ isPinned: false, sendEmail: false, sendSms: false });
        fetchMessages();
        // Force scroll to bottom when sending
        setTimeout(scrollToBottom, 500);
      } else {
        addToast(data.error || 'Failed to send message', 'error');
      }
    } catch (err) {
       addToast('Network error', 'error');
    }
  };

  const togglePin = async (msgId, currentState) => {
    try {
      const res = await fetchBackend('/api/admin_chat.php?action=pin', {
        method: 'POST',
        body: JSON.stringify({ message_id: msgId, is_pinned: !currentState })
      });
      if (res.success) {
        fetchMessages();
      }
    } catch (e) {
      addToast('Error toggling pin status', 'error');
    }
  };

  const fetchMaintStats = async () => {
    setMaintLoading(true);
    try {
      const data = await fetchBackend('/admin_chat_maintenance.php?action=stats');
      if (data.success) {
        setMaintStats(data.stats);
      }
    } catch (err) {
      addToast('Failed to fetch maintenance stats', 'error');
    } finally {
      setMaintLoading(false);
    }
  };

  const handleMaintenanceAction = async (action, body = {}) => {
    if (!(await confirm(`Are you sure you want to perform: ${action}?`))) return;
    setMaintLoading(true);
    try {
      const data = await fetchBackend(`/admin_chat_maintenance.php?action=${action}`, {
        method: 'POST',
        body: JSON.stringify(body)
      });
      if (data.success) {
        addToast(data.message, 'success');
        fetchMaintStats();
      } else {
        addToast(data.error || 'Action failed', 'error');
      }
    } catch (err) {
      addToast('Network error', 'error');
    } finally {
      setMaintLoading(false);
    }
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      addToast('File too large. Maximum size is 5MB', 'error');
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      setAttachmentBase64(event.target.result);
      // For simplicity, we just show image previews
      if (file.type.startsWith('image/')) {
        setAttachmentPreview(event.target.result);
      } else {
        setAttachmentPreview('file_icon'); // A placeholder string to show a file UI
      }
    };
    reader.readAsDataURL(file);
  };

  const clearAttachment = () => {
    setAttachmentBase64(null);
    setAttachmentPreview(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const getInitials = (user) => {
     if (user.avatar_text) return user.avatar_text;
     return user.name ? user.name.slice(0, 2).toUpperCase() : '??';
  };

  const pinnedMessages = messages.filter(m => m.is_pinned == 1);
  const activeChatUser = activeChat !== 'global' ? users.find(u => u.id === activeChat) : null;

  return (
    <div className="animate-fade-in" style={{ padding: '0 0 32px', display: 'flex', flexDirection: 'column', height: '100%' }}>
      
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '24px' }}>
        <div>
           <h1 style={{ fontSize: '32px', fontWeight: 900, margin: '0 0 8px 0', color: 'var(--text-main)' }}>
             Staff Hub
           </h1>
           <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '15px' }}>Team collaboration & internal announcements</p>
        </div>
        {isSuper && (
          <button 
            onClick={() => { setShowMaintenance(true); fetchMaintStats(); }}
            style={{ 
              display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 20px', 
              borderRadius: '12px', background: 'var(--bg-surface)', border: '1px solid var(--border-light)',
              fontSize: '14px', fontWeight: 700, color: 'var(--text-main)', cursor: 'pointer',
              boxShadow: '0 2px 8px rgba(0,0,0,0.05)', transition: 'all 0.2s'
            }}
          >
            <Settings size={18} /> Maintenance
          </button>
        )}
      </div>

      {/* Maintenance Modal */}
      {showMaintenance && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px', backdropFilter: 'blur(4px)' }}>
          <div className="glass" style={{ width: '100%', maxWidth: '500px', padding: '32px', borderRadius: '24px', background: 'var(--bg-surface)', boxShadow: '0 20px 50px rgba(0,0,0,0.2)', border: '1px solid var(--border-light)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
              <h2 style={{ fontSize: '20px', fontWeight: 900, display: 'flex', alignItems: 'center', gap: '10px' }}>
                <ShieldAlert size={24} color="#ef4444" /> System Maintenance
              </h2>
              <button onClick={() => setShowMaintenance(false)} style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}><X size={24} /></button>
            </div>

            {maintLoading && !maintStats ? (
              <div style={{ padding: '40px', textAlign: 'center' }}><Loader className="spin" /></div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                {maintStats && (
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', background: 'var(--bg-main)', padding: '16px', borderRadius: '16px' }}>
                    <div style={{ fontSize: '13px' }}><div style={{ color: 'var(--text-muted)' }}>Messages</div><strong>{maintStats.total_messages}</strong></div>
                    <div style={{ fontSize: '13px' }}><div style={{ color: 'var(--text-muted)' }}>Attachments</div><strong>{maintStats.with_attachments}</strong></div>
                    <div style={{ fontSize: '13px' }}><div style={{ color: 'var(--text-muted)' }}>Traffic Logs</div><strong>{maintStats.traffic_logs}</strong></div>
                    <div style={{ fontSize: '13px' }}><div style={{ color: 'var(--text-muted)' }}>Oldest Msg</div><strong>{maintStats.oldest_message ? new Date(maintStats.oldest_message).toLocaleDateString() : 'N/A'}</strong></div>
                  </div>
                )}

                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  <button 
                    onClick={() => handleMaintenanceAction('prune', { days: 180 })}
                    className="btn-warn"
                    style={{ width: '100%', padding: '14px', borderRadius: '12px', display: 'flex', alignItems: 'center', gap: '10px', justifyContent: 'center' }}
                  >
                    <Trash2 size={18} /> Prune Messages (&gt; 180 days)
                  </button>
                  <button 
                    onClick={() => handleMaintenanceAction('clean_orphans')}
                    className="btn-secondary"
                    style={{ width: '100%', padding: '14px', borderRadius: '12px', display: 'flex', alignItems: 'center', gap: '10px', justifyContent: 'center' }}
                  >
                    <FileSearch size={18} /> Cleanup Orphan Files
                  </button>
                  <button 
                    onClick={() => handleMaintenanceAction('clear_traffic')}
                    className="btn-danger"
                    style={{ width: '100%', padding: '14px', borderRadius: '12px', display: 'flex', alignItems: 'center', gap: '10px', justifyContent: 'center' }}
                  >
                    <Database size={18} /> Clear Traffic Logs
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="staff-chat-container" style={{ 
        display: 'flex', 
        height: 'calc(100vh - 190px)', 
        background: 'var(--bg-surface)', 
        borderRadius: '24px', 
        border: '1px solid var(--border-light)', 
        overflow: 'hidden',
        boxShadow: 'var(--shadow-premium, 0 8px 30px rgba(0,0,0,0.05))'
      }}>
        {/* Sidebar */}
      <div className="chat-sidebar" style={{ 
        width: '320px', 
        borderRight: '1px solid var(--border-light)', 
        display: 'flex', 
        flexDirection: 'column',
        background: 'var(--bg-main)'
      }}>
        <div style={{ padding: '24px', borderBottom: '1px solid var(--border-light)', background: 'var(--bg-surface-secondary)' }}>
          <h2 style={{ fontSize: '18px', fontWeight: 800, margin: '0 0 16px', display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--text-main)' }}>
            <Users size={20} color="var(--primary-blue)" /> Staff Members
          </h2>
          <div style={{ position: 'relative' }}>
            <Search size={16} color="var(--text-muted)" style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }} />
            <input 
              type="text" 
              placeholder="Filter names..."
              style={{ 
                width: '100%', 
                padding: '10px 16px 10px 40px', 
                borderRadius: '10px', 
                border: '1px solid var(--border-light)', 
                background: 'var(--bg-surface)', 
                outline: 'none', 
                color: 'var(--text-main)', 
                fontSize: '13px',
                transition: 'border-color 0.2s, box-shadow 0.2s'
              }}
              onFocus={(e) => {
                e.target.style.borderColor = 'var(--primary-blue)';
                e.target.style.boxShadow = '0 0 0 3px rgba(30, 58, 138, 0.05)';
              }}
              onBlur={(e) => {
                e.target.style.borderColor = 'var(--border-light)';
                e.target.style.boxShadow = 'none';
              }}
            />
          </div>
        </div>
        
        <div className="chat-contacts custom-scrollbar" style={{ overflowY: 'auto', flex: 1, padding: '12px' }}>
          
          <div 
            onClick={() => setActiveChat('global')}
            style={{
              padding: '16px',
              borderRadius: '16px',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '12px',
              background: activeChat === 'global' ? 'var(--bg-surface)' : 'transparent',
              border: `1px solid ${activeChat === 'global' ? 'var(--primary-blue)' : 'transparent'}`,
              marginBottom: activeChat === 'global' && isAdminOrManager ? '8px' : '16px',
              transition: 'all 0.2s'
            }}
          >
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--primary-blue)', color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
               <Megaphone size={20} />
            </div>
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 700, fontSize: '14px' }}>Global Updates</div>
              <div style={{ fontSize: '12px', color: 'var(--text-muted)' }}>Company-wide broadcasts</div>
            </div>
          </div>

          {/* Broadcast Tools */}
          {isAdminOrManager && activeChat === 'global' && (
             <div style={{ padding: '16px', margin: '0 8px 16px', background: 'rgba(59, 130, 246, 0.05)', borderRadius: '12px', border: '1px solid rgba(59, 130, 246, 0.1)' }}>
               <div style={{ fontSize: '12px', fontWeight: 800, color: 'var(--primary-blue)', textTransform: 'uppercase', marginBottom: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                  <Megaphone size={14} /> Broadcast Tools
               </div>
               <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                 <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer' }}>
                   <input 
                     type="checkbox" 
                     checked={broadcastOptions.isPinned} 
                     onChange={(e) => setBroadcastOptions(prev => ({ ...prev, isPinned: e.target.checked }))} 
                     style={{ accentColor: 'var(--primary-blue)', cursor: 'pointer', width: '16px', height: '16px' }}
                   />
                   Pin Announcement
                 </label>
                 
                 <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer' }}>
                   <input 
                     type="checkbox" 
                     checked={broadcastOptions.sendEmail} 
                     onChange={(e) => setBroadcastOptions(prev => ({ ...prev, sendEmail: e.target.checked }))}
                     style={{ accentColor: 'var(--primary-blue)', cursor: 'pointer', width: '16px', height: '16px' }}
                   />
                   Alert via Email
                 </label>

                 <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer' }}>
                   <input 
                     type="checkbox" 
                     checked={broadcastOptions.sendSms} 
                     onChange={(e) => setBroadcastOptions(prev => ({ ...prev, sendSms: e.target.checked }))}
                     style={{ accentColor: 'var(--primary-blue)', cursor: 'pointer', width: '16px', height: '16px' }}
                   />
                   Alert via SMS
                 </label>
               </div>
             </div>
          )}

          <div style={{ fontSize: '12px', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '8px', paddingLeft: '8px' }}>
            Direct Messages
          </div>

          {users.map(u => (
            <div 
              key={u.id}
              onClick={() => setActiveChat(u.id)}
              style={{
                padding: '12px',
                borderRadius: '16px',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                background: activeChat === u.id ? 'var(--bg-surface)' : 'transparent',
                border: `1px solid ${activeChat === u.id ? 'var(--primary-blue)' : 'transparent'}`,
                transition: 'all 0.2s',
                marginBottom: '4px'
              }}
            >
              <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: 'var(--secondary-blue)', color: 'var(--primary-blue)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: '14px', position: 'relative' }}>
                {u.profile_image ? (
                  <img src={formatImageUrl(u.profile_image)} alt={u.name} style={{ width: '100%', height: '100%', borderRadius: '50%', objectFit: 'cover' }} />
                ) : (
                  getInitials(u)
                )}
                <div style={{ position: 'absolute', bottom: 0, right: 0, width: '10px', height: '10px', background: '#22c55e', borderRadius: '50%', border: '2px solid var(--bg-main)' }}></div>
              </div>
              <div style={{ flex: 1, overflow: 'hidden' }}>
                <div style={{ fontWeight: 600, fontSize: '14px', whiteSpace: 'nowrap', textOverflow: 'ellipsis', overflow: 'hidden' }}>{u.name}</div>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'capitalize' }}>{u.role.replace('_', ' ')}</div>
              </div>
              {u.unread_count > 0 && (
                <div style={{ background: '#ef4444', color: 'white', fontSize: '10px', fontWeight: 800, padding: '2px 6px', borderRadius: '10px' }}>
                  {u.unread_count}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Main Chat Area */}
      <div className="chat-main" style={{ flex: 1, display: 'flex', flexDirection: 'column', background: 'var(--bg-surface)' }}>
        
        {/* Chat Header */}
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-light)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: 'var(--bg-surface)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
            {activeChat === 'global' ? (
              <>
                <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--primary-blue)', color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Users size={24} />
                </div>
                <div>
                  <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 800 }}>Global Staff Channel</h3>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Messages here are visible to all staff</div>
                </div>
              </>
            ) : activeChatUser ? (
              <>
                <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--secondary-blue)', color: 'var(--primary-blue)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: '16px' }}>
                  {activeChatUser.profile_image ? (
                    <img src={activeChatUser.profile_image} alt={activeChatUser.name} style={{ width: '100%', height: '100%', borderRadius: '50%', objectFit: 'cover' }} />
                  ) : (
                    getInitials(activeChatUser)
                  )}
                </div>
                <div>
                  <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 800 }}>{activeChatUser.name}</h3>
                  <div style={{ fontSize: '13px', color: 'var(--text-muted)', textTransform: 'capitalize' }}>{activeChatUser.role.replace('_', ' ')}</div>
                </div>
              </>
            ) : null}
          </div>
        </div>

        {/* Pinned Messages Header */}
        {activeChat === 'global' && pinnedMessages.length > 0 && (
          <div style={{ background: 'var(--info-bg)', borderBottom: '1px solid var(--border-light)', padding: '12px 24px', maxHeight: '140px', overflowY: 'auto' }}>
            <div style={{ fontSize: '11px', fontWeight: 800, color: 'var(--accent-blue)', textTransform: 'uppercase', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
               <MapPin size={12} fill="var(--accent-blue)" /> Pinned Announcements
            </div>
            {pinnedMessages.map(msg => (
               <div key={msg.id} style={{ fontSize: '13px', marginBottom: '8px', padding: '8px', background: 'var(--bg-surface)', borderRadius: '8px', border: '1px solid var(--border-light)' }}>
                 <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                    <span style={{ fontWeight: 700 }}>{msg.sender_name}</span>
                    <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>{new Date(msg.created_at).toLocaleString()}</span>
                 </div>
                 {msg.message}
                 {isAdminOrManager && (
                   <button onClick={() => togglePin(msg.id, msg.is_pinned)} style={{ display: 'block', background: 'none', border: 'none', color: '#ef4444', fontSize: '11px', cursor: 'pointer', marginTop: '6px', padding: 0 }}>
                     Unpin
                   </button>
                 )}
               </div>
            ))}
          </div>
        )}

        {/* Messages Feed */}
        <div 
          className="chat-feed custom-scrollbar" 
          ref={chatContainerRef}
          onScroll={handleScroll}
          style={{ flex: 1, padding: '24px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '20px', position: 'relative' }}
        >
          {loading && messages.length === 0 ? (
            <div style={{ display: 'flex', justifyContent: 'center', padding: '40px' }}><Loader className="spin" color="var(--primary-blue)" /></div>
          ) : messages.length === 0 ? (
            <div style={{ textAlign: 'center', color: 'var(--text-muted)', marginTop: '40px' }}>No messages yet. Start the conversation!</div>
          ) : (
            messages.map((msg) => {
              const isMine = parseInt(msg.sender_id) === parseInt(currentUser.id);
              
              return (
                <div key={msg.id} style={{ display: 'flex', flexDirection: 'column', alignItems: isMine ? 'flex-end' : 'flex-start' }}>
                  <div style={{ display: 'flex', gap: '12px', maxWidth: '85%', flexDirection: isMine ? 'row-reverse' : 'row' }}>
                    
                    {!isMine && (
                      <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--secondary-blue)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: '12px', flexShrink: 0 }}>
                         {msg.profile_image ? <img src={msg.profile_image} style={{ width: '100%', height: '100%', borderRadius: '50%', objectFit: 'cover' }} /> : (msg.avatar_text || msg.sender_name.slice(0, 2).toUpperCase())}
                      </div>
                    )}
                    
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: isMine ? 'flex-end' : 'flex-start' }}>
                      {!isMine && <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginBottom: '4px', marginLeft: '4px' }}>{msg.sender_name}</div>}
                      
                      <div className="message-bubble-row" style={{ display: 'flex', alignItems: 'center', gap: '8px', flexDirection: isMine ? 'row-reverse' : 'row', width: '100%' }}>
                        <div style={{ 
                          padding: '12px 16px', 
                          background: isMine ? 'var(--primary-blue)' : 'var(--bg-surface-secondary)', 
                          color: isMine ? 'white' : 'var(--text-main)',
                          borderRadius: isMine ? '18px 18px 4px 18px' : '18px 18px 18px 4px',
                          fontSize: '14px',
                          lineHeight: '1.6',
                          boxShadow: '0 2px 8px rgba(0,0,0,0.03)',
                          border: isMine ? 'none' : '1px solid var(--border-light)',
                          position: 'relative',
                          wordBreak: 'break-word',
                          flex: 1
                        }}>
                          {/* Quoted Message */}
                          {msg.reply_to_id && (
                            <div style={{ 
                              background: isMine ? 'rgba(255,255,255,0.15)' : 'var(--bg-main)', 
                              padding: '8px 12px', 
                              borderRadius: '8px', 
                              borderLeft: '4px solid ' + (isMine ? 'white' : 'var(--primary-blue)'),
                              marginBottom: '10px',
                              fontSize: '12px',
                              opacity: 0.95
                            }}>
                              <div style={{ fontWeight: 800, marginBottom: '2px', color: isMine ? 'white' : 'var(--primary-blue)' }}>{msg.reply_to_name}</div>
                              <div style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{msg.reply_to_message}</div>
                            </div>
                          )}

                          {msg.is_pinned == 1 && (
                            <div style={{ position: 'absolute', top: '-10px', right: isMine ? 'auto' : '-10px', left: isMine ? '-10px' : 'auto', background: 'var(--bg-surface)', padding: '2px', borderRadius: '50%', border: '1px solid var(--border-light)' }}>
                              <MapPin size={14} color="#ef4444" fill="#ef4444" />
                            </div>
                          )}
                          
                          {msg.attachment_url && (
                            <div style={{ marginBottom: '8px', maxWidth: '300px', borderRadius: '8px', overflow: 'hidden' }}>
                              {msg.attachment_url.match(/\.(jpeg|jpg|gif|png)$/i) != null ? (
                                <a href={formatImageUrl(msg.attachment_url)} target="_blank" rel="noopener noreferrer">
                                  <img src={formatImageUrl(msg.attachment_url)} alt="Attachment" style={{ width: '100%', height: 'auto', display: 'block' }} />
                                </a>
                              ) : (
                                <a href={formatImageUrl(msg.attachment_url)} target="_blank" rel="noopener noreferrer" style={{ display: 'flex', alignItems: 'center', gap: '8px', color: isMine ? 'white' : 'var(--primary-blue)', textDecoration: 'underline' }}>
                                  <Paperclip size={16} /> View Attachment
                                </a>
                              )}
                            </div>
                          )}
                          
                          {msg.message}
                        </div>

                        {/* Reply Action Button */}
                        <button 
                          onClick={() => setReplyTo({ id: msg.id, message: msg.message, name: msg.sender_name })}
                          style={{ 
                            background: 'var(--bg-surface)', 
                            border: '1px solid var(--border-light)', 
                            borderRadius: '50%', 
                            width: '32px', 
                            height: '32px', 
                            display: 'flex', 
                            alignItems: 'center', 
                            justifyContent: 'center', 
                            color: 'var(--text-muted)',
                            cursor: 'pointer',
                            opacity: 0.6,
                            flexShrink: 0
                          }}
                          title="Reply"
                        >
                          <ChevronLeft size={16} style={{ transform: 'rotate(180deg)' }} />
                        </button>
                      </div>

                      <div style={{ display: 'flex', alignItems: 'center', gap: '6px', marginTop: '6px', marginRight: '4px', fontSize: '11px', color: 'var(--text-muted)' }}>
                        {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        
                        {isMine && activeChat !== 'global' && (
                          msg.is_read == 1 ? <CheckCheck size={14} color="#3b82f6" /> : <Check size={14} />
                        )}

                        {isAdminOrManager && activeChat === 'global' && msg.is_pinned == 0 && (
                          <span onClick={() => togglePin(msg.id, 0)} style={{ cursor: 'pointer', color: 'var(--primary-blue)', marginLeft: '8px' }}>Pin Message</span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              );
            })
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Scroll to Bottom Button */}
        {showScrollButton && (
          <button
            onClick={scrollToBottom}
            style={{
              position: 'absolute', bottom: '100px', right: '40px',
              width: '40px', height: '40px', borderRadius: '50%',
              background: 'var(--primary-blue)', color: 'white',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 4px 12px rgba(0,0,0,0.15)', cursor: 'pointer',
              border: 'none', zIndex: 50, transition: 'all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275)'
            }}
            onMouseOver={(e) => e.currentTarget.style.transform = 'scale(1.1)'}
            onMouseOut={(e) => e.currentTarget.style.transform = 'scale(1)'}
          >
            <ChevronDown size={20} />
          </button>
        )}

        {/* Input Area */}
        <div style={{ padding: '20px 24px', borderTop: '1px solid var(--border-light)', background: 'var(--bg-surface)' }}>
          <form onSubmit={handleSendMessage}>
            <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-end', position: 'relative' }}>
              {/* Attachment Preview UI */}
              {attachmentPreview && (
                <div style={{ position: 'absolute', bottom: '60px', left: '0', background: 'var(--bg-main)', border: '1px solid var(--border-light)', padding: '8px', borderRadius: '12px', display: 'flex', alignItems: 'center', gap: '8px', zIndex: 10 }}>
                  {attachmentPreview === 'file_icon' ? (
                     <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--text-main)', fontSize: '13px', fontWeight: 600 }}>
                       <Paperclip size={16} color="var(--primary-blue)" /> Attached File
                     </div>
                  ) : (
                     <img src={attachmentPreview} alt="Preview" style={{ height: '60px', borderRadius: '6px', objectFit: 'cover' }} />
                  )}
                  <button type="button" onClick={clearAttachment} style={{ background: 'var(--danger-bg)', border: 'none', color: 'var(--danger)', borderRadius: '50%', width: '24px', height: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}>
                    <X size={14} />
                  </button>
                </div>
              )}

              {/* Reply Preview */}
              {replyTo && (
                <div style={{ position: 'absolute', bottom: '60px', left: '0', right: '0', padding: '12px 16px', borderRadius: '12px', background: 'var(--bg-main)', borderLeft: '4px solid var(--primary-blue)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', zIndex: 10, border: '1px solid var(--border-light)' }}>
                  <div style={{ overflow: 'hidden' }}>
                    <div style={{ fontSize: '11px', fontWeight: 800, color: 'var(--primary-blue)', marginBottom: '4px' }}>Replying to {replyTo.name}</div>
                    <div style={{ fontSize: '13px', color: 'var(--text-muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{replyTo.message}</div>
                  </div>
                  <button onClick={() => setReplyTo(null)} style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer', padding: '4px' }}>
                    <X size={16} />
                  </button>
                </div>
              )}

              <input 
                 type="file" 
                 hidden 
                 ref={fileInputRef} 
                 onChange={handleFileChange}
                 accept="image/*,.pdf,.doc,.docx"
              />
              <button onClick={() => fileInputRef.current?.click()} type="button" style={{ width: '48px', height: '48px', borderRadius: '16px', background: 'var(--bg-main)', border: '1px solid var(--border-light)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: attachmentPreview ? 'var(--primary-blue)' : 'var(--text-muted)', cursor: 'pointer', transition: 'all 0.2s' }}>
                 <Paperclip size={20} />
              </button>
              
              <textarea 
                value={newMessage}
                onChange={(e) => setNewMessage(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleSendMessage(e);
                  }
                }}
                placeholder={activeChat === 'global' ? "Broadcast a message to all staff..." : "Type a message..."}
                style={{ 
                  flex: 1, 
                  minHeight: '48px', 
                  maxHeight: '150px', 
                  resize: 'none', 
                  padding: '14px 18px',
                  borderRadius: '14px',
                  lineHeight: '1.5',
                  background: 'var(--bg-surface-secondary)',
                  color: 'var(--text-main)',
                  border: '1px solid var(--border-light)',
                  fontSize: '14px',
                  outline: 'none',
                  transition: 'border-color 0.2s'
                }}
                onFocus={(e) => e.target.style.borderColor = 'var(--primary-blue)'}
                onBlur={(e) => e.target.style.borderColor = 'var(--border-light)'}
              />
              
              <button 
                type="submit" 
                disabled={!newMessage.trim() && !attachmentBase64}
                style={{ 
                  width: '48px', height: '48px', borderRadius: '16px', 
                  background: (newMessage.trim() || attachmentBase64) ? 'var(--primary-blue)' : 'var(--bg-main)', 
                  border: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center', 
                  color: (newMessage.trim() || attachmentBase64) ? 'white' : 'var(--text-muted)', 
                  cursor: (newMessage.trim() || attachmentBase64) ? 'pointer' : 'not-allowed',
                  transition: 'background 0.2s'
                }}
              >
                 <Send size={20} style={{ marginLeft: '4px' }} />
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
);
}
