import { useCallback, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { getResourceRequests } from '../api/resourceRequestApi'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'

export default function RequestWorkflowPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const external = location.pathname === '/external-requests'
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const loader = useCallback(() => getResourceRequests({ page, per_page: 20, search, status, workflow: external ? 'approval' : 'monitoring' }), [page, search, status, external])
  const { data, error, loading, refresh } = useModuleData(loader)
  const open = (row, mode) => navigate(`/resources-requests/${row.request_id}/${mode}`, { state: { returnTo: location.pathname } })
  return <main className="ops-page resources-page">
    <PageHeader title={external ? 'External Requests' : 'Request Monitoring'} actions={<button className="btn btn-secondary" onClick={refresh}>Refresh</button>} />
    <div className="dp-side-head">
      <label>Search requests <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} /></label>
      {!external && <label>Request status <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }}>
        <option value="all">All approved requests</option><option value="verified">Approved</option><option value="forwarded">In progress</option><option value="fulfilled">Completed requests</option>
      </select></label>}
    </div>
    <ModuleDataView title={external ? 'For approval request list' : status === 'fulfilled' ? 'Completed requests' : 'Approved request list and status tracking'}
      rows={data?.requests?.data || []} loading={loading} error={error}
      columns={[{ key: 'request_id', label: 'Request' }, { key: 'source', label: 'Source', render: (row) => row.request_source?.label },
        { key: 'need', label: 'Request type', render: (row) => row.need?.type }, { key: 'quantity', label: 'Quantity', render: (row) => row.need?.quantity_text },
        { key: 'area', label: 'Area', render: (row) => row.area?.label }, { key: 'status', label: 'Status', render: (row) => row.status?.label || row.validation?.label },
        { key: 'tracking', label: 'Tracking', render: (row) => `${row.handoff?.label || 'Awaiting handoff'} ${row.handoff?.tracking_reference || ''}` },
        { key: 'actions', label: 'Actions', render: (row) => <><button className="btn btn-secondary" onClick={() => open(row, 'view')}>View request</button>
          {external && <button className="btn btn-primary" onClick={() => open(row, 'validate')}>Approve request</button>}
          {!external && row.validation?.key === 'verified' && <button className="btn btn-primary" onClick={() => open(row, 'view')}>Manage handoff</button>}</> }]} />
    <PaginationBar meta={data?.requests || {}} onPageChange={setPage} label="requests" />
    {!external && <TrackingAidMirror items={data?.tracking_mirror || []} />}
  </main>
}
