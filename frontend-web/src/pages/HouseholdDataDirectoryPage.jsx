import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getHouseholdDirectory } from '../api/householdApi'
import api from '../api/client'
import { HouseholdPagination, RefreshButton } from '../components/households/HouseholdPrototype'
import { useModuleData } from '../utils/useModuleData'

const perPage = 25
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

function MemberDetails({ members }) {
  if (!members.length) return <p>No household member records are linked.</p>

  return <div className="household-directory-records">
    {members.map(member => <article className="household-directory-record" key={member.member_id}>
      <h4>{member.display_name || fullName(member)} <small>{member.is_household_head ? '· Household head' : ''}</small></h4>
      <dl>
        <div><dt>Member ID</dt><dd>{recorded(member.member_id)}</dd></div>
        <div><dt>Relationship</dt><dd>{recorded(member.relation || member.relationship_id)}</dd></div>
        <div><dt>Birth date</dt><dd>{recorded(member.birth_date)}</dd></div>
        <div><dt>Gender</dt><dd>{recorded(member.gender || member.sex || member.gender_id)}</dd></div>
        <div><dt>Civil status</dt><dd>{recorded(member.civil_status || member.civil_status_id)}</dd></div>
        <div><dt>Occupation</dt><dd>{recorded(member.occupation)}</dd></div>
        <div><dt>Education</dt><dd>{recorded(member.education_level || member.education_level_id)}</dd></div>
        <div><dt>Contact</dt><dd>{recorded(member.contact_number)}</dd></div>
        <div><dt>Religion</dt><dd>{recorded(member.religion)}</dd></div>
        <div><dt>Special needs</dt><dd>{recorded(member.special_needs)}</dd></div>
        <div><dt>PWD / Senior / Pregnant</dt><dd>{[member.is_pwd && 'PWD', member.is_senior && 'Senior', member.is_pregnant && 'Pregnant'].filter(Boolean).join(', ') || 'None recorded'}</dd></div>
        <div><dt>Can report for household</dt><dd>{recorded(member.can_report_for_household)}</dd></div>
      </dl>
    </article>)}
  </div>
}

function AccountDetails({ accounts }) {
  if (!accounts.length) return <p>No active account is linked by household ID.</p>

  return <div className="household-directory-records">
    {accounts.map(account => <article className="household-directory-record" key={account.user_id}>
      <h4>{fullName(account)} <small>· {activeLabel(account.is_active)}</small></h4>
      <dl>
        <div><dt>User ID</dt><dd>{recorded(account.user_id)}</dd></div>
        <div><dt>Username</dt><dd>{recorded(account.username)}</dd></div>
        <div><dt>Household ID</dt><dd>{recorded(account.household_id)}</dd></div>
        <div><dt>Member ID</dt><dd>{recorded(account.member_id)}</dd></div>
        <div><dt>Member link</dt><dd>{account.member_link_status === 'matched' ? 'Matched' : account.member_link_status === 'unmatched' ? 'Member ID does not belong to this household' : 'No member assigned'}</dd></div>
        <div><dt>Role ID</dt><dd>{recorded(account.role_id)}</dd></div>
        <div><dt>Email</dt><dd>{recorded(account.email)}</dd></div>
        <div><dt>Contact</dt><dd>{recorded(account.contact_number)}</dd></div>
      </dl>
    </article>)}
  </div>
}

function HouseholdRecord({ item }) {
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
      <details className="household-directory-details">
        <summary>View household, address, members, and accounts</summary>
        <div className="household-directory-detail-grid">
          <section>
            <h3>Household details</h3>
            <dl>
              <div><dt>Household ID</dt><dd>{recorded(household.household_id)}</dd></div>
              <div><dt>Code / number</dt><dd>{recorded(household.household_code || household.household_number)}</dd></div>
              <div><dt>Email</dt><dd>{recorded(household.email)}</dd></div>
              <div><dt>Contact</dt><dd>{recorded(household.contact_number)}</dd></div>
              <div><dt>Emergency contact</dt><dd>{recorded(household.emergency_contact)}</dd></div>
              <div><dt>Recorded member count</dt><dd>{recorded(household.member_count)}</dd></div>
              <div><dt>Created by</dt><dd>{recorded(household.created_by)}</dd></div>
            </dl>
          </section>
          <section>
            <h3>Address and registered area</h3>
            <dl>
              <div><dt>Address ID</dt><dd>{recorded(address?.address_id)}</dd></div>
              <div><dt>Address</dt><dd>{addressLabel(address)}</dd></div>
              <div><dt>Site / barangay</dt><dd>{recorded(address?.barangay_name || address?.barangay_id)}</dd></div>
              <div><dt>Sitio</dt><dd>{recorded(address?.sitio_name || address?.registered_sitio_id || address?.sitio_id)}</dd></div>
              <div><dt>Purok</dt><dd>{recorded(address?.purok_name || address?.purok_id)}</dd></div>
              <div><dt>Legacy area text</dt><dd>{recorded(address?.purok_sitio)}</dd></div>
              <div><dt>Geographic assignment</dt><dd>{status}</dd></div>
              <div><dt>ZIP code</dt><dd>{recorded(address?.zip_code)}</dd></div>
            </dl>
          </section>
          <section>
            <h3>Household members ({item.member_count})</h3>
            <MemberDetails members={item.members} />
          </section>
          <section>
            <h3>Household accounts ({item.account_count})</h3>
            <AccountDetails accounts={item.accounts} />
          </section>
        </div>
      </details>
    </td>
    <td>{area || 'Area not assigned'}<small>{status}</small></td>
    <td>{addressLabel(address)}</td>
    <td>{item.member_count}<small>{household.member_count === null ? 'Recorded total unavailable' : `Recorded: ${household.member_count}`}</small></td>
    <td>{item.account_count}</td>
  </tr>
}

export default function HouseholdDataDirectoryPage() {
  const [page, setPage] = useState(1)
  const [syncing, setSyncing] = useState(false)
  const [syncNotice, setSyncNotice] = useState('')
  const loader = useCallback(() => getHouseholdDirectory({
    page,
    per_page: perPage,
  }), [page])
  const { data, error, loading, refresh } = useModuleData(loader, ['households', 'accounts'])
  const households = data?.households
  const summary = data?.summary

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

  return <section className="hh-prototype" aria-label="Household data directory">
    <div className="hd">
      <Link className="bk" to="/households" title="Back to household status" aria-label="Back to household status">‹</Link>
      <b className="ttl">Household data directory</b>
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
      <div className="tw">
        <table aria-busy={loading}>
          <thead><tr><th>Household</th><th>Sitio / purok</th><th>Address</th><th>Members</th><th>Accounts</th></tr></thead>
          <tbody>
            {loading && !households
              ? Array.from({ length: 5 }, (_, rowIndex) => <tr key={`skeleton-${rowIndex}`}>
                {Array.from({ length: 5 }, (_, columnIndex) => <td key={columnIndex}>
                  <span className="household-directory-skeleton" aria-hidden="true" />
                  {rowIndex === 0 && columnIndex === 0 && <span className="household-directory-sr-only">Loading household records…</span>}
                </td>)}
              </tr>)
              : households?.data?.length
                ? households.data.map(item => <HouseholdRecord key={item.household.household_id} item={item} />)
                : <tr><td className="household-directory-empty" colSpan={5}>{error ? 'Household records could not be loaded.' : 'There are no household records at the moment.'}</td></tr>}
          </tbody>
        </table>
      </div>
      <HouseholdPagination meta={households?.meta} loading={loading} onPageChange={setPage} />
    </div>
  </section>
}
