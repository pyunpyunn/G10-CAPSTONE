export default function SummaryTable({
  rows = [],
  activeKey = 'all',
  onSelectRow,
  showIdColumn = false,
  idLabel = 'Status ID',
}) {
  return (
    <div className="summary-table-wrap">
      <table className="summary-table">
        <thead>
          <tr>
            <th>Status</th>
            {showIdColumn ? <th>{idLabel}</th> : null}
            <th>Count</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const rowKey = row.key || row.status_id || row.label
            const isActive = activeKey === row.key

            return (
              <tr
                key={rowKey}
                className={isActive ? 'is-active' : ''}
                tabIndex={0}
                role="button"
                onClick={() => onSelectRow?.(row)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    onSelectRow?.(row)
                  }
                }}
              >
                <td>{row.label}</td>
                {showIdColumn ? <td><code>{row.status_id ?? row.event_id ?? '—'}</code></td> : null}
                <td>{row.count ?? 0}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
