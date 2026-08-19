import { useEffect, useState } from 'react'
import {
  FileDown,
  MapPin,
  RefreshCcw,
  Route,
  Users,
} from 'lucide-react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { getHousehold, getHouseholdStatusLogs, getHouseholds, confirmHouseholdStatus } from '../api/householdApi'
import HouseholdDetailContent from '../components/households/HouseholdDetailContent'
import HouseholdFilters from '../components/households/HouseholdFilters'
import HouseholdOpsPanels from '../components/households/HouseholdOpsPanels'
import HouseholdSummary from '../components/households/HouseholdSummary'
import HouseholdTable from '../components/households/HouseholdTable'
import LoadingState from '../components/ui/LoadingState'
import Modal from '../components/ui/Modal'
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  emptySummary,
} from '../utils/householdStatusHelpers'
import { pageDataError } from '../utils/pageShell'
import { readQueryNumber, readQueryParam, setQueryParams } from '../utils/pageQuery'
import {
  downloadExcelWorkbook,
  downloadPdfReport,
} from '../utils/exportFileHelpers'

export default function HouseholdStatusPage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [searchText, setSearchText] = useState('')
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState(readQueryParam(searchParams, 'purok', 'all'))
  const [status, setStatus] = useState(readQueryParam(searchParams, 'status', 'all'))
  const [page, setPage] = useState(readQueryNumber(searchParams, 'page', 1))
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
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const dataError = pageDataError(error, Boolean(payload))

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
          per_page: 10,
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

  useEffect(() => {
    const activeEventId = payload?.active_event?.event_id

    if (!activeEventId || readQueryParam(searchParams, 'event_id') === activeEventId) {
      return
    }

    setQueryParams(setSearchParams, searchParams, { event_id: activeEventId }, { replace: true })
  }, [payload?.active_event?.event_id, searchParams, setSearchParams])

  async function loadHouseholds() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getHouseholds({
        search,
        purok,
        status,
        page,
        per_page: 10,
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
    setQueryParams(setSearchParams, searchParams, {
      household_id: householdId,
      event_id: payload?.active_event?.event_id || null,
    }, { replace: true })
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
    setQueryParams(setSearchParams, searchParams, { household_id: null }, { replace: true })
  }

  async function handleConfirmHousehold() {
    if (!selectedId) {
      return
    }

    try {
      await confirmHouseholdStatus(selectedId)
      await openHousehold(selectedId)
      await loadHouseholds()
    } catch {
      setDetailError('Household status could not be confirmed right now.')
    }
  }

  function changeStatusFilter(nextStatus) {
    setStatus(nextStatus)
    setPage(1)
    setQueryParams(setSearchParams, searchParams, {
      status: nextStatus,
      page: 1,
      event_id: payload?.active_event?.event_id || null,
    })
  }

  function changePurok(nextPurok) {
    setPurok(nextPurok)
    setPage(1)
    setQueryParams(setSearchParams, searchParams, {
      purok: nextPurok,
      page: 1,
      event_id: payload?.active_event?.event_id || null,
    })
  }

  function changePage(nextPage) {
    setPage(nextPage)
    setQueryParams(setSearchParams, searchParams, {
      page: nextPage,
      event_id: payload?.active_event?.event_id || null,
    })
  }

  function exportCurrentPage(type) {
    if (households.length === 0) {
      return
    }

    const rows = householdExportRows(households)
    const title = `Household Status - Page ${page}`

    if (type === 'pdf') {
      downloadPdfReport(`household-status-page-${page}.pdf`, title, rows)
      return
    }

    downloadExcelWorkbook(`household-status-page-${page}.xls`, title, rows)
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
            <button className="btn btn-secondary btn-sm" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('excel')}>
              <FileDown size={14} />
              Export Excel
            </button>
            <button className="btn btn-secondary btn-sm" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('pdf')}>
              <FileDown size={14} />
              Export PDF
            </button>
          </>
        }
      />

      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}
      {isInitialLoading ? <LoadingState label="Loading household status..." /> : null}

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

      <RefreshOverlay active={isRefreshing}>
        <HouseholdTable
          households={households}
          meta={meta}
          selectedPurok={purok}
          onOpen={openHousehold}
          onPageChange={changePage}
          onDispatchPurok={() => navigate('/dispatch')}
        />
      </RefreshOverlay>
      <HouseholdOpsPanels activities={payload?.recent_activity || []} rows={payload?.purok_summary || []} />

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
              <button className="btn btn-primary btn-sm" type="button" onClick={handleConfirmHousehold}>
                Confirm latest report
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
        {isDetailLoading && <LoadingState />}
        {detailError && <div className="form-error">{detailError}</div>}
        {!isDetailLoading && detail?.household && (
          <HouseholdDetailContent detail={detail} history={history} />
        )}
      </Modal>
    </section>
  )
}

function householdExportRows(households) {
  const headers = ['Household ID', 'Household', 'Purok', 'People', 'Status', 'Source', 'Report Time', 'Devices', 'Battery', 'Last Location']
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
  ])

  return [headers, ...rows]
}
