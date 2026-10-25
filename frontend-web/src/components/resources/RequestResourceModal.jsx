import { useEffect, useMemo, useState } from 'react'
import {
  AlertCircle,
  Box,
  Building,

  Loader2,
  MapPin,
  Minus,
  Plus,
  Send,
  Tag,
  User,
  X,
} from 'lucide-react'
import { createResourceRequest, getResourceRequests } from '../../api/resourceRequestApi'
import { getCurrentUser } from '../../api/authApi'

function formatUnit(qty, unit = 'units') {
  const q = Number(qty) || 0
  const u = String(unit || 'units').trim()
  if (q === 1) {
    if (u.toLowerCase() === 'packs') return 'pack'
    if (u.toLowerCase() === 'boxes') return 'box'
    if (u.toLowerCase() === 'pieces' || u.toLowerCase() === 'pcs') return 'pc'
    if (u.toLowerCase() === 'bottles') return 'bottle'
    if (u.toLowerCase() === 'sacks') return 'sack'
    if (u.toLowerCase() === 'bags') return 'bag'
    if (u.toLowerCase() === 'kits') return 'kit'
    if (u.toLowerCase() === 'units') return 'unit'
    if (u.toLowerCase().endsWith('s') && !u.toLowerCase().endsWith('ss')) return u.slice(0, -1)
  }
  return u
}

function formatItemTitle(name = '') {
  if (!name) return 'Relief Resource'
  // Auto-capitalize first letter of each word if lowercase
  return name
    .split(' ')
    .map((w) => (w.length > 0 ? w.charAt(0).toUpperCase() + w.slice(1) : ''))
    .join(' ')
}

function getCategoryDisplay(item) {
  const catKey = String(
    item?.category_key || item?.category || item?.category_label || '',
  ).toLowerCase()
  if (catKey.includes('med') || catKey.includes('health')) return 'Medical kit'
  if (catKey.includes('food') || catKey.includes('water')) return 'Food & water'
  if (catKey.includes('comm')) return 'Communications'
  if (catKey.includes('rescue') || catKey.includes('gear')) return 'Rescue equipment'
  if (catKey.includes('shelter') || catKey.includes('tent')) return 'Emergency shelter'
  return item?.category_label || item?.category || 'Relief Goods'
}

export default function RequestResourceModal({
  item,
  isOpen,
  onClose,
  onSuccess,
}) {
  const [quantity, setQuantity] = useState(1)
  const [evacuationCenterId, setEvacuationCenterId] = useState('')
  const [urgencyId, setUrgencyId] = useState(2) // Default: Medium (urgency_id: 2)
  const [requestedBy, setRequestedBy] = useState('Disaster Response Admin')
  const [description, setDescription] = useState('')
  const [centers, setCenters] = useState([])
  const [urgencies, setUrgencies] = useState([])
  const [loadingOptions, setLoadingOptions] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)
  const [fieldErrors, setFieldErrors] = useState({})

  // Load evacuation centers, urgencies & current user
  useEffect(() => {
    if (!isOpen) return

    let isMounted = true

    async function loadOptions() {
      try {
        setLoadingOptions(true)
        const [workspace, userRes] = await Promise.allSettled([
          getResourceRequests({ per_page: 1 }),
          getCurrentUser(),
        ])

        if (!isMounted) return

        if (workspace.status === 'fulfilled' && workspace.value?.options) {
          const opts = workspace.value.options
          setCenters(opts.evacuation_centers || [])
          if (opts.urgencies?.length > 0) {
            setUrgencies(opts.urgencies)
            const medUrgency = opts.urgencies.find((u) => u.key === 'medium')
            if (medUrgency) {
              setUrgencyId(medUrgency.urgency_id)
            }
          }
          if (opts.evacuation_centers?.length > 0) {
            setEvacuationCenterId(opts.evacuation_centers[0].evacuation_center_id)
          }
        }

        if (userRes.status === 'fulfilled' && userRes.value) {
          const u = userRes.value
          const fullName = u.name || `${u.first_name || ''} ${u.last_name || ''}`.trim()
          if (fullName) {
            setRequestedBy(fullName)
          }
        }
      } catch (e) {
        console.error('Failed to load request options:', e)
      } finally {
        if (isMounted) setLoadingOptions(false)
      }
    }

    // Reset default form values based on item
    setQuantity(1)
    setDescription('')
    setError(null)
    setFieldErrors({})
    loadOptions()

    return () => {
      isMounted = false
    }
  }, [isOpen, item])

  const maxAvailable = Math.max(1, Number(item?.quantity) || 1)
