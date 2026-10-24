import { statuses } from '../utils/householdPrototype'
import { useCallback, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { getHouseholds } from '../api/householdApi'
import { useModuleData } from '../utils/useModuleData'
import { HouseholdStatus, HouseholdPagination, RefreshButton } from '../components/households/HouseholdPrototype'

export default function HouseholdPurokListPage() {
  const [params, setParams] = useSearchParams()
  const location = useLocation()
  const navigate = useNavigate()
  const [pageState, setPage] = useState({ query: '', page: 1 })
  const query = params.toString()
  const page = pageState.query === query ? pageState.page : 1
  const sitioId = params.get('sitio_id'), purokId = params.get('purok_id')
  const valid = (!sitioId || /^\d+$/.test(sitioId)) && (!purokId || /^\d+$/.test(purokId))
  const loader = useCallback(async () => {
    if (!valid) throw new Error('Choose a valid sitio or purok from the ranking.')
    const search = new URLSearchParams(query)
    const filters = Object.fromEntries(['sitio_id', 'purok_id', 'status'].filter(key => search.get(key)).map(key => [key, search.get(key)]))
    if (filters.status === 'safe') filters.status = 'safe_only'
    return getHouseholds({ ...filters, page, per_page: 25 })
  }, [query, page, valid])
  const { data, error, loading, refresh } = useModuleData(loader, ['households', 'disasters'])
  const scope = data?.selected_scope
  const households = data?.households
  function filter(key, value) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value); else next.delete(key)
    if (key === 'purok_id') next.delete('purok')
    next.delete('sitio')
    if (!next.get('sitio_id') && scope?.sitio) next.set('sitio_id', String(scope.sitio.sitio_id))
    setParams(next)
  }
  function reviewHousehold(household) {
    navigate(`/households/${household.household_id}`, { state: { returnTo: location.pathname + location.search } })
  }
  return <section className="hh-prototype" aria-label="Household list">
    <div className="hd"><Link className="bk" to="/households" title="Go back to main page" aria-label="Go back to main page">‹</Link><b className="ttl">{loading ? 'Loading households…' : scope?.sitio ? `${scope.sitio.sitio_name}${scope.purok ? ` · ${scope.purok.purok_name}` : ''}` : error ? 'Household list unavailable' : 'All households'}</b><span className="sp" /><Link className="bt" to="/households/_data-directory">Data directory</Link>
      {scope?.sitio && <select aria-label="Purok" value={purokId || ''} onChange={event => filter('purok_id', event.target.value)}><option value="">All puroks</option>{scope.puroks.map(purok => <option key={purok.purok_id} value={purok.purok_id}>{purok.purok_name}</option>)}</select>}
      <select aria-label="Status" value={params.get('status') || ''} onChange={event => filter('status', event.target.value)}><option value="">All status</option>{statuses.map(status => <option key={status.key} value={status.key}>{status.label}</option>)}</select><RefreshButton onClick={refresh} loading={loading} />
    </div>
    {error && <div className="emp" role="alert">{error}</div>}
    <div className="cd" style={{ flex: 1 }}><div className="tw">{loading ? <div className="emp" role="status">Loading households…</div> : !households?.data?.length ? <div className="emp">No households found</div> : <table><thead><tr><th>Household</th><th>Purok</th><th>People</th><th>Status</th><th>Last location</th></tr></thead><tbody>{households.data.map(household => <tr className="cl" key={household.household_id} tabIndex={0} onClick={() => reviewHousehold(household)} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); reviewHousehold(household) } }} aria-label={`Review ${household.household_name}`}>
      <td><b>{household.household_name}</b><small>{household.account_holder}{household.account_id ? ` · ${household.account_id}` : ''}</small></td><td>{household.purok}</td><td>{household.people}</td><td><HouseholdStatus status={household.status} /></td><td>{household.location?.label || '—'}{household.location?.note && <small>{household.location.note}</small>}</td>
    </tr>)}</tbody></table>}</div><HouseholdPagination meta={households?.meta} loading={loading} onPageChange={next => setPage({ query, page: next })} /></div>
  </section>
}
