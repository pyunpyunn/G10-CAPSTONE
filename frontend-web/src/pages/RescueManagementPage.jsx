import { useCallback, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { getDispatchDashboard } from '../api/dispatchApi'
import { ResponseOperationsLayout } from '../components/ui/PageHeader'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'

export default function RescueManagementPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('active')
  const loader = useCallback(() => getDispatchDashboard({ status, page, per_page: 20 }), [page, status])
  const { data, error, loading } = useModuleData(loader, ['dispatch', 'households', 'disasters'])
  const rows = data?.dispatches?.data || []
  return <ResponseOperationsLayout active={1}><section className="pg on">
    <div className="hd"><span className={`ev${data?.active_event ? '' : ' off'}`}><i />{data?.active_event?.name || 'No active event'}</span><span className="sp" /><button className="bt p pl2" type="button" disabled={loading || !data?.active_event} onClick={() => navigate('/rescue-management/new')}>New Dispatch<i><Plus size={15} /></i></button></div>
    {!loading && !error && !data?.active_event && <div className="nb">No active event · dispatch disabled</div>}
    <div className="fb"><div className="ft">{[['active','Active'], ['dispatched','Dispatched'], ['on_scene','On-scene'], ['returning','Returning'], ['closed','Closed']].map(([value, name]) => <button key={value} type="button" className={status === value ? 'on' : ''} onClick={() => { setStatus(value); setPage(1) }}>{name}</button>)}</div></div>
    <div className="cd" style={{ flex: 1 }}><div className="tw">{error ? <div className="form-error" role="alert">{error}</div> : loading ? <div className="empty-reference" role="status">Loading assignments…</div> : !rows.length ? <div className="empty-reference">No assignments</div> : <table><thead><tr><th>Code</th><th>Team</th><th>Area</th><th>Priority</th><th>Assigned</th><th>Status</th><th /></tr></thead><tbody>{rows.map((row) => <tr key={row.assignment_id} onClick={() => navigate(`/rescue-management/${row.assignment_id}/edit`)}><td><b>{row.assignment_code}</b></td><td>{row.team_name}</td><td>{row.assigned_area}</td><td><span className="pl" style={{ '--c': ['high','critical'].includes(row.priority_level) ? '#dc2626' : row.priority_level === 'watch' ? '#d97706' : '#2563eb' }}>{row.priority_label}</span></td><td>{row.assigned_at}</td><td><DispatchStatusBadge status={row.status} /></td><td><button className="lk" type="button" onClick={(event) => { event.stopPropagation(); navigate(`/rescue-management/${row.assignment_id}/edit`) }}>Update</button></td></tr>)}</tbody></table>}</div><PaginationBar reference meta={data?.dispatches?.meta || {}} onPageChange={setPage} label="assignments" /></div>
  </section></ResponseOperationsLayout>
}
