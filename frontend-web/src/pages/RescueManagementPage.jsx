import { useCallback, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getDispatchDashboard } from '../api/dispatchApi'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import DataFilterBar from '../components/ui/DataFilterBar'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'

export default function RescueManagementPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const loader = useCallback(() => getDispatchDashboard({ status: 'active', search, page, per_page: 20 }), [page, search])
  const { data, error, loading, refresh } = useModuleData(loader)
  return <main className="ops-page response-operations-page">
    <PageHeader title="Rescue Management" subtitle={data?.active_event?.name || 'Active dispatch assignments'}
      actions={<button className="button review" type="button" disabled={loading || !data?.active_event} onClick={() => navigate('/rescue-management/new')}>Create New Dispatch Assignment</button>} />
    {!loading && !error && !data?.active_event && <div className="standby-strip"><strong>No active disaster event</strong><span>Declare an event before creating dispatch assignments.</span></div>}
    <DataFilterBar search={search} onSearchChange={(value) => { setSearch(value); setPage(1) }} searchPlaceholder="Search assignment, team or area..." onReset={() => { setSearch(''); setPage(1) }} />
    <ModuleDataView title="List of Active Dispatch Assignments" rows={data?.dispatches?.data || []} loading={loading} error={error}
      actions={<button className="btn btn-secondary" type="button" onClick={refresh}>Refresh</button>}
      columns={[{ key: 'assignment_code', label: 'Assignment' }, { key: 'team_name', label: 'Team' }, { key: 'assigned_area', label: 'Area' },
        { key: 'priority_label', label: 'Priority' }, { key: 'assigned_at', label: 'Assigned' },
        { key: 'status', label: 'Status', render: (row) => <DispatchStatusBadge status={row.status} /> },
        { key: 'actions', label: 'Actions', render: (row) => <Link className="btn btn-secondary" to={`/rescue-management/${row.assignment_id}/edit`}>Update Dispatch Assignment</Link> }]} />
    <PaginationBar meta={data?.dispatches?.meta || {}} onPageChange={setPage} label="assignments" />
  </main>
}
