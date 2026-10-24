import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight, CircleDot } from 'lucide-react'
import { getFieldReports } from '../api/dispatchApi'
import { ResponseOperationsLayout } from '../components/ui/PageHeader'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import Modal from '../components/ui/Modal'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'

export default function FieldReportsPage() {
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState(null)
  const loader = useCallback(() => getFieldReports({ status, page, per_page: 20 }), [status, page])
  const { data, error, loading } = useModuleData(loader, ['field-reports', 'households', 'dispatch', 'disasters'])
  const reports = Array.isArray(data?.reports) ? data.reports : []
  const badge = (row) => <DispatchStatusBadge status={row.assignment_status} />
  const location = (row) => row.latitude != null && row.longitude != null ? `${row.latitude}, ${row.longitude}` : null
  return <ResponseOperationsLayout active={2}><section className="pg on">
    <div className="fb"><select aria-label="Dispatch status" value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }}><option value="all">All dispatch statuses</option>{(data?.status_options || []).map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><button className="bt" type="button" onClick={() => { setStatus('all'); setPage(1) }}>Reset</button></div>
    <div className="cd" style={{ flex: 1 }}><div className="tw">
      {error ? <div className="form-error" role="alert">{error}</div> : loading ? <div className="empty-reference" role="status">Loading reports…</div> : !reports.length ? <div className="empty-reference">No reports</div> : <table><thead><tr><th>Submitted</th><th>Household</th><th>Dispatch status</th><th>Reporter</th><th>Notes</th><th>Geotag</th><th /></tr></thead><tbody>{reports.map((row) => <tr key={row.status_log_id} onClick={() => setSelected(row)}><td>{row.submitted_at || '—'}</td><td><Link onClick={(event) => event.stopPropagation()} to={`/households/${row.household_id}`}>{row.household_head_name || row.household_code || row.household_id}</Link> <span className="mu">{row.household_code || row.household_id}</span></td><td>{badge(row)}</td><td>{row.reporter_name || 'Not recorded'}</td><td><span className="nt" title={row.notes}>{row.notes?.split('\n')[0] || '—'}</span></td><td>{location(row) ? <span className="geo"><CircleDot size={10} />{location(row)}</span> : <span className="mu">No geotag</span>}</td><td><button className="lk" type="button" onClick={(event) => { event.stopPropagation(); setSelected(row) }}>View</button></td></tr>)}</tbody></table>}
    </div><PaginationBar reference meta={data?.reports_meta || {}} onPageChange={setPage} label="reports" /></div>
  </section>
  <Modal className="reference-drawer" title={selected ? `${selected.household_head_name || 'Household'} · ${selected.household_code || selected.household_id}` : 'Report'} isOpen={Boolean(selected)} onClose={() => setSelected(null)}>
    {selected && <dl className="dl"><dt>Dispatch status</dt><dd>{badge(selected)}</dd><dt>Submitted</dt><dd>{selected.submitted_at || 'Not recorded'}</dd><dt>Reporter</dt><dd>{selected.reporter_name || 'Not recorded'} <span className="mu">{selected.reporter_team_name ? `· ${selected.reporter_team_name}` : ''}</span></dd><dt>Battery</dt><dd>{selected.battery_level != null ? <><span className="mtr"><i style={{ width: `${Math.max(0, Math.min(100, Number(selected.battery_level)))}%` }} /></span>{selected.battery_level}%</> : 'Not reported'}</dd><dt>Location</dt><dd>{location(selected) ? <>{location(selected)} <a className="mp" target="_blank" rel="noreferrer" href={`https://www.openstreetmap.org/?mlat=${selected.latitude}&mlon=${selected.longitude}#map=18/${selected.latitude}/${selected.longitude}`}>Open to map <ArrowUpRight size={12} /></a></> : <span className="mu">No geotag</span>}</dd><dt>Household</dt><dd><Link to={`/households/${selected.household_id}`}>Open account →</Link></dd><dt>Notes</dt><dd className="nts">{selected.notes || 'No notes'}</dd></dl>}
  </Modal></ResponseOperationsLayout>
}
