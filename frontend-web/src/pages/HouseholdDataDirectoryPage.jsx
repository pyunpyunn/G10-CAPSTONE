import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { getHouseholdDirectory } from '../api/householdApi'
import api from '../api/client'
import { HouseholdPagination, RefreshButton } from '../components/households/HouseholdPrototype'
import { useModuleData } from '../utils/useModuleData'

const perPage = 10
const geographicLabels = {
  source_record: 'SafeTrack census',
  assigned: 'Assigned',
  missing_address: 'Address missing',
  unassigned_sitio: 'Sitio not assigned',
  unassigned_purok: 'Purok not assigned',
  invalid_sitio: 'Sitio ID not found',
  invalid_purok: 'Purok ID not found',
  sitio_mismatch: 'Address sitio differs from purok parent',
}

function recorded(value) {
  if (value === true) return 'Yes'
  if (value === false) return 'No'
  return value === null || value === undefined || value === '' ? 'Not recorded' : value
}

function activeLabel(value) {
  if (value === null || value === undefined) return 'Status not recorded'
  return value === true || value === 1 || value === '1' ? 'Active' : 'Inactive'
}

function fullName(person) {
  const name = [person.first_name, person.middle_name, person.last_name].filter(Boolean).join(' ').trim()
  return name || person.name || 'Unnamed'
}

function addressLabel(address) {
  return address?.full_address
    || [address?.house_number, address?.street, address?.barangay_name].filter(Boolean).join(', ')
    || 'Address not recorded'
}

function SummaryCard({ label, value }) {
  return <div className="household-directory-summary"><span>{label}</span><strong>{recorded(value ?? 0)}</strong></div>
}

const detailTabs = [
  { id: 'household', label: 'Household' },
  { id: 'members', label: 'Members' },
  { id: 'accounts', label: 'Accounts' },
]

const householdColumns = [
  { id: 'name', label: 'Household name', default: true, value: row => row.household.household_name },
  { id: 'id', label: 'Household ID', value: row => row.household.household_id },
  { id: 'code', label: 'Code / number', value: row => row.household.household_code || row.household.household_number },
  { id: 'email', label: 'Email', value: row => row.household.email },
  { id: 'contact', label: 'Contact', default: true, value: row => row.household.contact_number },
  { id: 'emergency', label: 'Emergency contact', value: row => row.household.emergency_contact },
  { id: 'member_count', label: 'Recorded member count', default: true, value: row => row.household.member_count },
  { id: 'created_by', label: 'Created by', value: row => row.household.created_by },
  { id: 'address', label: 'Address', default: true, value: row => addressLabel(row.address) },
  { id: 'barangay', label: 'Site / barangay', default: true, value: row => row.address?.barangay_name || row.address?.barangay_id },
  { id: 'sitio', label: 'Sitio', default: true, value: row => row.address?.sitio_name || row.address?.registered_sitio_id || row.address?.sitio_id },
  { id: 'purok', label: 'Purok', default: true, value: row => row.address?.purok_name || row.address?.purok_id },
  { id: 'geography', label: 'Geographic assignment', value: row => geographicLabels[row.geographic_status] || 'Geography unavailable' },
  { id: 'address_id', label: 'Address ID', value: row => row.address?.address_id },
  { id: 'legacy_area', label: 'Legacy area text', value: row => row.address?.purok_sitio },
  { id: 'zip', label: 'ZIP code', value: row => row.address?.zip_code },
]

const memberColumns = [
  { id: 'name', label: 'Member name', default: true, value: member => `${member.display_name || fullName(member)}${member.is_household_head ? ' (Household head)' : ''}` },
  { id: 'relationship', label: 'Relationship', default: true, value: member => member.relation || member.relationship_id },
  { id: 'birth_date', label: 'Birth date', default: true, value: member => member.birth_date },
  { id: 'gender', label: 'Gender', value: member => member.gender || member.sex || member.gender_id },
  { id: 'id', label: 'Member ID', value: member => member.member_id },
  { id: 'civil_status', label: 'Civil status', value: member => member.civil_status || member.civil_status_id },
  { id: 'occupation', label: 'Occupation', value: member => member.occupation },
  { id: 'education', label: 'Education', value: member => member.education_level || member.education_level_id },
  { id: 'contact', label: 'Contact', value: member => member.contact_number },
  { id: 'religion', label: 'Religion', value: member => member.religion },
  { id: 'special_needs', label: 'Special needs', value: member => member.special_needs },
  { id: 'vulnerability', label: 'PWD / Senior / Pregnant', value: member => [member.is_pwd && 'PWD', member.is_senior && 'Senior', member.is_pregnant && 'Pregnant'].filter(Boolean).join(', ') },
  { id: 'can_report', label: 'Can report for household', value: member => member.can_report_for_household },
]

