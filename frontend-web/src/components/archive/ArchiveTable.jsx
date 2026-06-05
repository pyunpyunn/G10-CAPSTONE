import { Eye } from 'lucide-react'
import { ARCHIVE_COLUMNS, ARCHIVE_TABLE_COPY } from '../../utils/archiveHelpers'
import Badge from '../ui/Badge'

export default function ArchiveTable({ category, records = [], pagination = {}, onView }) {
  const columns = ARCHIVE_COLUMNS[category] || ARCHIVE_COLUMNS['disaster-events']
  const copy = ARCHIVE_TABLE_COPY[category] || ARCHIVE_TABLE_COPY['disaster-events']
  const total = pagination.total || 0
  const from = pagination.from || 0
  const to = pagination.to || 0

  return (
    <section className="archive-panel active">
      <div className="tbl-wrap archive-table-wrap">
        <div className="tbl-head-row archive-table-head">
          <div>
            <span className="tbl-title">{copy.title}</span>
          </div>
          <span className="archive-table-count">{total ? `Showing ${from}-${to} of ${total}` : 'No records yet'}</span>
        </div>
        <table className="archive-table">
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.key}>{column.label}</th>
              ))}
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {records.length === 0 ? (
              <tr className="archive-empty-row visible">
                <td colSpan={columns.length + 1}>No archive records match the current filters.</td>
              </tr>
            ) : (
              records.map((record) => (
                <tr key={`${category}-${record.id}`} data-archive-row>
                  {columns.map((column) => (
                    <td key={column.key}>{renderCell(record[column.key], column.key, category)}</td>
                  ))}
                  <td>
                    <button className="btn btn-secondary btn-sm archive-view-button" type="button" onClick={() => onView(record)}>
                      <Eye size={14} />
                      View
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  )
}

function renderCell(value, columnKey = '', category = '') {
  if (!value) {
    return 'Not recorded'
  }

  if (typeof value === 'string' || typeof value === 'number') {
    return value
  }

  if (value.label && value.tone) {
    return <Badge tone={value.tone}>{value.label}</Badge>
  }

  if (value.title || value.meta) {
    const meta = compactMeta(value.meta, columnKey, category)

    return (
      <>
        {value.title && <div className="archive-record-title">{value.title}</div>}
        {meta && <div className="archive-record-meta">{meta}</div>}
      </>
    )
  }

  return String(value)
}

function compactMeta(meta, columnKey, category) {
  if (!meta) {
    return ''
  }

  if (category === 'disaster-events' && ['period', 'broadcasts'].includes(columnKey)) {
    return ''
  }

  const text = String(meta)

  if (text.length <= 72) {
    return text
  }

  return `${text.slice(0, 69).trim()}...`
}
