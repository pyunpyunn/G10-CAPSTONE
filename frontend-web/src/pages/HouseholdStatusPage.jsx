import { useEffect, useState } from 'react'
import {
  FileDown,
  MapPin,
  RefreshCcw,
  Route,
  ShieldCheck,
  Users,
} from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getHousehold, getHouseholdStatusLogs, getHouseholds } from '../api/householdApi'
import HouseholdDetailContent from '../components/households/HouseholdDetailContent'
import HouseholdFilters from '../components/households/HouseholdFilters'
import HouseholdOpsPanels from '../components/households/HouseholdOpsPanels'
import HouseholdSummary from '../components/households/HouseholdSummary'
import HouseholdTable from '../components/households/HouseholdTable'
import LoadingState from '../components/ui/LoadingState'
import Modal from '../components/ui/Modal'
import PageHeader from '../components/ui/PageHeader'
import {
  csvValue,
  emptySummary,
} from '../utils/householdStatusHelpers'

export default function HouseholdStatusPage() {
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [selectedId, setSelectedId] = useState('')
  const [detail, setDetail] = useState(null)
  const [history, setHistory] = useState([])
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailError, setDetailError] = useState('')

  const summary = payload?.summary || emptySummary()
  const households = payload?.households?.data || []
  const meta = payload?.households?.meta || {}
  const puroks = payload?.filters?.puroks || []
  const hasActiveEvent = Boolean(payload?.active_event)

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setSearch(searchText.trim())
      setPage(1)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [searchText])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getHouseholds({
          search,
          purok,
          status,
          page,
          per_page: 20,
        })

        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Household status records cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadPage()

    return () => {
      ignore = true
    }
  }, [search, purok, status, page])

  async function loadHouseholds() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getHouseholds({
        search,
        purok,
        status,
        page,
        per_page: 20,
      })
      setPayload(data)
    } catch {
      setError('Household status records cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  async function openHousehold(householdId) {
    setSelectedId(householdId)
    setDetail(null)
    setHistory([])
    setDetailError('')
    setIsDetailLoading(true)

    try {
      const [detailData, historyData] = await Promise.all([
        getHousehold(householdId),
        getHouseholdStatusLogs(householdId),
      ])
      setDetail(detailData)
      setHistory(historyData.logs || [])
    } catch {
      setDetailError('Household details cannot be loaded right now.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  function closeDetail() {
    setSelectedId('')
    setDetail(null)
    setHistory([])
    setDetailError('')
  }

  function changeStatusFilter(nextStatus) {
    setStatus(nextStatus)
    setPage(1)
  }

  function changePurok(nextPurok) {
    setPurok(nextPurok)
    setPage(1)
  }

  function exportCurrentPage() {
    if (households.length === 0) {
      return
    }

    const headers = ['Household ID', 'Household', 'Purok', 'People', 'Status', 'Source', 'Report Time', 'Devices', 'Battery', 'Last Location', 'Priority']
    const rows = households.map((household) => [
      household.household_id,
      household.household_name,
      household.purok,
      household.people,
      household.status?.label,
      household.source?.label,
      household.source?.datetime,
      `${household.device?.active || 0}/${household.device?.total || 0}`,
      household.device?.lowest_battery !== null && household.device?.lowest_battery !== undefined ? `${household.device.lowest_battery}%` : '',
      household.location?.label,
      household.priority?.label,
    ])
    const csv = [headers, ...rows]
      .map((row) => row.map(csvValue).join(','))
      .join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = `household-status-page-${page}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 0)
  }

  return (
    <section className="page active household-page">
      <PageHeader
        title="Household Status"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadHouseholds}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            <button className="btn btn-secondary btn-sm" type="button" disabled={households.length === 0} onClick={exportCurrentPage}>
              <FileDown size={14} />
              Export CSV
            </button>
          </>
        }
      />

      <div className="hh-readonly-banner">
        <span>
          <ShieldCheck size={15} />
          HQ reviews reports only. Status is updated by household mobile reports or authenticated responder field reports.
        </span>
        <span>Latest row per household - History on open</span>
      </div>

      {isLoading && <LoadingState message="Loading household status..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && (
        <>
          {!hasActiveEvent && (
            <div className="standby-strip hh-standby-strip">
              <strong>No active disaster event</strong>
              <span>Household reporting starts after HQ/Admin broadcasts an active event.</span>
            </div>
          )}

          <HouseholdSummary summary={summary} />
          <HouseholdFilters
            searchText={searchText}
            purok={purok}
            status={status}
            summary={summary}
            puroks={puroks}
            onSearchTextChange={setSearchText}
            onPurokChange={changePurok}
            onStatusChange={changeStatusFilter}
          />

          <HouseholdTable households={households} meta={meta} onOpen={openHousehold} onPageChange={setPage} />
          <HouseholdOpsPanels activities={payload?.recent_activity || []} rows={payload?.purok_summary || []} />
        </>
      )}

      <Modal
        title={detail?.household?.household_name || 'Household details'}
        isOpen={Boolean(selectedId)}
        onClose={closeDetail}
        className="hh-drawer-modal"
        footer={
          detail?.household && (
            <>
              <button className="btn btn-secondary btn-sm" type="button" onClick={() => navigate('/mapping')}>
                <MapPin size={14} />
                Open map
              </button>
              <button className="btn btn-secondary btn-sm" type="button" onClick={() => navigate('/dispatch')}>
                <Users size={14} />
                Request check
              </button>
              {detail.household.priority?.key === 'urgent' && (
                <button className="btn btn-danger btn-sm" type="button" onClick={() => navigate('/dispatch')}>
                  <Route size={14} />
                  Create dispatch
                </button>
              )}
            </>
          )
        }
      >
        {isDetailLoading && <LoadingState message="Loading household details..." />}
        {detailError && <div className="form-error">{detailError}</div>}
        {!isDetailLoading && detail?.household && (
          <HouseholdDetailContent detail={detail} history={history} />
        )}
      </Modal>
    </section>
  )
}