const accountColumns = [
  { id: 'name', label: 'Account name', default: true, value: account => fullName(account) },
  { id: 'username', label: 'Username', default: true, value: account => account.username },
  { id: 'status', label: 'Status', default: true, value: account => activeLabel(account.is_active) },
  { id: 'email', label: 'Email', value: account => account.email },
  { id: 'contact', label: 'Contact', value: account => account.contact_number },
  { id: 'user_id', label: 'User ID', value: account => account.user_id },
  { id: 'household_id', label: 'Household ID', value: account => account.household_id },
  { id: 'member_id', label: 'Member ID', value: account => account.member_id },
  { id: 'member_link', label: 'Member link', value: account => account.member_link_status === 'matched' ? 'Matched' : account.member_link_status === 'unmatched' ? 'Member ID does not belong to this household' : 'No member assigned' },
  { id: 'role_id', label: 'Role ID', value: account => account.role_id },
]

const columnsByTab = { household: householdColumns, members: memberColumns, accounts: accountColumns }
const detailRowsPerPage = 7

function HouseholdDetailsModal({ item, onClose }) {
  const dialog = useRef(null)
  const [activeTab, setActiveTab] = useState('household')
  const [detailPage, setDetailPage] = useState(1)
  const [visibleColumns, setVisibleColumns] = useState(() => Object.fromEntries(
    Object.entries(columnsByTab).map(([tab, columns]) => [tab, columns.filter(column => column.default).map(column => column.id)]),
  ))
  const records = {
    household: [item],
    members: item.members || [],
    accounts: item.accounts || [],
  }[activeTab]
  const columns = columnsByTab[activeTab].filter(column => visibleColumns[activeTab].includes(column.id))
  const pageCount = Math.ceil(records.length / detailRowsPerPage)
  const visibleRecords = records.slice((detailPage - 1) * detailRowsPerPage, detailPage * detailRowsPerPage)

  useEffect(() => {
    const element = dialog.current
    const previous = document.activeElement
    const overflow = document.body.style.overflow
    element.showModal()
    document.body.style.overflow = 'hidden'
    return () => {
      element.close()
      document.body.style.overflow = overflow
      previous?.focus()
    }
  }, [])

  function toggleColumn(id) {
    setVisibleColumns(current => {
      const selected = current[activeTab]
      return {
        ...current,
        [activeTab]: selected.includes(id) ? selected.filter(value => value !== id) : [...selected, id],
      }
    })
  }

  function changeTab(tab) {
    setActiveTab(tab)
    setDetailPage(1)
  }

  return createPortal(<dialog ref={dialog} className="household-directory-modal" aria-labelledby="household-directory-modal-title" onCancel={event => { event.preventDefault(); onClose() }} onClick={event => {
    if (event.target !== event.currentTarget) return
    const bounds = event.currentTarget.getBoundingClientRect()
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) onClose()
  }}>
    <header className="household-directory-modal-head">
      <div><h2 id="household-directory-modal-title">{item.household.household_name || 'Unnamed household'}</h2></div>
      <button type="button" aria-label="Close household details" onClick={onClose}><X size={20} /></button>
    </header>
    <div className="household-directory-modal-body">
      <nav className="household-directory-modal-tabs" aria-label="Household details">
        {detailTabs.map(tab => <button key={tab.id} type="button" className={activeTab === tab.id ? 'active' : ''} aria-pressed={activeTab === tab.id} onClick={() => changeTab(tab.id)}>
          {tab.label}<span>{tab.id === 'members' ? item.member_count : tab.id === 'accounts' ? item.account_count : ''}</span>
        </button>)}
        <details className="household-directory-column-menu">
          <summary>{activeTab === 'household' ? 'Fields' : 'Table columns'}</summary>
          <fieldset className="household-directory-column-picker">
            <legend>{activeTab === 'household' ? 'Show fields' : 'Show columns'}</legend>
            {columnsByTab[activeTab].map(column => <label key={column.id}>
              <input type="checkbox" checked={visibleColumns[activeTab].includes(column.id)} onChange={() => toggleColumn(column.id)} />
              {column.label}
            </label>)}
          </fieldset>
        </details>
      </nav>
      {activeTab === 'household'
        ? columns.length ? <form className="household-directory-field-view" onSubmit={event => event.preventDefault()}>
          {columns.map(column => <label key={column.id}>
            <span>{column.label}</span>
            <input type="text" value={String(recorded(column.value(item)))} readOnly />
          </label>)}
        </form> : <p className="household-directory-no-columns">Select at least one field to display.</p>
        : columns.length ? <div className="household-directory-table-wrap">
        <table className="household-directory-detail-table">
          <thead><tr>{columns.map(column => <th key={column.id}>{column.label}</th>)}</tr></thead>
          <tbody>{records.length ? visibleRecords.map((record, index) => <tr key={record.household?.household_id || record.member_id || record.user_id || (detailPage - 1) * detailRowsPerPage + index}>
            {columns.map(column => <td key={column.id}>{recorded(column.value(record))}</td>)}
          </tr>) : <tr><td colSpan={columns.length}>{activeTab === 'members' ? 'No household member records are linked.' : 'No account is linked to this household.'}</td></tr>}</tbody>
        </table>
      </div> : <p className="household-directory-no-columns">Select at least one column to display data.</p>}
      {pageCount > 1 && <div className="household-directory-detail-pagination" aria-label="Detail table pagination">
        <button type="button" onClick={() => setDetailPage(current => Math.max(1, current - 1))} disabled={detailPage === 1}>Previous</button>
        <span>Page {detailPage} of {pageCount}</span>
        <button type="button" onClick={() => setDetailPage(current => Math.min(pageCount, current + 1))} disabled={detailPage === pageCount}>Next</button>
      </div>}
    </div>
  </dialog>, document.body)
}

