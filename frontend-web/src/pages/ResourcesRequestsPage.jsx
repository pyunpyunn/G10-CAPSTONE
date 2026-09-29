import { PackageCheck } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getResourceRequests } from '../api/resourceRequestApi'
import ResourceRequestQueueTable from '../components/resources/ResourceRequestQueueTable'
import ResourceRequestStats from '../components/resources/ResourceRequestStats'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import { filterParams, resourceRequestErrorMessage } from '../utils/resourceRequestHelpers'

export default function ResourcesRequestsPage() {
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [queuePage, setQueuePage] = useState(1)
  const [summaryPeriod, setSummaryPeriod] = useState('week')

  useEffect(() => {
    let ignore = false

    async function loadInitialRequests() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage, summaryPeriod))

        if (!ignore) {
          setPayload(data)
        }
      } catch (loadError) {
        if (!ignore) {
          setError(resourceRequestErrorMessage(loadError, 'Resource requests could not be loaded. Please sign in again or check the Laravel API.'))
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialRequests()

    return () => {
      ignore = true
    }
  }, [queuePage, summaryPeriod])

  async function loadRequests(showMessage = '') {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage, summaryPeriod))
      setPayload(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch (loadError) {
      setError(resourceRequestErrorMessage(loadError, 'Resource requests could not be loaded. Please sign in again or check the Laravel API.'))
    } finally {
      setIsLoading(false)
    }
  }

  const requests = payload?.requests?.data || []
  const pagination = payload?.requests || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

  function openCreateModal() {
    navigate('/resources-requests/new')
  }

  function openEditPage(request) {
    navigate(`/resources-requests/${request.request_id}/edit`)
  }

  async function openExistingModal(request, mode, initialError = '') {
    navigate(`/resources-requests/${request.request_id}/${mode}`, { state: { initialError } })
  }

  async function handleSyncEvaTrack() {
    await loadRequests('Latest shared DB requests loaded. EvaTrack requests will appear here after they are saved in the shared database.')
  }

  return (
    <section className="page active resources-page">
      <PageHeader
        title="Resources & Requests"
        subtitle="Barangay Mambaling, Cebu City"
        actions={<button className="button review" type="button" onClick={openCreateModal}><PackageCheck size={16} />New request</button>}
      />

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!hasBlockingError && (payload || isLoading) && (
        <>
          {message && <div className="rr-message">{message}</div>}

          <div className="rr-layout">
            <div className="rr-main-column">
              <RefreshOverlay active={isRefreshing}>
                <ResourceRequestQueueTable
                  requests={requests}
                  pagination={pagination}
                  loading={isLoading}
                  onView={(request) => openExistingModal(request, 'view')}
                  onEdit={openEditPage}
                  onPageChange={setQueuePage}
                  onSync={handleSyncEvaTrack}
                />
              </RefreshOverlay>
              <TrackingAidMirror items={payload?.tracking_mirror || []} />
            </div>
            <aside className="rr-side-column">
              <ResourceRequestStats summary={payload?.summary} period={summaryPeriod} onPeriodChange={(value) => { setSummaryPeriod(value); setQueuePage(1) }} />
            </aside>
          </div>
        </>
      )}
    </section>
  )
}
