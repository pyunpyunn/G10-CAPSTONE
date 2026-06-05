import EmptyState from './EmptyState'

export default function DataTable({ columns, rows, emptyTitle = 'No records', emptyMessage = 'Records will appear here when data is available.' }) {
  if (!rows || rows.length === 0) {
    return (
      <div className="tbl-wrap">
        <EmptyState title={emptyTitle} message={emptyMessage} />
      </div>
    )
  }

  return (
    <div className="tbl-wrap compact-table">
      <table>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.key}>{column.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={row.id || index}>
              {columns.map((column) => (
                <td key={column.key}>{column.render ? column.render(row) : row[column.key]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
