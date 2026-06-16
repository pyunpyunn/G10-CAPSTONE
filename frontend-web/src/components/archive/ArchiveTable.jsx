import { Eye } from 'lucide-react'
import { Fragment } from 'react'
import { ARCHIVE_COLUMNS, ARCHIVE_TABLE_COPY, archiveDateLabel } from '../../utils/archiveHelpers'
import Badge from '../ui/Badge'
import PaginationBar from '../ui/PaginationBar'

export default function ArchiveTable({
  category,
  records = [],
  pagination = {},
  selectedIds = [],
  onToggleRecord,
  onToggleDate,
  onTogglePage,
  onView,
  onPageChange,
}) {
  const columns = ARCHIVE_COLUMNS[category] || ARCHIVE_COLUMNS['disaster-events']
  const copy = ARCHIVE_TABLE_COPY[category] || ARCHIVE_TABLE_COPY['disaster-events']
  const total = pagination.total || 0
  const from = pagination.from || 0
  const to = pagination.to || 0
  const pageIds = records.map((record) => String(record.id))
  const isPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id))

  return (
    <section className="archive-panel active">
      <div className="tbl-wrap archive-table-wrap">
        <div className="tbl-head-row archive-table-head">
          <div>
            <span className="tbl-title">{copy.title}</span>
          </div>
          <div className="archive-table-tools">
            <label className="archive-check-label">
              <input
                type="checkbox"
                checked={isPageSelected}
                disabled={records.length === 0}
                onChange={(event) => onTogglePage(pageIds, event.target.checked)}
              />
              Select page
            </label>
            <span className="archive-table-count">{total ? `Showing ${from}-${to} of ${total}` : 'No records yet'}</span>
          </div>
        </div>
        <table className="archive-table">
          <thead>
            <tr>
              <th className="archive-select-col">Select</th>
              {columns.map((column) => (
                <th key={column.key}>{column.label}</th>
              ))}
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {records.length === 0 ? (
              <tr className="archive-empty-row visible">
                <td colSpan={columns.length + 2}>No archive records match the current filters.</td>
              </tr>
            ) : (
              records.map((record, index) => {
                const dateLabel = archiveDateLabel(record)
                const previousDate = index > 0 ? archiveDateLabel(records[index - 1]) : ''
                const dateIds = records
                  .filter((item) => archiveDateLabel(item) === dateLabel)
                  .map((item) => String(item.id))
                const isDateSelected = dateIds.every((id) => selectedIds.includes(id))

                return (
                  <Fragment key={`${category}-${record.id}`}>
                    {dateLabel !== previousDate && (
                      <tr className="archive-date-row" key={`${category}-${dateLabel}`}>
                        <td colSpan={columns.length + 2}>
                          <label className="archive-date-check">
                            <input
                              type="checkbox"
                              checked={isDateSelected}
                              onChange={(event) => onToggleDate(dateIds, event.target.checked)}
                            />
                            {dateLabel}
                          </label>
                        </td>
                      </tr>
                    )}
                    <tr key={`${category}-${record.id}`} data-archive-row>
                      <td className="archive-select-col">
                        <input
                          type="checkbox"
                          checked={selectedIds.includes(String(record.id))}
                          onChange={(event) => onToggleRecord(String(record.id), event.target.checked, record)}
                        />
                      </td>
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
                  </Fragment>
                )
              })
            )}
          </tbody>
        </table>
        <PaginationBar meta={pagination} onPageChange={onPageChange} label="logs" />
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