function HouseholdRecord({ item, onView }) {
  const household = item.household
  const address = item.address
  const purok = address?.purok_name || (address?.purok_id ? `Purok #${address.purok_id}` : null)
  const sitio = address?.sitio_name || (address?.registered_sitio_id ? `Sitio #${address.registered_sitio_id}` : null)
  const area = [purok, sitio].filter(Boolean).join(', ')
  const status = geographicLabels[item.geographic_status] || 'Geography unavailable'

  return <tr>
    <td>
      <strong>{household.household_name || 'Unnamed household'}</strong>
      <small>{household.household_code || household.household_number || household.household_id}</small>
    </td>
    <td>{area || 'Area not assigned'}<small>{status}</small></td>
    <td>{addressLabel(address)}</td>
    <td>{item.member_count}<small>{household.member_count === null ? 'Recorded total unavailable' : `Recorded: ${household.member_count}`}</small></td>
    <td>{item.account_count}</td>
    <td><button className="household-directory-view-button" type="button" onClick={() => onView(item)}>View details</button></td>
  </tr>
}

function getSitioGroup(item) {
  const address = item.address
  const sitioId = address?.sitio_id ?? address?.registered_sitio_id
  const sitioName = address?.sitio_name || (sitioId !== null && sitioId !== undefined ? `Sitio #${sitioId}` : 'Sitio not assigned')
  return {
    id: sitioId === null || sitioId === undefined ? null : String(sitioId),
    key: sitioId === null || sitioId === undefined ? `name:${sitioName.toLocaleLowerCase()}` : `id:${sitioId}`,
    name: sitioName,
  }
}

