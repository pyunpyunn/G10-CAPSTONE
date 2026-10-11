import { useState } from 'react'
import {
  Activity,
  AlertTriangle,
  ArrowRight,
  Boxes,
  Calendar,
  Check,
  CheckCircle2,
  Copy,
  Database,
  ExternalLink,
  LifeBuoy,
  MapPin,
  Package,
  ShieldAlert,
  Sparkles,
  Tag,
  X,
  XCircle,
} from 'lucide-react'

export default function RequestTypeDetailDrawer({
  item,
  isOpen,
  onClose,
  onRequestNow,
}) {
  const [copied, setCopied] = useState(false)

  if (!isOpen || !item) return null

  const isOutOfStock = Boolean(item.is_out_of_stock || item.quantity <= 0)
  const isNew = Boolean(item.is_new)

  const handleCopySku = () => {
    if (item.sku) {
      navigator.clipboard?.writeText(item.sku)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  return (
    <div className="rr-drawer-backdrop" onClick={onClose} role="presentation">
      <div
        className="rr-drawer-container req-type-detail-drawer"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={`Details for ${item.name}`}
      >
        {/* Header */}
        <div className="rr-drawer-header">
          <div className="rr-drawer-title-group">
            <span className="rr-drawer-kicker">Resource Request Type Details</span>
            <h2 className="rr-drawer-title">{item.name || item.label}</h2>
          </div>
          <button
            className="rr-drawer-close"
            type="button"
            onClick={onClose}
            aria-label="Close drawer"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="rr-drawer-body">
          {/* 1st Card Priority / NEW Banner */}
          {isNew && (
            <div className="rtd-alert-banner rtd-new-banner">
              <Sparkles size={18} className="sparkle-icon" />
              <div>
                <strong>Priority 1st Card: Newly Registered Type</strong>
                <p>This request type was recently added to the central disaster inventory catalog.</p>
              </div>
            </div>
          )}

          {/* Stock Status Banner */}
          <div className={`rtd-stock-hero ${isOutOfStock ? 'hero-out-of-stock' : 'hero-in-stock'}`}>
            <div className="hero-icon-box">
              {isOutOfStock ? <XCircle size={28} /> : <CheckCircle2 size={28} />}
            </div>
            <div className="hero-info">
              <span className="hero-kicker">Inventory Availability</span>
              <div className="hero-status-title">
                {isOutOfStock ? 'Currently Out of Stock' : 'In Stock & Ready for Dispatch'}
              </div>
              <div className="hero-quantity">
                <strong>{Number(item.quantity).toLocaleString()}</strong> {item.unit || 'units'} available
              </div>
            </div>
          </div>

          {/* Specification Cards Grid */}
          <div className="rtd-specs-grid">
            <div className="rtd-spec-card">
              <span className="spec-label">Resource Category</span>
              <div className="spec-value">
                <Package size={15} />
                <span>{item.category_label || item.category || 'General'}</span>
              </div>
            </div>

            <div className="rtd-spec-card">
              <span className="spec-label">SKU Identifier</span>
              <div className="spec-value sku-box">
                <Tag size={14} />
                <code>{item.sku || 'N/A'}</code>
                {item.sku && (
                  <button
                    type="button"
                    className="btn-copy-sku"
                    onClick={handleCopySku}
                    title="Copy SKU to clipboard"
                  >
                    {copied ? <Check size={12} className="text-success" /> : <Copy size={12} />}
                  </button>
                )}
              </div>
            </div>

            <div className="rtd-spec-card">
              <span className="spec-label">Resource Type</span>
              <div className="spec-value">
                <Boxes size={15} />
                <span>{item.type || 'Consumable'}</span>
              </div>
            </div>

            <div className="rtd-spec-card">
              <span className="spec-label">Warehouse Storage</span>
              <div className="spec-value">
                <MapPin size={15} />
                <span>{item.storage_location || 'Warehouse Depot'}</span>
              </div>
            </div>

            {item.expiration_date && (
              <div className="rtd-spec-card">
                <span className="spec-label">Expiration Date</span>
                <div className="spec-value">
                  <Calendar size={15} />
                  <span>{item.expiration_date}</span>
                </div>
              </div>
            )}
          </div>

          {/* TrackingAid Shared Database Status */}
          <div className="rtd-sync-card">
            <div className="sync-card-head">
              <Database size={16} className="text-primary" />
              <span>TrackingAid Shared Database Sync</span>
            </div>
            <p className="sync-card-desc">
              This request type is directly mapped to the central warehouse database (<code>trackingaid_db</code>).
              Handoff dispatches and stock adjustments are automatically reflected across ResQperation and TrackingAid.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="rr-drawer-footer rtd-drawer-footer">
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
          >
            Close
          </button>

          <button
            type="button"
            className={`btn btn-primary btn-request-action ${isOutOfStock ? 'btn-disabled' : ''}`}
            disabled={isOutOfStock}
            onClick={() => {
              if (!isOutOfStock) {
                onClose()
                onRequestNow?.(item)
              }
            }}
          >
            {isOutOfStock ? 'Item Out of Stock' : 'Request This Resource'} {!isOutOfStock && <ArrowRight size={16} />}
          </button>
        </div>
      </div>
    </div>
  )
}
