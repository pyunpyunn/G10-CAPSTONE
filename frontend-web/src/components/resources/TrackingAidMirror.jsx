import { useEffect, useState } from 'react'
import PaginationBar from '../ui/PaginationBar'

export default function TrackingAidMirror({ items = [] }) {
  const perPage = 5
  const [page, setPage] = useState(1)
  const lastPage = Math.max(1, Math.ceil(items.length / perPage))
  const visibleItems = items.slice((page - 1) * perPage, page * perPage)

  useEffect(() => {
    setPage((current) => Math.min(current, lastPage))
  }, [lastPage])

  return (
    <div className="rr-panel rr-mirror">
      <div className="rr-panel-head">
        <span className="rr-title">TrackingAid resource mirror</span>
        <span className="rr-subtle">Acknowledged handoffs from TrackingAid</span>
      </div>
      <div className="rr-mirror-table-wrap">
        <table className="rr-mirror-table">
          <thead>
            <tr>
              <th>TrackingAid ID</th>
              <th>Request ID</th>
              <th>Resource</th>
              <th>Source</th>
              <th>Status</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
        {visibleItems.map((item) => (
            <tr key={item.tracking_reference || item.request_id || item.label}>
              <td>{item.tracking_reference}</td>
              <td>{item.request_id}</td>
              <td>{item.label}</td>
              <td>{item.source}</td>
              <td>{item.status}</td>
              <td>{item.detail}</td>
            </tr>
        ))}
          </tbody>
        </table>
      </div>
      <PaginationBar
        meta={{ current_page: page, per_page: perPage, total: items.length, last_page: lastPage }}
        onPageChange={setPage}
        label="mirror records"
      />
    </div>
  )
}
