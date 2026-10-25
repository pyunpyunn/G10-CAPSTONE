import { Box, MapPin } from 'lucide-react'

// Helper to format category labels to match clean sentence case styling
function getCategoryDisplay(item) {
  const catKey = String(
    item.category_key || item.category || item.category_label || '',
  ).toLowerCase()
  if (catKey.includes('med') || catKey.includes('health')) return 'Medical kit'
  if (catKey.includes('food') || catKey.includes('water')) return 'Food & water'
  if (catKey.includes('comm')) return 'Communications'
  if (catKey.includes('rescue') || catKey.includes('gear')) return 'Rescue equipment'
  if (catKey.includes('shelter') || catKey.includes('tent')) return 'Emergency shelter'
  return item.category_label || item.category || 'Relief goods'
}

export default function RequestTypeCard({
  item,
  isSelected = false,
  onSelect,
  onRequestNow,
}) {
  const quantity = Number(item.quantity ?? 0)
  const isOutOfStock = Boolean(item.is_out_of_stock || quantity <= 0)
  const isLowStock = !isOutOfStock && quantity <= 20
  const isNew = Boolean(item.is_new)

  // Clean location string (e.g. remove "Evacuation: " prefix)
  const rawLocation =
    item.storage_location ||
    item.location ||
    item.depot_location ||
    item.detail ||
    'Barangay Resource Depot'
  const locationText = rawLocation.replace(/^Evacuation:\s*/i, '')

  // Code / SKU identifier
  const itemSku =
    item.sku ||
    item.tracking_reference ||
    (item.id
      ? String(item.id).startsWith('TA-') || String(item.id).startsWith('RR-')
        ? item.id
        : `TA-${item.id}`
      : '')

  const categoryLabel = getCategoryDisplay(item)

  // Progress bar fill calculation matching design proportions (5 boxes -> 25%, 8 kits -> 40%, 10 boxes -> 50%)
  let progressPercent = 0
  if (isOutOfStock) {
    progressPercent = 0
  } else if (isLowStock) {
    progressPercent = Math.max(15, Math.min(85, Math.round((quantity / 20) * 100)))
  } else {
    progressPercent = 100
  }

  return (
    <article
      className={`human-catalog-card ${isOutOfStock ? 'is-out-of-stock' : 'is-in-stock'} ${
        isSelected ? 'is-selected' : ''
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
      aria-label={`${item.name || item.label} - ${
        isOutOfStock ? 'Out of stock' : `${quantity} available in stock`
      }`}
    >
      {/* Header Bar: Category with soft-blue cube icon & clean Stock Pill */}
      <div className="card-header-bar">
        <div className="card-category-badge">
          <div className="card-category-icon-box">
            <Box size={16} strokeWidth={2.2} />
          </div>
          <span className="card-category-label">{categoryLabel}</span>
        </div>

        <div className="card-status-wrapper">
          {isOutOfStock ? (
            <span className="human-stock-pill pill-out">Out of stock</span>
          ) : isLowStock ? (
            <span className="human-stock-pill pill-low">Low stock</span>
          ) : (
            <span className="human-stock-pill pill-in">In stock</span>
          )}
        </div>
      </div>

      {/* Main Content: Title with optional 'New' badge, SKU code, and Location */}
      <div className="card-main-content">
        <div className="card-title-row">
          <h3 className="human-card-title" title={item.name || item.label}>
            {item.name || item.label}
          </h3>
          {isNew && (
            <span className="human-new-badge" title="Newly registered catalog item">
              New
            </span>
          )}
        </div>

        {itemSku && <div className="card-code-text">{itemSku}</div>}

        <div className="card-location-row" title={`Location: ${locationText}`}>
          <MapPin size={13} className="card-location-icon" />
          <span className="card-location-text">{locationText}</span>
        </div>
      </div>

      {/* Availability Count & Colored Progress Bar Meter */}
      <div className="card-stock-meter-section">
        <div className="card-stock-count-row">
          <span className="stock-count-number">
            {isOutOfStock ? 0 : quantity.toLocaleString()}
          </span>
          <span className="stock-count-unit">
            {item.unit || 'units'} available
          </span>
        </div>

        <div className="card-stock-progress-track">
          <div
            className={`card-stock-progress-fill ${
              isOutOfStock ? 'fill-out' : isLowStock ? 'fill-low' : 'fill-in'
            }`}
            style={{ width: `${progressPercent}%` }}
          />
        </div>
      </div>

      {/* Card Actions Footer: View specs & Request buttons */}
      <div className="human-card-footer">
        <button
          type="button"
          className="btn-human-specs"
          onClick={(e) => {
            e.stopPropagation()
            onSelect?.(item)
          }}
          title="View specs"
        >
          View specs
        </button>

        {isOutOfStock ? (
          <button
            type="button"
            className="btn-human-disabled"
            disabled
          >
            Out of stock
          </button>
        ) : (
          <button
            type="button"
            className="btn-human-request"
            onClick={(e) => {
              e.stopPropagation()
              onRequestNow?.(item)
            }}
            title="Request"
          >
            Request
          </button>
        )}
      </div>
    </article>
  )
}
