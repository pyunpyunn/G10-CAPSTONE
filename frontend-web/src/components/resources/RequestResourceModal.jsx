import { useEffect, useMemo, useState } from 'react'
import {
  AlertCircle,
  Boxes,
  Building,
  Loader2,
  MapPin,
  Minus,
  Package,
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

const fallbackUrgencies = [
  { urgency_id: 1, key: 'low', label: 'Low' },
  { urgency_id: 2, key: 'medium', label: 'Medium' },
  { urgency_id: 3, key: 'high', label: 'High' },
  { urgency_id: 4, key: 'critical', label: 'Critical' },
]

export default function RequestResourceModal({
  item,
  isOpen,
  onClose,
  onSuccess,
}) {
  const [quantity, setQuantity] = useState(1)
  const [evacuationCenterId, setEvacuationCenterId] = useState('')
  const [urgencyId, setUrgencyId] = useState(2)
  const [requestedBy, setRequestedBy] = useState('Disaster Response Admin')
  const [description, setDescription] = useState('')
  const [centers, setCenters] = useState([])
  const [urgencies, setUrgencies] = useState([])
  const [loadingOptions, setLoadingOptions] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)

  // Load evacuation centers & current user
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
          const urgencyOptions = opts.urgencies || []
          setUrgencies(urgencyOptions)
          const mediumUrgency = urgencyOptions.find((urgency) => urgency.key === 'medium')
          if (mediumUrgency) setUrgencyId(Number(mediumUrgency.urgency_id))
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

    loadOptions()

    return () => {
      isMounted = false
    }
  }, [isOpen, item])

  const maxAvailable = Math.max(1, Number(item?.quantity) || 1)
  const urgencyOptions = useMemo(() => urgencies.length > 0 ? urgencies : fallbackUrgencies, [urgencies])

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

  const currentQtyNum = Math.min(maxAvailable, Math.max(1, parseInt(quantity, 10) || 1))
  const percentStock = Math.min(100, Math.round((currentQtyNum / maxAvailable) * 100))
  const remainingStock = Math.max(0, maxAvailable - currentQtyNum)

  const stepQuantity = (delta) => {
    const cur = parseInt(quantity, 10) || 1
    const next = Math.min(maxAvailable, Math.max(1, cur + delta))
    setQuantity(next)
  }

  const handleQuantityChange = (val) => {
    if (val === '') {
      setQuantity('')
      return
    }
    const num = parseInt(val, 10)
    if (isNaN(num)) return
    if (num < 1) {
      setQuantity(1)
    } else if (num > maxAvailable) {
      setQuantity(maxAvailable)
    } else {
      setQuantity(num)
    }
  }

  const handleQuantityBlur = () => {
    if (quantity === '' || isNaN(parseInt(quantity, 10)) || parseInt(quantity, 10) < 1) {
      setQuantity(1)
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    const reqQty = parseInt(quantity, 10)
    if (isNaN(reqQty) || reqQty < 1) {
      setError('Please specify a valid quantity of at least 1.')
      return
    }

    if (reqQty > maxAvailable) {
      setError(`Requested quantity cannot exceed available stock (${maxAvailable} ${formatUnit(maxAvailable, item.unit)}).`)
      return
    }

    if (!requestedBy.trim()) {
      setError('Please provide the requester name.')
      return
    }

    try {
      setSubmitting(true)
      setError(null)

      const payload = {
        request_source: 'hq_desk',
        request_category: 'resource',
        resource_type: item.category_label || item.category || 'Food & Water',
        item_name: item.name || item.label,
        quantity: reqQty,
        unit: item.unit || 'units',
        requested_by: requestedBy.trim(),
        evacuation_center_id: evacuationCenterId || null,
        urgency_id: Number(urgencyId) || 2,
        description: description.trim() || `Disaster resource request for ${item.name || item.label} (${item.sku || ''})`,
        sku: item.sku || null,
        item_id: item.id || null,
      }

      const res = await createResourceRequest(payload)
      onSuccess?.(res, item, reqQty)
      onClose()
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
    <div className="modal-backdrop" role="presentation" onClick={onClose}>
      <section
        className="modal-panel request-resource-modal"
        role="dialog"
        aria-modal="true"
        aria-label={`Request ${item.name}`}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Modal Head */}
        <div className="modal-head">
          <div className="modal-title-with-badge">
            <div className="modal-title-left">
              <div className="modal-icon-badge">
                <Package size={17} />
              </div>
              <h2>Request Resource</h2>
            </div>
            <span className="badge-stock-info">
              <span className="stock-dot" />
              {Number(item.quantity).toLocaleString()} {formatUnit(item.quantity, item.unit)} Available
            </span>
          </div>
          <button
            className="modal-close"
            type="button"
            aria-label="Close modal"
            onClick={onClose}
          >
            <X size={17} />
          </button>
        </div>

        {/* Modal Body */}
        <form onSubmit={handleSubmit} className="modal-form">
          <div className="modal-body">
            {error && (
              <div className="form-error-banner" role="alert">
                <AlertCircle size={15} />
                <span>{error}</span>
              </div>
            )}

            {/* Selected Item Summary Card */}
            <div className="req-item-preview-box">
              <div className="preview-top">
                <span className="preview-cat-badge">{item.category_label || item.category}</span>
                {item.sku && (
                  <code className="preview-sku">
                    <Tag size={11} /> {item.sku}
                  </code>
                )}
              </div>
              <h3 className="preview-title">{item.name || item.label}</h3>
              <div className="preview-meta">
                <span className="preview-meta-item">
                  <MapPin size={12} /> {item.storage_location || 'Warehouse Depot'}
                </span>
                <span className="preview-meta-item">
                  <Boxes size={12} /> {item.type || 'Consumable'}
                </span>
              </div>

              {/* Allocation Progress Bar */}
              <div className="req-stock-bar-wrap">
                <div className="req-stock-bar-labels">
                  <span>
                    Requesting <strong>{currentQtyNum}</strong> of {maxAvailable} {formatUnit(maxAvailable, item.unit)}
                  </span>
                  <span className="req-remaining-badge">
                    {remainingStock} {formatUnit(remainingStock, item.unit)} remaining
                  </span>
                </div>
                <div className="req-stock-progress-track">
                  <div
                    className="req-stock-progress-fill"
                    style={{
                      width: `${percentStock}%`,
                      backgroundColor: percentStock > 80 ? '#f59e0b' : '#3b82f6',
                    }}
                  />
                </div>
              </div>
            </div>

            {/* Form Fields */}
            <div className="form-grid-2">
              {/* Interactive Quantity Stepper */}
              <div className="form-field">
                <label htmlFor="field-request-quantity">
                  Quantity Needed <span className="req-star">*</span>
                </label>
                <div className="req-stepper-control">
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
                      max={maxAvailable}
                      value={quantity}
                      onChange={(e) => handleQuantityChange(e.target.value)}
                      onBlur={handleQuantityBlur}
                      required
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

                {/* Quick Presets */}
                <div className="req-preset-row">
                  {quickPresets.map((preset) => (
                    <button
                      key={preset}
                      type="button"
                      className={`preset-chip ${Number(quantity) === preset ? 'is-active' : ''}`}
                      onClick={() => setQuantity(preset)}
                    >
                      +{preset}
                    </button>
                  ))}
                  <button
                    type="button"
                    className={`preset-chip preset-max ${Number(quantity) === maxAvailable ? 'is-active' : ''}`}
                    onClick={() => setQuantity(maxAvailable)}
                  >
                    Max ({maxAvailable})
                  </button>
                </div>
              </div>

              <div className="form-field full-width">
                <label>Priority <span className="req-star">*</span></label>
                <div className="urgency-selector-grid" role="radiogroup" aria-label="Request priority">
                  {urgencyOptions.map((urgency) => {
                    const urgencyKey = urgency.key || 'medium'
                    const selected = Number(urgencyId) === Number(urgency.urgency_id)
                    return (
                      <button
                        key={urgency.urgency_id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        className={`urgency-choice-btn urgency-${urgencyKey} ${selected ? 'selected' : ''}`}
                        onClick={() => setUrgencyId(Number(urgency.urgency_id))}
                      >
                        <span className={`urgency-status-dot dot-${urgencyKey}`} />
                        <span className="urgency-choice-label">{urgency.label}</span>
                      </button>
                    )
                  })}
                </div>
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
                    onChange={(e) => setRequestedBy(e.target.value)}
                    placeholder="e.g. Nina Kathleen (Disaster Operations)"
                    required
                    className="form-control with-icon"
                  />
                </div>
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
              onClick={onClose}
              disabled={submitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn btn-primary btn-submit-request"
              disabled={submitting || !quantity || Number(quantity) < 1}
            >
              {submitting ? (
                <>
                  <Loader2 size={15} className="spin-icon" /> Submitting Request...
                </>
              ) : (
                <>
                  <Send size={15} /> Submit Request ({quantity || 0} {formatUnit(quantity || 0, item.unit)})
                </>
              )}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}