export default function HouseholdDataDirectoryPage() {
  const [page, setPage] = useState(1)
  const [groupBy, setGroupBy] = useState('')
  const [selectedHousehold, setSelectedHousehold] = useState(null)
  const [syncing, setSyncing] = useState(false)
  const [syncingSitio, setSyncingSitio] = useState(null)
  const [syncNotice, setSyncNotice] = useState('')
  const loader = useCallback(() => getHouseholdDirectory({
    page,
    per_page: perPage,
  }), [page])
  const { data, error, loading, refresh } = useModuleData(loader, ['households', 'accounts'])
  const households = data?.households
  const summary = data?.summary
  const groupedHouseholds = groupBy === 'sitio'
    ? Object.values((households?.data || []).reduce((groups, item) => {
      const sitio = getSitioGroup(item)
      groups[sitio.key] ||= { ...sitio, items: [] }
      groups[sitio.key].items.push(item)
      return groups
    }, {}))
    : []

  useEffect(() => {
    let cancelled = false
    let checking = false
    async function checkUpdates() {
      if (checking || document.hidden) return
      checking = true
      try {
        const { data: response } = await api.get('/households/directory/updates', { timeout: 90000 })
        const changes = response.data
        if (!cancelled && (changes.new_households || changes.updated_households || changes.removed_households)) {
          setSyncNotice(`SafeTrack updates available: ${changes.new_households} new, ${changes.updated_households} updated, ${changes.removed_households} removed households. Click Sync from SafeTrack to apply.`)
        }
      } catch {
        if (!cancelled) setSyncNotice('Unable to check SafeTrack for updates. Previously synced records remain available.')
      } finally { checking = false }
    }
    const timer = window.setInterval(checkUpdates, 60000)
    return () => { cancelled = true; window.clearInterval(timer) }
  }, [])

  async function syncHouseholds() {
    setSyncing(true)
    try {
      const { data: response } = await api.post('/households/directory/sync', {}, { timeout: 90000 })
      setSyncNotice(`Synced ${response.data.total} households from SafeTrack. ${response.data.new_households} new households.`)
      setPage(1)
      refresh()
    } catch (failure) {
      setSyncNotice(failure.response?.data?.message || 'SafeTrack sync failed. Please try again.')
    } finally { setSyncing(false) }
  }

  async function syncSitio(group) {
    if (group.id === null) return
    setSyncingSitio(group.id)
    try {
      const { data: response } = await api.post('/households/directory/sync', { sitio_id: group.id }, { timeout: 90000 })
      const result = response.data
      setSyncNotice(`Synced ${result.total} households from ${group.sitio_name}. ${result.new_households} new, ${result.updated_households} updated.`)
      refresh()
    } catch (failure) {
      setSyncNotice(failure.response?.data?.message || `Unable to sync households from ${group.sitio_name}. Please try again.`)
    } finally { setSyncingSitio(null) }
  }

  return <section className="hh-prototype" aria-label="Household data directory">
    <div className="hd">
      <Link className="bk" to="/households" title="Back to household status" aria-label="Back to household status">‹</Link>
      <b className="ttl">New Household Data</b>
      <span className="sp" />
      <button className="bt" type="button" onClick={syncHouseholds} disabled={syncing}>{syncing ? 'Syncing…' : 'Sync from SafeTrack'}</button>
      <RefreshButton onClick={refresh} loading={loading} />
    </div>
    {syncNotice && <p role="status">{syncNotice}</p>}
    {summary && <div className="household-directory-summary-grid">
      <SummaryCard label="Active households" value={summary.active_households} />
      <SummaryCard label="Active member records" value={summary.active_members} />
      <SummaryCard label="Households without registered purok" value={summary.households_missing_purok} />
      <SummaryCard label="Sitio / purok mismatches" value={summary.household_sitio_purok_mismatches} />
      <SummaryCard label="Accounts with no matching household" value={summary.unmatched_household_accounts} />
      <SummaryCard label="Member count mismatches" value={summary.household_member_count_mismatches} />
    </div>}
    {error && <div className="emp" role="alert">{error}</div>}
    <div className="cd" style={{ flex: 1 }}>
      <div className="household-directory-group-control">
        <label htmlFor="household-directory-group-by">Group by</label>
        <select id="household-directory-group-by" value={groupBy} onChange={event => { setGroupBy(event.target.value); setPage(1) }}>
          <option value="">None</option>
          <option value="sitio">Sitio</option>
        </select>
      </div>
      <div className="tw">
        <table aria-busy={loading}>
          <thead><tr><th>Household</th><th>Sitio / purok</th><th>Address</th><th>Members</th><th>Accounts</th><th>Action</th></tr></thead>
          <tbody>
            {loading && !households
              ? Array.from({ length: 5 }, (_, rowIndex) => <tr key={`skeleton-${rowIndex}`}>
                {Array.from({ length: 6 }, (_, columnIndex) => <td key={columnIndex}>
                  <span className="household-directory-skeleton" aria-hidden="true" />
                  {rowIndex === 0 && columnIndex === 0 && <span className="household-directory-sr-only">Loading household records…</span>}
                </td>)}
              </tr>)
              : households?.data?.length
                ? groupBy === 'sitio'
                  ? groupedHouseholds.flatMap(group => [
                    <tr className="household-directory-group-row" key={`group:${group.key}`}>
                      <th scope="rowgroup" colSpan={6}>
                        <div className="household-directory-group-heading">
                          <span>GROUP BY SITIO: {group.name} <small>({group.items.length} households)</small></span>
                          <button className="household-directory-view-button" type="button" onClick={() => syncSitio(group)} disabled={syncing || syncingSitio !== null || group.id === null} title={group.id === null ? 'A registered sitio ID is required to sync this group.' : undefined}>
                            {syncingSitio === group.id ? 'Syncing…' : 'SYNC DATA'}
                          </button>
                        </div>
                      </th>
                    </tr>,
                    ...group.items.map(item => <HouseholdRecord key={item.household.household_id} item={item} onView={setSelectedHousehold} />),
                  ])
                  : households.data.map(item => <HouseholdRecord key={item.household.household_id} item={item} onView={setSelectedHousehold} />)
                : <tr><td className="household-directory-empty" colSpan={6}>{error ? 'Household records could not be loaded.' : 'There are no household records at the moment.'}</td></tr>}
          </tbody>
        </table>
      </div>
      <HouseholdPagination meta={households?.meta} loading={loading} onPageChange={setPage} />
    </div>
    {selectedHousehold && <HouseholdDetailsModal key={selectedHousehold.household.household_id} item={selectedHousehold} onClose={() => setSelectedHousehold(null)} />}
  </section>
}
