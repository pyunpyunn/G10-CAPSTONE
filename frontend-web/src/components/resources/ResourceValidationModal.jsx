import { CheckCircle2, FileCheck2, Send, Undo2, X } from 'lucide-react'
import { createPortal } from 'react-dom'

export default function ResourceValidationModal({
  mode,
  isOpen,
  form,
  setForm,
  options = {},
  formError,
  isSaving,
  selectedRequestId,
  onClose,
  onSubmit,
  onForward,
  onReturn,
}) {
  if (!isOpen) {
    return null
  }

  const isView = mode === 'view'
  const isCreate = mode === 'create'
  const requestFieldsDisabled = !isCreate
  const decisionDisabled = isView || isCreate
  const modalTitle = isCreate ? 'New request record' : 'Validation record'
  const modalSub = isCreate
    ? 'Manual HQ intake before validation'
    : `${selectedRequestId || 'Request'} - HQ sign-off before TrackingAid handoff`

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  return createPortal(
    <div className="rr-validation-modal-overlay open" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget) {
        onClose()
      }
    }}>
      <section className="rr-validation-modal" role="dialog" aria-modal="true" aria-labelledby="resourceValidationTitle">
        <div className="rr-validation-modal-head">
          <div>
            <div className="rr-validation-modal-title" id="resourceValidationTitle">
              <FileCheck2 size={18} />
              {modalTitle}
            </div>
            <div className="rr-validation-modal-sub">{modalSub}</div>
          </div>
          <button className="rr-validation-modal-close" type="button" onClick={onClose} aria-label="Close validation modal" title="Close">
            <X size={16} />
          </button>
        </div>

        <form id="resourceRequestForm" onSubmit={onSubmit}>
          <div className="rr-validation-modal-body">
            <div className="rr-form-grid">
              <div>
                <label className="rr-mini-label">Source</label>
                <select value={form.request_source} disabled={requestFieldsDisabled} onChange={(event) => setField('request_source', event.target.value)}>
                  {(options.sources || []).map((source) => (
                    <option value={source.key} key={source.key}>{source.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="rr-mini-label">Request type</label>
                <select value={form.request_category} disabled={requestFieldsDisabled} onChange={(event) => setField('request_category', event.target.value)}>
                  {(options.categories || []).map((category) => (
                    <option value={category.key} key={category.key}>{category.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="rr-mini-label">Need / category</label>
                <input
                  type="text"
                  value={form.resource_type}
                  readOnly={requestFieldsDisabled}
                  placeholder="Food packs, medical responders, rescue vehicle..."
                  onChange={(event) => setField('resource_type', event.target.value)}
                />
              </div>
              <div>
                <label className="rr-mini-label">Quantity</label>
                <div className="rr-quantity-row">
                  <input
                    type="number"
                    min="1"
                    value={form.quantity}
                    readOnly={requestFieldsDisabled}
                    onChange={(event) => setField('quantity', event.target.value)}
                  />
                  <input
                    type="text"
                    value={form.unit}
                    readOnly={requestFieldsDisabled}
                    placeholder="packs, kits, persons"
                    onChange={(event) => setField('unit', event.target.value)}
                  />
                </div>
              </div>
              <div className="full">
                <label className="rr-mini-label">Requester / contact</label>
                <input
                  type="text"
                  value={form.requested_by}
                  readOnly={requestFieldsDisabled}
                  placeholder="Name, role, mobile, organization"
                  onChange={(event) => setField('requested_by', event.target.value)}
                />
              </div>
              <div>
                <label className="rr-mini-label">Source reference</label>
                <input
                  type="text"
                  value={form.source_reference}
                  readOnly={requestFieldsDisabled}
                  placeholder="Event ID, EvaTrack ref, or field ref"
                  onChange={(event) => setField('source_reference', event.target.value)}
                />
              </div>
              <div>
                <label className="rr-mini-label">Urgency</label>
                <select value={form.urgency_id} disabled={requestFieldsDisabled} onChange={(event) => setField('urgency_id', event.target.value)}>
                  <option value="">Select urgency</option>
                  {(options.urgencies || []).map((urgency) => (
                    <option value={urgency.urgency_id} key={urgency.urgency_id}>{urgency.label}</option>
                  ))}
                </select>
              </div>
              <div className="full">
                <label className="rr-mini-label">Area / beneficiaries</label>
                <select value={form.evacuation_center_id} disabled={requestFieldsDisabled} onChange={(event) => setField('evacuation_center_id', event.target.value)}>
                  <option value="">No linked evacuation center</option>
                  {(options.evacuation_centers || []).map((center) => (
                    <option value={center.evacuation_center_id} key={center.evacuation_center_id}>
                      {center.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="full">
                <label className="rr-mini-label">Justification / area note</label>
                <textarea
                  value={form.description}
                  readOnly={requestFieldsDisabled}
                  placeholder="Incident link, urgency, vulnerability, beneficiary count, or evacuation site note..."
                  onChange={(event) => setField('description', event.target.value)}
                />
              </div>
              <div>
                <label className="rr-mini-label">Validation decision</label>
                <select value={form.validation_status} disabled={decisionDisabled} onChange={(event) => setField('validation_status', event.target.value)}>
                  {(options.statuses || []).map((status) => (
                    <option value={status.key} key={status.key}>{status.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="rr-mini-label">TrackingAid reference</label>
                <input
                  type="text"
                  value={form.tracking_reference}
                  readOnly={isView || isCreate}
                  placeholder="Auto-generated if blank"
                  onChange={(event) => setField('tracking_reference', event.target.value)}
                />
              </div>
              <div className="full">
                <label className="rr-mini-label">Validation notes</label>
                <textarea
                  value={form.validation_notes}
                  readOnly={isView}
                  placeholder="Validation result, beneficiary check, duplicate check, missing details..."
                  onChange={(event) => setField('validation_notes', event.target.value)}
                />
              </div>
              <div className="full">
                <label className="rr-mini-label">Missing information / duplicate reference</label>
                <input
                  type="text"
                  value={form.missing_information}
                  readOnly={isView || isCreate}
                  placeholder="Use when returning the request"
                  onChange={(event) => setField('missing_information', event.target.value)}
                />
              </div>
            </div>

            <div className="rr-checklist">
              <div className="rr-check"><CheckCircle2 size={14} />Requester identity confirmed</div>
              <div className="rr-check"><CheckCircle2 size={14} />Incident and location matched</div>
              <div className="rr-check"><CheckCircle2 size={14} />Quantity is reasonable</div>
              <div className="rr-check"><CheckCircle2 size={14} />No duplicate active request</div>
              <div className="rr-check"><CheckCircle2 size={14} />TrackingAid handoff logged after forwarding</div>
            </div>

            {formError && <div className="form-error rr-modal-error">{formError}</div>}
          </div>

          <div className="rr-validation-modal-actions">
            {!isCreate && !isView && (
              <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onReturn}>
                <Undo2 size={14} />
                Return
              </button>
            )}
            <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>
              <X size={14} />
              {isView ? 'Close' : 'Cancel'}
            </button>
            {!isView && (
              <button className="btn btn-primary btn-sm" type="submit" disabled={isSaving}>
                <FileCheck2 size={14} />
                {isSaving ? 'Saving...' : isCreate ? 'Save request' : 'Save validation'}
              </button>
            )}
            {!isCreate && !isView && (
              <button className="btn btn-primary btn-sm" type="button" disabled={isSaving} onClick={onForward}>
                <Send size={14} />
                Forward verified
              </button>
            )}
          </div>
        </form>
      </section>
    </div>,
    document.body,
  )
}
