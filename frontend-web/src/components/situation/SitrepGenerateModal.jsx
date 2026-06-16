import { Download, FileCheck2, X } from 'lucide-react'
import { createPortal } from 'react-dom'

export default function SitrepGenerateModal({
  isOpen,
  summary,
  form,
  setForm,
  formError,
  isSaving,
  onClose,
  onSubmit,
  onPreviewPdf,
}) {
  if (!isOpen || !summary) {
    return null
  }

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  return createPortal(
    <div className="sr-gen-overlay open" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget) {
        onClose()
      }
    }}>
      <section className="sr-gen-modal" role="dialog" aria-modal="true" aria-labelledby="srGenTitle">
        <div className="sr-gen-head">
          <div>
            <div className="archive-view-kicker">Generate official report</div>
            <div className="sr-gen-title" id="srGenTitle">{summary.event.name} SitRep snapshot</div>
          </div>
          <button className="sr-close" type="button" onClick={onClose} aria-label="Close SitRep generator">
            <X size={16} />
          </button>
        </div>
        <form id="sitrepGenerateForm" onSubmit={onSubmit}>
          <div className="sr-gen-body">
            <div className="sr-gen-guidance">Generate a locked SitRep after verifying household status, dispatch status, weather, resources, and casualty fields for the selected event.</div>
            <div className="sr-gen-grid">
              <div className="form-group">
                <label className="form-label" htmlFor="srReportNo">SitRep number</label>
                <input id="srReportNo" type="text" value={form.report_number} onChange={(event) => setField('report_number', event.target.value)} />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="srSelectedEvent">Selected event</label>
                <input id="srSelectedEvent" type="text" value={summary.event.name} readOnly />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="srPeriodStart">Period start</label>
                <input id="srPeriodStart" type="datetime-local" value={form.period_start} onChange={(event) => setField('period_start', event.target.value)} />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="srPeriodEnd">Period end</label>
                <input id="srPeriodEnd" type="datetime-local" value={form.period_end} onChange={(event) => setField('period_end', event.target.value)} />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="srPreparedBy">Prepared by</label>
                <input id="srPreparedBy" type="text" value={form.prepared_by} onChange={(event) => setField('prepared_by', event.target.value)} />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="srApprovedBy">Reviewed by</label>
                <input id="srApprovedBy" type="text" value={form.reviewed_by} onChange={(event) => setField('reviewed_by', event.target.value)} />
              </div>
            </div>
            <div className="sr-checklist">
              <label className="sr-check"><input type="checkbox" checked readOnly /> Household status verified</label>
              <label className="sr-check"><input type="checkbox" checked readOnly /> Dispatch logs included</label>
              <label className="sr-check"><input type="checkbox" checked readOnly /> Weather source attached</label>
              <label className="sr-check"><input type="checkbox" checked readOnly /> Resources and requests included</label>
            </div>
            {formError && <div className="form-error sr-form-error">{formError}</div>}
          </div>
          <div className="sr-gen-actions">
            <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>Cancel</button>
            <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onPreviewPdf}>
              <Download size={14} />
              Export PDF
            </button>
            <button className="btn btn-primary btn-sm" type="submit" disabled={isSaving}>
              <FileCheck2 size={14} />
              {isSaving ? 'Generating...' : 'Generate & lock'}
            </button>
          </div>
        </form>
      </section>
    </div>,
    document.body,
  )
}
