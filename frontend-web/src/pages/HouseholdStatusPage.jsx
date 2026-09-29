import { useEffect, useState } from 'react'
import {
  Download,
  FileDown,
  RefreshCcw,
} from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getHouseholds } from '../api/householdApi'
import HouseholdOpsPanels from '../components/households/HouseholdOpsPanels'
import HouseholdSummary from '../components/households/HouseholdSummary'
import HouseholdTable from '../components/households/HouseholdTable'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
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
  const [page, setPage] = useState(1)

  const summary = payload?.summary || emptySummary()
  const households = payload?.households?.data || []
  const meta = payload?.households?.meta || {}
  const hasActiveEvent = Boolean(payload?.active_event)
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getHouseholds({
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
  }, [page])

  async function loadHouseholds() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getHouseholds({
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
      <PageHeader
        title="Household Status"
        subtitle="Barangay Mambaling, Cebu City"
      />

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <div className="workspace-grid">
          <section className="household-workspace" aria-label="Household rescue list">
            <div className="data-panel">
              <RefreshOverlay active={isRefreshing}>
                <HouseholdTable
                  households={households}
                  meta={meta}
                  selectedPurok="all"
                  onOpen={openHousehold}
                  onPageChange={setPage}
                  onDispatchPurok={() => navigate('/dispatch')}
                  actions={(
                    <>
                      <button className="button secondary" type="button" onClick={loadHouseholds}>
                        <RefreshCcw size={14} /> Refresh
                      </button>
                      <button className="button secondary" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('excel')}>
                        <Download size={14} /> Export
                      </button>
                      <button className="button secondary" type="button" disabled={households.length === 0} onClick={() => exportCurrentPage('pdf')}>
                        <FileDown size={14} /> PDF
                      </button>
                    </>
                  )}
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