<<<<<<< HEAD
=======
  const isLowStock = maxAvailable <= 20
>>>>>>> 280f60972fc9aee233f9e420d198b23d0170580d

  const selectedCenter = useMemo(() => {
    if (!centers || centers.length === 0) return null
    return (
      centers.find((c) => String(c.evacuation_center_id) === String(evacuationCenterId)) ||
      centers[0]
    )
  }, [centers, evacuationCenterId])

  const quickPresets = useMemo(() => {
    const list = []
    if (maxAvailable >= 5) list.push(5)
    if (maxAvailable >= 10) list.push(10)
    if (maxAvailable >= 25) list.push(25)
    return list.filter((p) => p < maxAvailable)
  }, [maxAvailable])

  if (!isOpen || !item) return null

  const rawQtyNum = parseInt(quantity, 10)
  const isOverStock = !isNaN(rawQtyNum) && rawQtyNum > maxAvailable
  const isUnderMin = !isNaN(rawQtyNum) && rawQtyNum < 1
  const isInvalidQty = quantity === '' || isNaN(rawQtyNum) || isUnderMin || isOverStock
  const excessQty = isOverStock ? rawQtyNum - maxAvailable : 0
  const displayQty = isNaN(rawQtyNum) ? (quantity === '' ? 0 : 1) : rawQtyNum
  const remainingStock = Math.max(0, maxAvailable - displayQty)
  const percentStock = maxAvailable > 0
    ? Math.min(100, Math.round((Math.min(displayQty, maxAvailable) / maxAvailable) * 100))
    : 0

<<<<<<< HEAD
=======
  // Formatting values for visual display
  const titleDisplay = formatItemTitle(item.name || item.label)
  const categoryLabel = getCategoryDisplay(item)
  const rawLocation =
    item.storage_location ||
    item.location ||
    item.depot_location ||
    item.detail ||
    'Barangay Resource Depot'
  const locationText = rawLocation.replace(/^Evacuation:\s*/i, '')
  const itemSku =
    item.sku ||
    item.tracking_reference ||
    (item.id
      ? String(item.id).startsWith('TA-') || String(item.id).startsWith('RR-')
        ? item.id
        : `TA-${item.id}`
      : '')

