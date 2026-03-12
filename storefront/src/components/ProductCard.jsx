import React from 'react';
import { X, Star, Heart } from 'lucide-react';
import { useSettings } from '../context/SettingsContext';
import { useWishlist } from '../context/WishlistContext';
import { useUser } from '../context/UserContext';

export default function ProductCard({ id, name, price, image, rating, onClick, onRemove }) {
  const { formatPrice } = useSettings();
  const safeRating = parseFloat(rating) || 0;
  
  // Use hooks for wishlist and user state
  const { toggleWishlist, isInWishlist } = useWishlist();
  const { user, openAuthModal } = useUser();
  const inWishlist = isInWishlist(id); 

  const handleWishlistClick = (e) => {
    e.stopPropagation();
    if (!user) {
      openAuthModal('signin');
      return;
    }
    toggleWishlist({ id, name, price, image, rating });
  };

  return (
    <div className="product-card animate-scale-in" onClick={onClick} style={{ position: 'relative' }}>
      {/* Heart Toggle Button - Shown on all cards if onRemove is NOT present (Shop view) */}
      {!onRemove && (
        <button 
          onClick={handleWishlistClick}
          className={`wishlist-btn ${inWishlist ? 'active' : ''}`}
          title={inWishlist ? "Remove from wishlist" : "Add to wishlist"}
        >
          <Heart 
            size={18} 
            fill={inWishlist ? "currentColor" : "none"} 
            strokeWidth={inWishlist ? 0 : 2}
          />
        </button>
      )}
      
      {onRemove && (
        <button 
          onClick={(e) => {
            e.stopPropagation();
            onRemove();
          }}
          className="wishlist-btn" 
          title="Remove from favorites"
        >
          <X size={18} />
        </button>
      )}

      {image ? (
        <img src={image} className="product-image" alt={name} />
      ) : (
        <div className="product-image" style={{ background: 'var(--bg-surface-secondary)' }}></div>
      )}

      <div className="product-info">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
            <h3 style={{ margin: 0 }}>{name}</h3>
            <div style={{ display: 'flex', alignItems: 'center', gap: '2px', background: 'var(--warning-bg)', padding: '2px 6px', borderRadius: '4px' }}>
                <Star size={10} fill="var(--warning)" color="var(--warning)" />
                <span style={{ fontSize: '10px', fontWeight: 700, color: 'var(--warning)' }}>{safeRating.toFixed(1)}</span>
            </div>
        </div>
        <p style={{ margin: 0, fontWeight: 600 }}>{formatPrice(price)}</p>
      </div>
    </div>
  );
}