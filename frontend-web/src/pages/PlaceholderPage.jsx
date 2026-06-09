import { Database, Download, Filter, RefreshCcw } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader'

export default function PlaceholderPage({ page }) {
  return (
    <section className="page active">
      <PageHeader
        title={page.title}
        actions={
          <>
          <button className="btn btn-secondary btn-sm" type="button">
            <RefreshCcw size={14} />
            Refresh
          </button>
          <button className="btn btn-secondary btn-sm" type="button">
            <Filter size={14} />
            Filter
          </button>
          <button className="btn btn-primary btn-sm" type="button">
            <Download size={14} />
            Export
          </button>
          </>
        }
      />

      <div className="panel module-preview-panel">
        <div className="panel-head">
          <span className="panel-title">
            <Database size={15} />
            {page.title}
          </span>
        </div>
        <div>
          <p className="module-preview-text">{page.summary}</p>
          <div className="module-preview-grid">
            <div className="overview-mini-stat"><strong>Live</strong><span>API ready</span></div>
            <div className="overview-mini-stat"><strong>JSON</strong><span>No HTML backend</span></div>
            <div className="overview-mini-stat"><strong>Role</strong><span>HQ/Admin only</span></div>
          </div>
        </div>
      </div>
    </section>
  )
}