>>>>>>> 280f60972fc9aee233f9e420d198b23d0170580d
  const handleClose = () => {
    setFieldErrors({})
    setError(null)
    onClose()
  }

  const stepQuantity = (delta) => {
    if (error) setError(null)
    const cur = parseInt(quantity, 10) || 1
    const next = Math.max(1, cur + delta)
    setQuantity(next)

    if (next > maxAvailable) {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: `Exceeds available stock! Only ${maxAvailable} ${formatUnit(maxAvailable, item.unit)} remaining.`,
      }))
    } else {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: null,
      }))
    }
  }

  const handleQuantityChange = (val) => {
    if (error) setError(null)
    setQuantity(val)

    if (val === '') {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: 'Quantity is required (at least 1).',
      }))
      return
    }

    const num = parseInt(val, 10)
    if (isNaN(num)) return

    if (num < 1) {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: 'Quantity must be at least 1.',
      }))
    } else if (num > maxAvailable) {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: `Exceeds available stock! Only ${maxAvailable} ${formatUnit(maxAvailable, item.unit)} remaining.`,
      }))
    } else {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: null,
      }))
    }
  }

  const handleQuantityBlur = () => {
    if (quantity === '' || isNaN(parseInt(quantity, 10)) || parseInt(quantity, 10) < 1) {
      setQuantity(1)
      setFieldErrors((prev) => ({ ...prev, quantity: null }))
    } else if (parseInt(quantity, 10) > maxAvailable) {
      setFieldErrors((prev) => ({
        ...prev,
        quantity: `Exceeds available stock! Only ${maxAvailable} ${formatUnit(maxAvailable, item.unit)} remaining.`,
      }))
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    const errors = {}
    const reqQty = parseInt(quantity, 10)
    if (quantity === '' || isNaN(reqQty) || reqQty < 1) {
      errors.quantity = 'Please specify a valid quantity of at least 1.'
    } else if (reqQty > maxAvailable) {
      errors.quantity = `Requested quantity (${reqQty}) exceeds available stock! Only ${maxAvailable} ${formatUnit(maxAvailable, item.unit)} remaining.`
    }

    if (!requestedBy.trim()) {
      errors.requestedBy = 'Please provide the requester name.'
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      setError(errors.quantity || 'Please fill out the required fields.')
      return
    }

    setFieldErrors({})

    try {
      setSubmitting(true)
      setError(null)

      const rawItemId = item.id
      const validItemId =
        Number.isInteger(Number(rawItemId)) && Number(rawItemId) > 0
          ? Number(rawItemId)
          : null

      const payload = {
        request_source: 'hq_desk',
        request_category: 'resource',
        resource_type: categoryLabel,
        item_name: titleDisplay,
        quantity: reqQty,
        unit: item.unit || 'units',
        requested_by: requestedBy.trim(),
        evacuation_center_id: evacuationCenterId || null,
        urgency_id: urgencyId ? Number(urgencyId) : 2,
        description: description.trim() || `Disaster resource request for ${titleDisplay} (${itemSku})`,
        sku: itemSku || null,
        item_id: validItemId,
      }

      const res = await createResourceRequest(payload)
      onSuccess?.(res, item, reqQty)
      handleClose()
    } catch (err) {
      console.error(err)
      setError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to submit resource request. Please check your inputs.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="presentation" onClick={handleClose}>
      <section
        className="modal-panel request-resource-modal"
        role="dialog"
        aria-modal="true"
        aria-label={`Request ${titleDisplay}`}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Modal Head */}
        <div className="modal-head">
          <div className="modal-title-with-badge">
            <div className="modal-title-left">
              <div className="modal-icon-badge">
                <Box size={17} strokeWidth={2.2} />
              </div>
              <h2>Request Resource</h2>
            </div>
            <span className={`badge-stock-info ${isLowStock ? 'stock-low' : 'stock-in'}`}>
              <span className="stock-dot" />
              {maxAvailable.toLocaleString()} {formatUnit(maxAvailable, item.unit)} Available
            </span>
          </div>
          <button
            className="modal-close"
            type="button"
            aria-label="Close modal"
            onClick={handleClose}
          >
            <X size={17} />
          </button>
        </div>

        {/* Modal Body */}
        <form onSubmit={handleSubmit} className="modal-form" noValidate>
          <div className="modal-body">
            {error && (
              <div className="form-error-banner" role="alert">
                <AlertCircle size={15} />
                <span>{error}</span>
              </div>
            )}

            {/* Selected Item Summary Card (Unified with Catalog Card aesthetic) */}
            <div className="req-item-preview-box">
              <div className="preview-top">
                <span className="preview-cat-badge">Resource</span>
                {itemSku && (
                  <code className="preview-sku">
                    <Tag size={11} /> {itemSku}
                  </code>
                )}
              </div>

              <h3 className="preview-title">{titleDisplay}</h3>

              <div className="preview-meta">
                <span className="preview-meta-item">
                  <MapPin size={12} className="meta-pin-icon" /> {locationText}
                </span>
                <span className="preview-meta-item">
                  <Box size={12} /> {categoryLabel}
                </span>
              </div>

              {/* Allocation Progress Bar */}
              <div className="req-stock-bar-wrap">
                <div className="req-stock-bar-labels">
                  <span>
                    Requesting <strong>{displayQty}</strong> of {maxAvailable} {formatUnit(maxAvailable, item.unit)}
                  </span>
                  {isOverStock ? (
<<<<<<< HEAD
                    <span
                      className="req-remaining-badge is-deficit"
                      style={{
                        backgroundColor: '#fef2f2',
                        color: '#dc2626',
                        borderColor: '#fca5a5',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '3px',
                        fontWeight: 600,
                      }}
                    >
                      <AlertCircle size={11} /> Over by {excessQty} {formatUnit(excessQty, item.unit)}
                    </span>
=======
                    <span className="req-remaining-badge is-deficit">
                      <AlertCircle size={11} /> Over by {excessQty} {formatUnit(excessQty, item.unit)}
                    </span>
                  ) : remainingStock === 0 ? (
                    <span className="req-remaining-badge is-full">
                      All available stock requested
                    </span>
>>>>>>> 280f60972fc9aee233f9e420d198b23d0170580d
                  ) : (
                    <span className="req-remaining-badge">
                      {remainingStock} {formatUnit(remainingStock, item.unit)} remaining
                    </span>
                  )}
                </div>
                <div className="req-stock-progress-track">
                  <div
                    className="req-stock-progress-fill"
                    style={{
                      width: isOverStock ? '100%' : `${percentStock}%`,
<<<<<<< HEAD
                      backgroundColor: isOverStock ? '#dc2626' : percentStock > 80 ? '#f59e0b' : '#3b82f6',
=======
                      backgroundColor: isOverStock
                        ? '#dc2626'
                        : percentStock > 80
                          ? '#f59e0b'
                          : '#2563eb',
>>>>>>> 280f60972fc9aee233f9e420d198b23d0170580d
                      transition: 'width 200ms ease, background-color 200ms ease',
                    }}
                  />
                </div>
              </div>
            </div>

            {/* Form Fields */}
            <div className="form-grid-2">
              {/* Interactive Quantity Stepper */}
              <div className="form-field full-width">
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px', flexWrap: 'wrap' }}>
                  <label htmlFor="field-request-quantity">
                    Quantity Needed <span className="req-star">*</span>
                  </label>
                  {/* Quick Presets */}
                  <div className="req-preset-row" style={{ marginTop: 0 }}>
                    {quickPresets.map((preset) => (
                      <button
                        key={preset}
                        type="button"
                        className={`preset-chip ${Number(quantity) === preset ? 'is-active' : ''}`}
                        onClick={() => {
                          setQuantity(preset)
                          if (fieldErrors.quantity) setFieldErrors((prev) => ({ ...prev, quantity: null }))
                          if (error) setError(null)
                        }}
                      >
                        +{preset}
                      </button>
                    ))}
                    <button
                      type="button"
                      className={`preset-chip preset-max ${Number(quantity) === maxAvailable ? 'is-active' : ''}`}
                      onClick={() => {
                        setQuantity(maxAvailable)
                        if (fieldErrors.quantity) setFieldErrors((prev) => ({ ...prev, quantity: null }))
                        if (error) setError(null)
                      }}
                    >
                      Max ({maxAvailable})
                    </button>
                  </div>
                </div>

                <div className={`req-stepper-control ${fieldErrors.quantity ? 'is-invalid' : ''}`}>
                  <button
                    type="button"
                    className="stepper-step-btn"
                    onClick={() => stepQuantity(-1)}
                    disabled={Number(quantity) <= 1}
                    aria-label="Decrease quantity"
                  >
                    <Minus size={14} />
                  </button>
                  <div className="stepper-center-input">
                    <input
                      id="field-request-quantity"
                      type="number"
                      min="1"
                      value={quantity}
                      onChange={(e) => handleQuantityChange(e.target.value)}
                      onBlur={handleQuantityBlur}
                      className="stepper-num-field"
                    />
                    <span className="stepper-unit-tag">{formatUnit(quantity || 1, item.unit)}</span>
                  </div>
                  <button
                    type="button"
                    className="stepper-step-btn"
                    onClick={() => stepQuantity(1)}
                    disabled={Number(quantity) >= maxAvailable}
                    aria-label="Increase quantity"
                  >
                    <Plus size={14} />
                  </button>
                </div>

                {fieldErrors.quantity && (
                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      marginTop: '4px',
                      flexWrap: 'wrap',
                      gap: '4px',
                    }}
                  >
                    <span className="field-error-text" style={{ marginTop: 0 }}>
                      <AlertCircle size={12} /> {fieldErrors.quantity}
                    </span>
                    {isOverStock && (
                      <button
                        type="button"
                        onClick={() => {
                          setQuantity(maxAvailable)
                          setFieldErrors((prev) => ({ ...prev, quantity: null }))
                          if (error) setError(null)
                        }}
                        style={{
                          background: 'none',
                          border: 'none',
                          color: '#2563eb',
                          fontSize: '11.5px',
                          fontWeight: 600,
                          cursor: 'pointer',
                          padding: 0,
                          textDecoration: 'underline',
                        }}
                      >
                        Set to max ({maxAvailable})
                      </button>
                    )}
                  </div>
                )}
              </div>

              {/* Destination Evacuation Center */}
              <div className="form-field full-width">
                <label htmlFor="field-destination">
                  Destination Evacuation Center <span className="req-star">*</span>
                </label>
                <div className="select-with-icon">
                  <Building size={14} className="field-icon" />
                  <select
                    id="field-destination"
                    value={evacuationCenterId}
                    onChange={(e) => setEvacuationCenterId(e.target.value)}
                    className="form-control with-icon"
                    disabled={loadingOptions}
                  >
                    {centers.length > 0 ? (
                      centers.map((c) => (
                        <option key={c.evacuation_center_id} value={c.evacuation_center_id}>
                          {c.name}
                        </option>
                      ))
                    ) : (
                      <option value="">General Municipal Relief Hub</option>
                    )}
                  </select>
                </div>

                {/* Selected Center Address Hint */}
                {selectedCenter && selectedCenter.address && (
                  <div className="center-caption-info">
                    <MapPin size={11} className="center-caption-pin" />
                    <span className="center-caption-address">{selectedCenter.address}</span>
                    {selectedCenter.current_event_status === 'active' && (
                      <span className="center-caption-active-tag">Active event</span>
                    )}
                  </div>
                )}
              </div>

              {/* Requester Name */}
              <div className="form-field full-width">
                <label htmlFor="field-requested-by">
                  Requested By / Authorized Person <span className="req-star">*</span>
                </label>
                <div className="input-with-icon">
                  <User size={14} className="field-icon" />
                  <input
                    id="field-requested-by"
                    type="text"
                    value={requestedBy}
                    onChange={(e) => {
                      setRequestedBy(e.target.value)
                      if (fieldErrors.requestedBy) {
                        setFieldErrors((prev) => ({ ...prev, requestedBy: null }))
                      }
                      if (error) setError(null)
                    }}
                    placeholder="e.g. Nina Kathleen (Disaster Operations)"
                    className={`form-control with-icon ${fieldErrors.requestedBy ? 'is-invalid' : ''}`}
                  />
                </div>
                {fieldErrors.requestedBy && (
                  <span className="field-error-text">
                    <AlertCircle size={12} /> {fieldErrors.requestedBy}
                  </span>
                )}
              </div>

              {/* Purpose / Notes */}
              <div className="form-field full-width">
                <div className="field-label-row">
                  <label htmlFor="field-description">
                    Disaster Context / Purpose <span className="field-optional">(Optional)</span>
                  </label>
                  <span className="field-char-count">{description.length}/200</span>
                </div>
                <textarea
                  id="field-description"
                  rows={2}
                  maxLength={200}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="e.g. Immediate food assistance for 15 displaced families at evacuation site."
                  className="form-control req-textarea"
                />
              </div>
            </div>
          </div>

          {/* Modal Footer */}
          <div className="modal-footer">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={handleClose}
              disabled={submitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn btn-primary btn-submit-request"
              disabled={submitting || isInvalidQty}
            >
              {submitting ? (
                <>
                  <Loader2 size={15} className="spin-icon" /> Submitting Request...
                </>
              ) : isOverStock ? (
                <>
                  <AlertCircle size={15} /> Exceeds Stock Limit ({displayQty}/{maxAvailable})
                </>
              ) : (
                <>
                  <Send size={15} /> Submit Request ({displayQty || 0} {formatUnit(displayQty || 0, item.unit)})
                </>
              )}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}
