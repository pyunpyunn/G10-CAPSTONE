import LoadingState from './LoadingState'
import EmptyState from './EmptyState'

export default function ModuleDataView({ title, rows = [], columns, loading, error, actions }) {
  return <section className="dp-side-card">
    <div className="dp-side-head"><strong className="dp-side-title">{title}</strong>{actions}</div>
    <div className="dp-side-body dp-table-body">
      {error && <div className="form-error" role="alert">{error}</div>}
      {loading ? <LoadingState /> : error && !rows.length ? null : rows.length ?
        <div style={{ overflowX: 'auto' }}><table><thead><tr>{columns.map((column) => <th key={column.key}>{column.label}</th>)}</tr></thead>
          <tbody>{rows.map((row, index) => <tr key={row.id || row.request_id || row.assignment_id || row.status_log_id || index}>
            {columns.map((column) => <td key={column.key}>{column.render ? column.render(row) : row[column.key] ?? '—'}</td>)}
          </tr>)}</tbody></table></div> : <EmptyState title="No records yet" message={`${title} will appear when records are saved.`} />}
    </div>
  </section>
}
