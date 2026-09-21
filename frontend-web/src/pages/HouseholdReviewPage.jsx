import { useEffect, useState } from 'react'
import { ArrowLeft, ChevronDown, ChevronUp, ShieldCheck } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { getHousehold, getHouseholdStatusLogs } from '../api/householdApi'
import LoadingState from '../components/ui/LoadingState'
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
  const historyStart = (historyPage - 1) * HISTORY_PAGE_SIZE
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

  useEffect(() => {
    if (historyPage > totalHistoryPages) {
      setHistoryPage(totalHistoryPages)
    }
  }, [historyPage, totalHistoryPages])

  return (
    <main className="household-review-page">
      <div className="household-review-topbar">
        <div className="household-review-summary">
          <div className="summary-primary-row">
            <div className="review-meta-inline headline-inline">
              <div className="review-header-title-wrap">
                <span className="review-header-kicker">Household</span>
                <h1 className="review-header-title">{detail?.household?.household_name || 'Household record'}</h1>
                <div className="review-header-subtitle">{detail?.household?.purok || 'Purok not assigned'}</div>
              </div>
              <span className={`review-header-status status-badge ${detail?.household?.status?.key || 'unchecked'}`}>
                {detail?.household?.status?.label || 'Status not reported'}
              </span>
            </div>
            <button type="button" className="button secondary compact-button" onClick={() => navigate('/households')}>
              <ArrowLeft size={15} />
              GO BACK TO
            </button>
          </div>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && detail && (
        <>
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
                    <th>Role</th>
                    <th>Gender</th>
                    <th>Head</th>
                    <th>Vulnerable Indicators</th>
                    <th>Last location</th>
                    <th>Devices connected</th>
                    <th>Low battery</th>
                    <th>Status reported</th>
                  </tr>
                </thead>
                <tbody>
                  {(!detail.members || detail.members.length === 0) ? (
                    <EmptyTableRow colSpan={9} text="No household members are synced yet." />
                  ) : (
                    detail.members.map((member) => (
                      <tr key={member.member_id || member.name}>
                        <td>
                          <div className="hh-household-name">{member.name}</div>
                          <div className="hh-household-meta">{member.age ? `${member.age} yrs` : 'Age not recorded'}</div>
                        </td>
                        <td>{member.relation}</td>
                        <td>{member.gender}</td>
                        <td>{member.is_household_head ? 'Yes' : 'No'}</td>
                        <td>{getVulnerableIndicators(member)}</td>
                        <td>{member.last_location_label || 'No location yet'}</td>
                        <td>{Boolean(member.device_name && member.device_name !== 'No assigned mobile') ? 'True' : 'False'}</td>
                        <td>{isLowBattery(member.battery_level) ? 'True' : 'False'}</td>
                        <td>{member.status?.label || 'No report'}</td>
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
                      disabled={historyPage === 1}
                    >
                      Prev
                    </button>
                    <span className="hh-history-page-indicator">Page {historyPage} of {totalHistoryPages}</span>
                    <button
                      type="button"
                      className="button secondary compact-button"
                      onClick={() => setHistoryPage((page) => Math.min(totalHistoryPages, page + 1))}
                      disabled={historyPage >= totalHistoryPages}
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

function isLowBattery(batteryLevel) {
  if (batteryLevel === null || batteryLevel === undefined || batteryLevel === '') {
    return false
  }

  const parsed = Number(batteryLevel)
  return Number.isFinite(parsed) && parsed <= 20
}

function DetailSection({ title, note, children }) {
  return (
    <>
      <div className="hh-section-label">
        <span>{title}</span>
        <span>{note}</span>
      </div>
      <div className="hh-detail-table-wrap">{children}</div>
    </>
  )
}

function EmptyTableRow({ colSpan, text }) {
  return (
    <tr>
      <td colSpan={colSpan} className="hh-empty-cell">{text}</td>
    </tr>
  )
}
