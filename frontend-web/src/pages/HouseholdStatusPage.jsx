import { useEffect, useState } from 'react'
import {
  Download,
  FileDown,
  RefreshCcw,
} from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getHouseholds } from '../api/householdApi'
import HouseholdFilters from '../components/households/HouseholdFilters'
import HouseholdOpsPanels from '../components/households/HouseholdOpsPanels'
import HouseholdSummary from '../components/households/HouseholdSummary'
import HouseholdTable from '../components/households/HouseholdTable'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  emptySummary,
} from '../utils/householdStatusHelpers'
import {
  downloadExcelWorkbook,
  downloadPdfReport,
} from '../utils/exportFileHelpers'

const PAGE_SIZE = 25

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

  const summary = payload?.summary || emptySummary()
  const households = payload?.households?.data || []
  const meta = payload?.households?.meta || {}
  const puroks = payload?.filters?.puroks || []
  const hasActiveEvent = Boolean(payload?.active_event)
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

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
          per_page: PAGE_SIZE,
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
        per_page: PAGE_SIZE,
      })
      setPayload(data)
    } catch {
      setError('Household status records cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function changeStatusFilter(nextStatus) {
    setStatus(nextStatus)
    setPage(1)
  }

  function changePurok(nextPurok) {
    setPurok(nextPurok)
    setPage(1)
  }

  function openHousehold(householdId) {
    navigate(`/households/${householdId}`)
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
    <main className="ops-page household-page">
      <header className="household-status-page-header">
        <div className="household-status-header-copy">
          <h1>Household Status</h1>
          <p>Barangay Mambaling, Cebu City</p>
        </div>
        <div className="weather-page-actions household-status-page-actions">
          <div className="weather-live-status">
            <strong>{hasActiveEvent ? 'Live' : 'Standby'}</strong>
            <span>{hasActiveEvent ? 'Monitoring active event' : 'Waiting for active event'}</span>
          </div>
          <button className="button secondary" type="button" onClick={loadHouseholds}>
            <RefreshCcw size={16} />
            Refresh
          </button>
          <button className="button secondary" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('excel')}>
            <Download size={16} />
            Export
          </button>
          <button className="button secondary" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('pdf')}>
            <FileDown size={16} />
            PDF
          </button>
        </div>
      </header>

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <div className="workspace-grid">
          <section className="household-workspace" aria-label="Household rescue list">
            <div className="filter-bar household-filter-shell">
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
            </div>

            <div className="data-panel">
              <RefreshOverlay active={isRefreshing}>
                <HouseholdTable
                  households={households}
                  meta={meta}
                  selectedPurok={purok}
                  onOpen={openHousehold}
                  onPageChange={setPage}
                  onDispatchPurok={() => navigate('/dispatch')}
                />
              </RefreshOverlay>
            </div>
          </section>

          <aside className="side-panel" aria-label="Household operations summary">
            {!hasActiveEvent && (
              <div className="standby-strip hh-standby-strip">
                <strong>No active disaster event</strong>
                <span>Household reporting starts after HQ/Admin broadcasts an active event.</span>
              </div>
            )}
            <HouseholdSummary summary={summary} />
            <HouseholdOpsPanels rows={payload?.purok_summary || []} />
          </aside>
        </div>
      )}
    </main>
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
