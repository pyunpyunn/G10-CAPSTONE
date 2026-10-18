import { useState } from 'react'
import {
  Database,
  Loader2,
  Plus,
  Sparkles,
  X,
} from 'lucide-react'
import { createResourceRequestType } from '../../api/resourceRequestApi'

export default function DeclareRequestTypeModal({
  isOpen,
  onClose,
  onSuccess,
}) {
  const [formData, setFormData] = useState({
    name: '',
    category: 'FOOD',
    unit_type: 'packs',
    type: 'consumable',
    quantity: 50,
    storage_location: 'Warehouse A',
    expiration_date: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)

  if (!isOpen) return null

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!formData.name.trim()) {
      setError('Please provide a resource name.')
      return
    }

    try {
      setSubmitting(true)
      setError(null)
      await createResourceRequestType({
        name: formData.name.trim(),
        category: formData.category,
        unit_type: formData.unit_type.trim() || 'units',
        type: formData.type,
        quantity: Math.max(0, parseInt(formData.quantity, 10) || 0),
        storage_location: formData.storage_location.trim() || 'Warehouse Depot',
        expiration_date: formData.expiration_date || null,
      })

      // Reset form
      setFormData({
        name: '',
        category: 'FOOD',
        unit_type: 'packs',
        type: 'consumable',
        quantity: 50,
        storage_location: 'Warehouse A',
        expiration_date: '',
      })

      onSuccess?.()
      onClose()
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Failed to register request type.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="presentation" onClick={onClose}>
      <section
        className="modal-panel declare-type-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Declare New Request Type"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <div className="modal-title-with-badge">
            <h2>
              <Plus size={18} className="inline-icon" /> Declare New Request Type
            </h2>
            <span className="badge-sync-notice">
              <Database size={12} /> Syncs to TrackingAid
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

        <form onSubmit={handleSubmit} className="modal-form">
          <div className="modal-body">
            {error && (
              <div className="form-error-banner" role="alert">
                {error}
              </div>
            )}

            <div className="form-info-note">
              <Sparkles size={16} className="sparkle-icon" />
              <span>
                Newly registered request types will be prioritized and displayed as the <strong>1st Card</strong> in the Available Request catalog.
              </span>
            </div>

            <div className="form-grid-2">
              <div className="form-field full-width">
                <label htmlFor="field-name">
                  Resource Name <span className="req-star">*</span>
                </label>
                <input
                  id="field-name"
                  name="name"
                  type="text"
                  required
                  placeholder="e.g. Hygiene Kits (Family), Heavy Duty Tarps"
                  value={formData.name}
                  onChange={handleChange}
                  className="form-control"
                />
              </div>

              <div className="form-field">
                <label htmlFor="field-category">
                  Resource Category <span className="req-star">*</span>
                </label>
                <select
                  id="field-category"
                  name="category"
                  value={formData.category}
                  onChange={handleChange}
                  className="form-control"
                >
                  <option value="FOOD">Food & Water</option>
                  <option value="MEDICAL">Medical Supplies</option>
                  <option value="RESCUE">Rescue Equipment</option>
                  <option value="SHELTER">Emergency Shelter</option>
                  <option value="RELIEF">General Relief</option>
                  <option value="OTHER">Other Disaster Supply</option>
                </select>
              </div>

              <div className="form-field">
                <label htmlFor="field-unit">Unit of Measure</label>
                <input
                  id="field-unit"
                  name="unit_type"
                  type="text"
                  placeholder="e.g. boxes, sacks, kits, pieces"
                  value={formData.unit_type}
                  onChange={handleChange}
                  className="form-control"
                />
              </div>

              <div className="form-field">
                <label htmlFor="field-type">Resource Classification</label>
                <select
                  id="field-type"
                  name="type"
                  value={formData.type}
                  onChange={handleChange}
                  className="form-control"
                >
                  <option value="consumable">Consumable Supply</option>
                  <option value="returnable">Returnable / Asset</option>
                </select>
              </div>

              <div className="form-field">
                <label htmlFor="field-quantity">Initial Stock Quantity</label>
                <input
                  id="field-quantity"
                  name="quantity"
                  type="number"
                  min="0"
                  placeholder="0"
                  value={formData.quantity}
                  onChange={handleChange}
                  className="form-control"
                />
              </div>

              <div className="form-field">
                <label htmlFor="field-location">Warehouse Storage Depot</label>
                <input
                  id="field-location"
                  name="storage_location"
                  type="text"
                  placeholder="e.g. Warehouse A, Bay 4"
                  value={formData.storage_location}
                  onChange={handleChange}
                  className="form-control"
                />
              </div>

              <div className="form-field">
                <label htmlFor="field-expiration">Expiration Date (Optional)</label>
                <input
                  id="field-expiration"
                  name="expiration_date"
                  type="date"
                  value={formData.expiration_date}
                  onChange={handleChange}
                  className="form-control"
                />
              </div>
            </div>
          </div>

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
              className="btn btn-primary btn-submit-declare"
              disabled={submitting}
            >
              {submitting ? (
                <>
                  <Loader2 size={16} className="spin-icon" /> Registering...
                </>
              ) : (
                <>
                  <Plus size={16} /> Register Request Type
                </>
              )}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}
