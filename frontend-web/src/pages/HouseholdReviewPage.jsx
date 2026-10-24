import { useCallback, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { getHousehold, getHouseholdStatusLogs } from '../api/householdApi'
import { useModuleData } from '../utils/useModuleData'
import { HouseholdStatus, HouseholdPagination, RefreshButton } from '../components/households/HouseholdPrototype'

const recorded = value => value === null || value === undefined || value === '' ? 'Not recorded' : value
const percentage = value => value === null || value === undefined || value === '' ? 'Not recorded' : `${value}%`
function Fact({ label, value }) { return <div><em>{label}</em><b>{recorded(value)}</b></div> }
function Section({ title, children }) { return <div className="ps"><h4>{title}</h4><div>{children}</div></div> }
function Table({ headings, children, empty, count }) {
  return count ? <div className="tw2"><table><thead><tr>{headings.map(heading => <th key={heading}>{heading}</th>)}</tr></thead><tbody>{children}</tbody></table></div> : <div className="emp">{empty}</div>
}
function Battery({ value }) { return <><span className="bat"><i style={{ width: `${Math.max(0, Math.min(100, Number(value) || 0))}%` }} /></span>{percentage(value)}</> }

export default function HouseholdReviewPage() {
  const { householdId } = useParams()
  const location = useLocation()
  const [expanded, setExpanded] = useState(false)
  const [pageState, setPage] = useState({ id: '', page: 1 })
  const loader = useCallback(async () => {
    const [detail, logs] = await Promise.all([getHousehold(householdId), getHouseholdStatusLogs(householdId)])
    return { detail, logs: logs.logs || [] }
  }, [householdId])
  const { data, error, loading, refresh } = useModuleData(loader, ['households', 'disasters'])
  const h = data?.detail?.household
  const devices = data?.detail?.devices || [], members = data?.detail?.members || []
  const history = data?.logs || []
  const page = Math.min(pageState.id === householdId ? pageState.page : 1, Math.max(1, Math.ceil(history.length / 5)))
  const context = new URLSearchParams(location.search)
  const dispatchId = context.get('dispatch_id')
  const returnContext = new URLSearchParams()
  if (/^DSP-\d+-\d+$/.test(context.get('dispatch_code') || '')) returnContext.set('dispatch_code', context.get('dispatch_code'))
  if (/^\d+$/.test(context.get('responder_id') || '')) returnContext.set('responder_id', context.get('responder_id'))
  const dispatchReturn = /^\d+$/.test(dispatchId || '') ? `/rescue-management/${dispatchId}/edit${returnContext.size ? `?${returnContext}` : ''}` : null
  const householdListPath = h?.sitio ? `/households/_household-list?${new URLSearchParams({ sitio_id: String(h.sitio.sitio_id), ...(h.purok_id ? { purok_id: String(h.purok_id) } : {}) })}` : '/households/_household-list'
  const returnTo = location.state?.returnTo?.startsWith('/households/_household-list') ? location.state.returnTo : householdListPath
  const coordinates = h?.location?.latitude != null && h?.location?.longitude != null ? `${h.location.latitude}, ${h.location.longitude}` : null
  return <section className="hh-prototype" aria-label="Household account">
    <div className="hd"><Link className="bk" to={returnTo} title="Back to households" aria-label="Back to households">‹</Link><b className="ttl">{h?.sitio?.sitio_name || h?.purok || 'Household account'}</b><span className="sp" />{dispatchReturn && <Link className="bt" to={dispatchReturn}>Back to rescue dispatch</Link>}<RefreshButton onClick={refresh} loading={loading} /></div>
    {error && <div className="emp" role="alert">{error}</div>}
    {loading ? <div className="cd sby" role="status">Loading household account…</div> : h && <div className="cd acv">
      <div className="cover"><span className="slot" /><span className="hv">{(h.household_name || 'HH').slice(0, 2).toUpperCase()}</span><div className="hn"><div className="nm">{h.household_name}</div><div className="cm">{h.household_code || h.household_id} · {h.purok || 'Purok not assigned'}</div><HouseholdStatus status={h.status} /></div></div>
      <Section title="Profile"><div className="pf"><Fact label="Head of the house" value={h.household_head?.name} /><Fact label="Account ID" value={h.account_id} /><Fact label="Contact" value={h.contact_number} /><Fact label="Address" value={h.address} /><Fact label="Last location" value={h.location?.label} /><Fact label="Coordinates" value={coordinates} /><Fact label="Last report" value={[h.source?.label, h.source?.submitted_by, h.source?.datetime].filter(Boolean).join(' · ')} /></div></Section>
      <Section title="Devices"><Table headings={['Device', 'Member', 'Battery', 'Signal', 'Location', 'Last seen']} count={devices.length} empty="No devices linked">{devices.map(device => <tr key={device.id || device.device_uuid}><td title={`Role: ${recorded(device.app_role)} · ${device.is_active ? 'Active' : 'Inactive'}`}><span className="bg" style={{ '--c': device.is_active ? '#0ea5e9' : '#94a3b8' }}>{device.device_name}</span> <span className="mu">{device.platform}</span></td><td>{device.member_name}</td><td><Battery value={device.battery_level} /></td><td>{percentage(device.signal_strength)}</td><td title={device.last_location_label}>{device.location_permission_status || 'Unknown'}</td><td>{recorded(device.last_seen_at)}</td></tr>)}</Table></Section>
      <Section title="Members"><Table headings={['Member', 'Relationship', 'Vulnerability', 'Status']} count={members.length} empty="Members have not synced">{members.map(member => <tr key={member.member_id || member.name}><td title={`${recorded(member.member_id)} · ${recorded(member.gender)} · ${member.is_household_head ? 'Household head' : 'Member'} · ${recorded(member.last_location_label)} · ${recorded(member.device_name)} · ${percentage(member.battery_level)}`}>{member.name} <span className="mu">· {recorded(member.age)}</span></td><td>{member.relation}</td><td>{[member.is_pwd && 'PWD', member.is_senior && 'Senior', member.is_pregnant && 'Pregnant'].filter(Boolean).map(flag => <span className="tag" key={flag}>{flag}</span>)}{!member.is_pwd && !member.is_senior && !member.is_pregnant && '–'}</td><td><HouseholdStatus status={member.status} />{member.status_scope === 'household' && <span className="mu"> Household report</span>} <span className="mu">{member.status_updated_at}</span></td></tr>)}</Table></Section>
      <Section title="History"><button type="button" className={`acc ${expanded ? 'on' : ''}`} aria-expanded={expanded} onClick={() => setExpanded(!expanded)}><i>›</i><span>{history.length}</span><span className="sr-only">Toggle status history for the active event</span></button>{expanded && <><Table headings={['Time', 'Status', 'Submitted by']} count={history.length} empty="No history for the active event">{history.slice((page - 1) * 5, page * 5).map(log => <tr key={log.status_log_id}><td>{log.submitted_at}</td><td><HouseholdStatus status={log.status} /></td><td>{log.source} · {log.submitted_by}</td></tr>)}</Table>{history.length > 5 && <HouseholdPagination meta={{ total: history.length, current_page: page, last_page: Math.ceil(history.length / 5), from: (page - 1) * 5 + 1, to: Math.min(page * 5, history.length) }} onPageChange={next => setPage({ id: householdId, page: next })} />}</>}</Section>
    </div>}
  </section>
}
