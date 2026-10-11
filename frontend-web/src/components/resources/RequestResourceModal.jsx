import { useEffect, useState } from 'react'
import {
  AlertCircle,
  ArrowRight,
  Boxes,
  Building,
  CheckCircle2,
  Clock,
  Loader2,
  MapPin,
  Package,
  Send,
  Tag,
  X,
} from 'lucide-react'
import { createResourceRequest, getResourceRequests } from '../../api/resourceRequestApi'
import { getCurrentUser } from '../../api/authApi'

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
          setUrgencies(opts.urgencies || [])
          if (opts.evacuation_centers?.length > 0) {
            setEvacuationCenterId(opts.evacuation_centers[0].evacuation_center_id)
          }
          const medUrgency = opts.urgencies?.find((u) => u.key === 'medium')
          if (medUrgency) {
            setUrgencyId(medUrgency.urgency_id)
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
    loadOptions()

    return () => {
      isMounted = false
    }
  }, [isOpen, item])

  if (!isOpen || !item) return null

  const maxAvailable = Math.max(1, Number(item.quantity) || 1)

  const handleSubmit = async (e) => {
    e.preventDefault()

    const reqQty = parseInt(quantity, 10)
    if (isNaN(reqQty) || reqQty < 1) {
      setError('Please specify a valid quantity of at least 1.')
      return
    }

    if (reqQty > maxAvailable) {
      setError(`Requested quantity cannot exceed available stock (${maxAvailable} ${item.unit || 'units'}).`)
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
        urgency_id: urgencyId ? Number(urgencyId) : 2,
        description: description.trim() || `Disaster resource request for ${item.name || item.label} (${item.sku || ''})`,
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
            <h2>Request Resource</h2>
            <span className="badge-stock-info">
              {Number(item.quantity).toLocaleString()} {item.unit || 'units'} Available
            </span>
          </div>
          <button
            className="modal-close"
            type="button"
            aria-label="Close modal"
            onClick={onClose}
          >
            <X size={18} />
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
            </div>

            {/* Form Fields */}
            <div className="form-grid-2">
              {/* Quantity Field */}
              <div className="form-field">
                <label htmlFor="field-request-quantity">
                  Quantity Needed <span className="req-star">*</span>
                </label>
                <div className="input-with-unit">
                  <input
                    id="field-request-quantity"
                    type="number"
                    min="1"
                    max={maxAvailable}
                    value={quantity}
                    onChange={(e) => setQuantity(e.target.value)}
                    required
                    className="form-control"
                  />
                  <span className="input-unit-label">{item.unit || 'units'}</span>
                </div>
                <span className="form-help-text">
                  Max available: {maxAvailable} {item.unit || 'units'}
                </span>
              </div>

              {/* Urgency Level */}
              <div className="form-field">
                <label htmlFor="field-urgency">
                  Urgency Level <span className="req-star">*</span>
                </label>
                <select
                  id="field-urgency"
                  value={urgencyId}
                  onChange={(e) => setUrgencyId(e.target.value)}
                  className="form-control"
                >
                  {urgencies.length > 0 ? (
                    urgencies.map((u) => (
                      <option key={u.urgency_id} value={u.urgency_id}>
                        {u.label} Priority
                      </option>
                    ))
                  ) : (
                    <>
                      <option value="1">Low Priority</option>
                      <option value="2">Medium Priority</option>
                      <option value="3">High Priority</option>
                      <option value="4">Critical Priority</option>
                    </>
                  )}
                </select>
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
                          {c.name} {c.address ? `(${c.address})` : ''}
                        </option>
                      ))
                    ) : (
                      <option value="">General Municipal Relief Hub</option>
                    )}
                  </select>
                </div>
              </div>

              {/* Requester Name */}
              <div className="form-field full-width">
                <label htmlFor="field-requested-by">
                  Requested By / Authorized Person <span className="req-star">*</span>
                </label>
                <input
                  id="field-requested-by"
                  type="text"
                  value={requestedBy}
                  onChange={(e) => setRequestedBy(e.target.value)}
                  placeholder="e.g. Nina Kathleen (Disaster Operations)"
                  required
                  className="form-control"
                />
              </div>

              {/* Purpose / Notes */}
              <div className="form-field full-width">
                <label htmlFor="field-description">
                  Disaster Context / Purpose (Optional)
                </label>
                <textarea
                  id="field-description"
                  rows={2}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="e.g. Immediate food assistance for 15 displaced families at evacuation site."
                  className="form-control"
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
              disabled={submitting}
            >
              {submitting ? (
                <>
                  <Loader2 size={15} className="spin-icon" /> Submitting Request...
                </>
              ) : (
                <>
                  <Send size={15} /> Submit Request ({quantity} {item.unit || 'units'})
                </>
              )}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}
