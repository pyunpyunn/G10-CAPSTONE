import { Database, Download, Eye, FileCheck2, MoreHorizontal } from 'lucide-react'

export default function SituationActionMenu({
  hasSummary,
  onGenerate,
  onArchive,
  onViewArchive,
  onExportCsv,
  onExportPdf,
}) {
  return (
    <details className="download-menu sr-action-menu">
      <summary className="sr-action-summary" aria-label="Situation report options" title="Situation report options">
        <MoreHorizontal size={18} />
      </summary>
      <div className="download-menu-list">
        <button className="btn btn-secondary btn-sm" type="button" disabled={!hasSummary} onClick={onGenerate}>
          <FileCheck2 size={14} />
          Generate official report
        </button>
        <button className="btn btn-secondary btn-sm" type="button" disabled={!hasSummary} onClick={onArchive}>
          <Database size={14} />
          Archive current SitRep
        </button>
        <button className="btn btn-secondary btn-sm" type="button" onClick={onViewArchive}>
          <Eye size={14} />
          View SitRep archive
        </button>
        <button className="btn btn-secondary btn-sm" type="button" disabled={!hasSummary} onClick={onExportPdf}>
          <Download size={14} />
          Export PDF
        </button>
        <button className="btn btn-secondary btn-sm" type="button" disabled={!hasSummary} onClick={onExportCsv}>
          <Download size={14} />
          Export CSV
        </button>
      </div>
    </details>
  )
}
