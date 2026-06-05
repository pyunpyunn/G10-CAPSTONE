import { CheckCircle2, Download, X } from 'lucide-react'
import { createPortal } from 'react-dom'

export default function ArchiveRecordModal({ record, categoryLabel, onClose, onDownload }) {
  if (!record) {
    return null
  }

  return createPortal(
    <div
      className="archive-view-modal-overlay open"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose()
        }
      }}
    >
      <section className="archive-view-modal" role="dialog" aria-modal="true" aria-labelledby="archiveViewTitle">
        <div className="archive-view-head">
          <div>
            <div className="archive-view-kicker">{categoryLabel}</div>
            <div className="archive-view-title" id="archiveViewTitle">{recordTitle(record)}</div>
          </div>
          <button className="archive-view-close" type="button" onClick={onClose} aria-label="Close archive record">
            <X size={16} />
          </button>
        </div>

        <div className="archive-view-body">
          <div className="archive-detail-grid">
            {(record.details || []).map((item) => (
              <div className="archive-detail-item" key={item.label}>
                <span>{item.label}</span>
                <strong>{item.value}</strong>
              </div>
            ))}
          </div>
        </div>

        <div className="archive-view-actions">
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => onDownload('pdf')}>
            <Download size={14} />
            PDF
          </button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => onDownload('csv')}>
            <Download size={14} />
            CSV
          </button>
          <button className="btn btn-primary btn-sm" type="button" onClick={onClose}>
            <CheckCircle2 size={14} />
            Done
          </button>
        </div>
      </section>
    </div>,
    document.body,
  )
}

function recordTitle(record) {
  return record?.event?.title
    || record?.household?.title
    || record?.team_route?.title
    || record?.request?.title
    || record?.sitrep?.title
    || 'Archive record'
}
