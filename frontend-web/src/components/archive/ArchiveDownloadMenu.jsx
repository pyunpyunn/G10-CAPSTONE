import { Download } from 'lucide-react'

export default function ArchiveDownloadMenu({ disabled = false, onDownload }) {
  return (
    <details className="download-menu archive-download-menu">
      <summary className={`btn btn-secondary btn-sm ${disabled ? 'disabled-summary' : ''}`}>
        <Download size={14} />
        Download
      </summary>
      {!disabled && (
        <div className="download-menu-list">
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => onDownload('excel')}>
            <Download size={14} />
            Excel file
          </button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => onDownload('pdf')}>
            <Download size={14} />
            PDF file
          </button>
        </div>
      )}
    </details>
  )
}
