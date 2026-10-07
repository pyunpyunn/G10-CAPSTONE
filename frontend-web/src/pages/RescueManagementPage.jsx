import { useCallback, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDispatchDashboard } from '../api/dispatchApi'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'

export default function RescueManagementPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const loader = useCallback(() => getDispatchDashboard({ status: 'active', page, per_page: 20 }), [page])
  const { data, error, loading, refresh } = useModuleData(loader)
  const rows = (data?.dispatches?.data || []).filter((row) => !['completed', 'cancelled'].includes(row.status?.key))
  return <main className="ops-page">
    <PageHeader title="Rescue Management" actions={<button className="button review" disabled={!data?.active_event} onClick={() => navigate('/dispatch/new', { state: { returnTo: '/rescue-management' } })}>Create new dispatch assignment</button>} />
    <ModuleDataView title="Active dispatch assignments" rows={rows} loading={loading} error={error}
      actions={<button className="btn btn-secondary" onClick={refresh}>Refresh</button>}
      columns={[{ key: 'assignment_code', label: 'Assignment' }, { key: 'team_name', label: 'Team' }, { key: 'assigned_area', label: 'Area' },
        { key: 'status', label: 'Status', render: (row) => <DispatchStatusBadge status={row.status} /> },
        { key: 'actions', label: 'Actions', render: (row) => <button className="btn btn-secondary" onClick={() => navigate('/dispatch/new', { state: { dispatch: row, returnTo: '/rescue-management' } })}>Update assignment</button> }]} />
    <PaginationBar meta={data?.dispatches || {}} onPageChange={setPage} label="assignments" />
  </main>
}
