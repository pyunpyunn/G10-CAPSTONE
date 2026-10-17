import {
  Activity,
  ArrowRight,
  Boxes,
  LifeBuoy,
  Package,
  ShieldAlert,
  Sparkles,
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
  const isLowStock = !isOutOfStock && Number(item.quantity) <= 20
  const isNew = Boolean(item.is_new)
  const categoryMeta = getCategoryMeta(item.category_key || item.category)
  const CategoryIcon = categoryMeta.icon

  return (
    <article
      className={`human-catalog-card ${isOutOfStock ? 'is-out-of-stock' : 'is-in-stock'}`}
      onClick={() => onSelect?.(item)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          onSelect?.(item)
        }
      }}
      aria-label={`${item.name || item.label} - ${isOutOfStock ? 'Out of stock' : `${item.quantity} available in stock`}`}
    >
      {/* Header Bar: Category Avatar & Live Stock Tag */}
      <div className="card-header-bar">
        <div className="card-identity-group">
          <div className={`card-category-avatar ${categoryMeta.pillClass}`}>
            <CategoryIcon size={18} />
          </div>
          <div className="card-category-text">
            <span className="card-category-name">{item.category_label || categoryMeta.label}</span>
            {item.sku && <span className="card-sku-text" title={`SKU: ${item.sku}`}>{item.sku}</span>}
          </div>
        </div>

        <div className="card-status-wrapper">
          {isOutOfStock ? (
            <span className="human-stock-pill pill-out">
              <span className="stock-dot" />
              <span>Out of stock</span>
            </span>
          ) : isLowStock ? (
            <span className="human-stock-pill pill-low">
              <span className="stock-dot" />
              <span>Low stock ({item.quantity})</span>
            </span>
          ) : (
            <span className="human-stock-pill pill-in">
              <span className="stock-dot" />
              <span>In stock</span>
            </span>
          )}
        </div>
      </div>

      {/* Main Content: Title & Natural Human Metadata */}
      <div className="card-main-content">
        <h3 className="human-card-title" title={item.name || item.label}>
          {item.name || item.label}
        </h3>

        {/* Clean, comma/dot separated metadata without pill fatigue */}
        <div className="human-meta-line">
          <span>{item.storage_location || 'Warehouse Depot'}</span>
          <span className="meta-bullet">·</span>
          <span>{item.type || 'Consumable'}</span>
          {item.expiration_date && (
            <>
              <span className="meta-bullet">·</span>
              <span className="meta-exp">Exp: {item.expiration_date}</span>
            </>
          )}
        </div>
      </div>

      {/* Natural Inventory Availability (No inner bordered box!) */}
      <div className="human-inventory-row">
        <div className="human-inventory-count">
          <span className="count-number">
            {isOutOfStock ? 0 : Number(item.quantity).toLocaleString()}
          </span>
          <span className="count-unit">
            {item.unit || 'units'} available
          </span>
        </div>

        {isNew && (
          <span className="human-new-badge" title="Newly registered catalog item">
            <Sparkles size={11} /> New
          </span>
        )}
      </div>

      {/* Card Actions Footer */}
      <div className="human-card-footer">
        <button
          type="button"
          className="btn-human-specs"
          onClick={(e) => {
            e.stopPropagation()
            onSelect?.(item)
          }}
          title="View resource specifications"
        >
          View Specs
        </button>

        {isOutOfStock ? (
          <button
            type="button"
            className="btn-human-disabled"
            disabled
          >
            Out of Stock
          </button>
        ) : (
          <button
            type="button"
            className="btn-human-request"
            onClick={(e) => {
              e.stopPropagation()
              onRequestNow?.(item)
            }}
            title="Create a resource request for this item"
          >
            <span>Request</span>
            <ArrowRight size={13} />
          </button>
        )}
      </div>
    </article>
  )
}
