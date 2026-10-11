import {
  Activity,
  AlertCircle,
  ArrowRight,
  Boxes,
  Calendar,
  CheckCircle2,
  Eye,
  LifeBuoy,
  MapPin,
  Package,
  ShieldAlert,
  Sparkles,
  Tag,
  XCircle,
} from 'lucide-react'

// Helper to pick category visual styles & icons
function getCategoryMeta(categoryKey = '') {
  const key = String(categoryKey).toLowerCase()
  if (key.includes('food') || key.includes('water')) {
    return {
      label: 'Food & Water',
      pillClass: 'cat-pill-food',
      icon: Package,
    }
  }
  if (key.includes('med') || key.includes('health')) {
    return {
      label: 'Medical Supplies',
      pillClass: 'cat-pill-medical',
      icon: Activity,
    }
  }
  if (key.includes('rescue') || key.includes('gear')) {
    return {
      label: 'Rescue Equipment',
      pillClass: 'cat-pill-rescue',
      icon: LifeBuoy,
    }
  }
  if (key.includes('shelter') || key.includes('tent')) {
    return {
      label: 'Emergency Shelter',
      pillClass: 'cat-pill-shelter',
      icon: ShieldAlert,
    }
  }
  return {
    label: categoryKey || 'Relief Goods',
    pillClass: 'cat-pill-default',
    icon: Boxes,
  }
}

export default function RequestTypeCard({
  item,
  isFirst = false,
  onSelect,
  onRequestNow,
}) {
  const isOutOfStock = Boolean(item.is_out_of_stock || item.quantity <= 0)
  const isNew = Boolean(item.is_new)
  const categoryMeta = getCategoryMeta(item.category_key || item.category)
  const CategoryIcon = categoryMeta.icon

  return (
    <article
      className={`req-type-card ${isFirst && isNew ? 'is-first-priority' : ''} ${
        isOutOfStock ? 'is-out-of-stock' : 'is-in-stock'
      }`}
      onClick={() => onSelect?.(item)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          onSelect?.(item)
        }
      }}
      aria-label={`${item.name} - ${isOutOfStock ? 'Out of stock' : 'Available'}`}
    >
      {/* 1st Card Priority Strip (Only for newly declared item) */}
      {isFirst && isNew && (
        <div className="card-priority-strip" title="Prioritized 1st Card: Newly declared catalog item">
          <Sparkles size={12} className="sparkle-icon" />
          <span>Priority 1st Card • Newly Declared</span>
        </div>
      )}

      {/* Top Meta Row: Category Badge & SKU */}
      <div className="card-top-row">
        <div className={`card-cat-pill ${categoryMeta.pillClass}`}>
          <CategoryIcon size={12} className="cat-icon" />
          <span>{item.category_label || categoryMeta.label}</span>
        </div>

        {item.sku && (
          <code className="card-sku-tag" title={`SKU: ${item.sku}`}>
            {item.sku}
          </code>
        )}
      </div>

      {/* Title & Classification */}
      <div className="card-title-block">
        <h3 className="card-item-title" title={item.name || item.label}>
          {item.name || item.label}
        </h3>
        <div className="card-type-sub">
          <span>{item.type || 'Consumable'}</span>
          {item.expiration_date && (
            <>
              <span className="dot-sep">•</span>
              <span className="exp-note">Exp: {item.expiration_date}</span>
            </>
          )}
        </div>
      </div>

      {/* Stock Availability Badge ("pwede out of stock diri") */}
      <div className="card-stock-section">
        {isOutOfStock ? (
          <div className="stock-pill stock-pill-out" title="Currently out of stock in warehouse depot">
            <span className="stock-dot dot-out" />
            <span className="stock-label">Out of Stock</span>
            <span className="stock-count">0 {item.unit || 'units'}</span>
          </div>
        ) : (
          <div className="stock-pill stock-pill-in" title="In stock and available for dispatch">
            <span className="stock-dot dot-in" />
            <span className="stock-label">Available</span>
            <span className="stock-count">
              {Number(item.quantity).toLocaleString()} {item.unit || 'units'}
            </span>
          </div>
        )}
      </div>

      {/* Depot Location info */}
      <div className="card-location-row">
        <MapPin size={12} className="location-icon" />
        <span className="location-text">{item.storage_location || 'Warehouse Depot'}</span>
      </div>

      {/* Standardized Card Actions Footer */}
      <div className="card-footer">
        <button
          type="button"
          className="btn-card-specs"
          onClick={(e) => {
            e.stopPropagation()
            onSelect?.(item)
          }}
          title="View detailed resource specifications"
        >
          <Eye size={13} />
          <span>View Specs</span>
        </button>

        {isOutOfStock ? (
          <button
            type="button"
            className="btn-card-disabled"
            disabled
            title="Resource is currently out of stock"
          >
            Out of Stock
          </button>
        ) : (
          <button
            type="button"
            className="btn-card-request"
            onClick={(e) => {
              e.stopPropagation()
              onRequestNow?.(item)
            }}
            title="Create a resource request for this item"
          >
            <span>Request Item</span>
            <ArrowRight size={13} />
          </button>
        )}
      </div>
    </article>
  )
}
