import { useEffect, useState } from 'react'
import { ArrowLeft, ChevronDown, ChevronUp } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { getHousehold, getHouseholdStatusLogs } from '../api/householdApi'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import { StatusBadge } from '../components/households/HouseholdTable'

const HISTORY_PAGE_SIZE = 5

export default function HouseholdReviewPage() {
  const navigate = useNavigate()
  const { householdId } = useParams()
  const [detail, setDetail] = useState(null)
  const [history, setHistory] = useState([])
  const [historyPage, setHistoryPage] = useState(1)
  const [historyExpanded, setHistoryExpanded] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const totalHistoryPages = Math.max(1, Math.ceil(history.length / HISTORY_PAGE_SIZE))
  const currentHistoryPage = Math.min(historyPage, totalHistoryPages)
  const historyStart = (currentHistoryPage - 1) * HISTORY_PAGE_SIZE
  const paginatedHistory = history.slice(historyStart, historyStart + HISTORY_PAGE_SIZE)

  useEffect(() => {
    let ignore = false

    async function loadDetail() {
      setIsLoading(true)
      setError('')

      try {
        const [detailData, historyData] = await Promise.all([
          getHousehold(householdId),
          getHouseholdStatusLogs(householdId),
        ])

        if (!ignore) {
          setDetail(detailData)
          setHistory(historyData.logs || [])
          setHistoryPage(1)
        }
      } catch {
        if (!ignore) {
          setError('Household details cannot be loaded right now.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    if (householdId) {
      loadDetail()
    }

    return () => {
      ignore = true
    }
  }, [householdId])

  return (
    <main className="household-review-page">
      <PageHeader
        title={detail?.household?.household_name || 'Household record'}
        subtitle={detail?.household?.purok || 'Purok not assigned'}
        actions={(
          <>
            <span className={`review-header-status status-badge ${detail?.household?.status?.key || 'unchecked'}`}>
              {detail?.household?.status?.label || 'Status not reported'}
            </span>
            <button type="button" className="button secondary compact-button" onClick={() => navigate('/households')}>
              <ArrowLeft size={15} /> Back to households
            </button>
          </>
        )}
      />

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && detail && (
        <>
          <div className="hh-detail-group">
            <div className="hh-section-label">
              <span>Household profile</span>
              <span>{detail.household?.household_id || 'ID not recorded'}</span>
            </div>
            <div className="hh-household-facts">
              <Fact label="Household code" value={detail.household?.household_code || detail.household?.household_id} />
              <Fact label="Account holder" value={detail.household?.account_holder} />
              <Fact label="Account ID" value={detail.household?.account_id} />
              <Fact label="Contact number" value={detail.household?.contact_number} />
              <Fact label="Address" value={detail.household?.address} />
              <Fact label="Purok" value={detail.household?.purok} />
              <Fact label="Reported by" value={detail.household?.source?.submitted_by} />
              <Fact label="Report source" value={detail.household?.source?.label} />
              <Fact label="Last report" value={detail.household?.source?.datetime} />
              <Fact label="Last location" value={detail.household?.location?.label} />
              <Fact label="Coordinates" value={coordinateLabel(detail.household?.location)} />
            </div>
            <div className="hh-detail-metrics">
              {(detail.household?.detail_tiles || []).map((tile) => (
                <div className="hh-detail-metric" key={tile.label}>
                  <span>{tile.label}</span>
                  <strong>{tile.value}</strong>
                </div>
              ))}
            </div>
          </div>

          <div className="hh-detail-group">
            <div className="hh-section-label">
              <span>Registered devices</span>
              <span>{detail.devices?.length || 0} devices</span>
            </div>
            <div className="hh-detail-table-wrap">
              <table className="hh-detail-table compact-detail-table">
                <thead>
                  <tr>
                    <th>Device</th>
                    <th>Member</th>
                    <th>Platform / role</th>
                    <th>Battery</th>
                    <th>Signal</th>
                    <th>Last location</th>
                    <th>Location permission</th>
                    <th>Last seen</th>
                    <th>State</th>
                  </tr>
                </thead>
                <tbody>
                  {!detail.devices?.length ? (
                    <EmptyTableRow colSpan={9} text="No device records are linked to this household." />
                  ) : detail.devices.map((device) => (
                    <tr key={device.id || device.device_uuid}>
                      <td>{device.device_name || 'Unnamed device'}</td>
                      <td>{device.member_name || 'Household user'}</td>
                      <td>{[device.platform, device.app_role].filter(Boolean).join(' / ') || 'Not recorded'}</td>
                      <td>{formatPercent(device.battery_level)}</td>
                      <td>{formatPercent(device.signal_strength)}</td>
                      <td>{device.last_location_label || 'No location yet'}</td>
                      <td>{device.location_permission_status || 'Unknown'}</td>
                      <td>{device.last_seen_at || 'Not recorded'}</td>
                      <td>{device.is_active ? 'Active' : 'Inactive'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="hh-detail-group">
            <div className="hh-section-label">
              <span>Family members</span>
              <span>{detail.members?.length || 0} members</span>
            </div>
            <div className="hh-detail-table-wrap">
              <table className="hh-detail-table compact-detail-table">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Member ID</th>
                    <th>Role</th>
                    <th>Gender</th>
                    <th>Head</th>
                    <th>Vulnerable Indicators</th>
                    <th>Last location</th>
                    <th>Device</th>
                    <th>Battery</th>
                    <th>Status reported</th>
                  </tr>
                </thead>
                <tbody>
                  {(!detail.members || detail.members.length === 0) ? (
                    <EmptyTableRow colSpan={10} text="No household members are synced yet." />
                  ) : (
                    detail.members.map((member) => (
                      <tr key={member.member_id || member.name}>
                        <td>
                          <div className="hh-household-name">{member.name}</div>
                          <div className="hh-household-meta">{member.age ? `${member.age} yrs` : 'Age not recorded'}</div>
                        </td>
                        <td>{member.member_id}</td>
                        <td>{member.relation}</td>
                        <td>{member.gender}</td>
                        <td>{member.is_household_head ? 'Yes' : 'No'}</td>
                        <td>{getVulnerableIndicators(member)}</td>
                        <td>{member.last_location_label || 'No location yet'}</td>
                        <td>{member.device_name || 'No assigned mobile'}{member.device_platform ? ` (${member.device_platform})` : ''}</td>
                        <td>{formatPercent(member.battery_level)}</td>
                        <td>{member.status?.label || 'No report'}{member.status_updated_at ? <div className="hh-household-meta">{member.status_updated_at}</div> : null}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div className="hh-detail-group hh-history-collapsible">
            <button
              type="button"
              className="hh-history-toggle"
              onClick={() => setHistoryExpanded((current) => !current)}
              aria-expanded={historyExpanded}
            >
              <div className="hh-section-label no-margin">
                <span>Status history</span>
                <span>Active event</span>
              </div>
              <span className="hh-history-toggle-icon">
                {historyExpanded ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
              </span>
            </button>

            {historyExpanded && (
              <div className="hh-detail-table-wrap">
                <table className="hh-detail-table compact-detail-table">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Status</th>
                      <th>Submitted by</th>
                      <th>Source</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedHistory.length === 0 ? (
                      <EmptyTableRow colSpan={4} text="No status history for this active event yet." />
                    ) : (
                      paginatedHistory.map((log) => (
                        <tr key={log.status_log_id}>
                          <td>{log.submitted_at || 'No time'}</td>
                          <td><StatusBadge status={log.status} /></td>
                          <td>{log.submitted_by}</td>
                          <td>{log.source}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>

                {history.length > 0 && (
                  <div className="hh-history-pagination">
                    <button
                      type="button"
                      className="button secondary compact-button"
                      onClick={() => setHistoryPage((page) => Math.max(1, page - 1))}
                      disabled={currentHistoryPage === 1}
                    >
                      Prev
                    </button>
                    <span className="hh-history-page-indicator">Page {currentHistoryPage} of {totalHistoryPages}</span>
                    <button
                      type="button"
                      className="button secondary compact-button"
                      onClick={() => setHistoryPage((page) => Math.min(totalHistoryPages, page + 1))}
                      disabled={currentHistoryPage >= totalHistoryPages}
                    >
                      Next
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        </>
      )}
    </main>
  )
}

function getVulnerableIndicators(member) {
  const flags = []

  if (member.is_pwd) flags.push('PWD')
  if (member.is_senior) flags.push('Senior')
  if (member.is_pregnant) flags.push('Pregnant')

  return flags.length ? flags.join(' • ') : 'None'
}

function formatPercent(value) {
  return value === null || value === undefined || value === '' ? 'Not recorded' : `${value}%`
}

function coordinateLabel(location) {
  if (location?.latitude === null || location?.latitude === undefined
    || location?.longitude === null || location?.longitude === undefined) {
    return 'Not recorded'
  }

  return `${location.latitude}, ${location.longitude}`
}

function Fact({ label, value }) {
  return (
    <div className="hh-household-fact">
      <span>{label}</span>
      <strong>{value || 'Not recorded'}</strong>
    </div>
  )
}

function EmptyTableRow({ colSpan, text }) {
  return (
    <tr>
      <td colSpan={colSpan} className="hh-empty-cell">{text}</td>
    </tr>
  )
}
