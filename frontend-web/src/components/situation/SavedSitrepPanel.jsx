import Badge from '../ui/Badge'

export default function SavedSitrepPanel({ reports = [], onOpen }) {
  return (
    <div className="sr-saved-panel">
      <div className="rr-panel-head">
        <span className="rr-title">Saved SitRep snapshots</span>
        <span className="rr-subtle">{reports.length ? `${reports.length} latest` : 'No saved reports yet'}</span>
      </div>
      <div className="sr-saved-list">
        {reports.length === 0 ? (
          <div className="sitrep-empty-state sr-saved-empty">Generated SitRep snapshots will appear here.</div>
        ) : reports.map((report) => (
          <button className="sr-saved-row" type="button" key={report.sit_rep_id} onClick={() => onOpen(report)}>
            <div>
              <strong>{report.report_number}</strong>
              <span>{report.event_name} - {report.generated_at}</span>
            </div>
            <Badge tone={report.is_archived ? 'green' : 'blue'}>{report.report_status}</Badge>
          </button>
        ))}
      </div>
    </div>
  )
}
