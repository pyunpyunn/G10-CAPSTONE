import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useWorkspaceQuery } from '../state/operationsApi'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'
import { ResponseOperationsLayout } from '../components/ui/PageHeader'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const { data, error: queryError, isLoading: loading, refetch } = useWorkspaceQuery({ view: 'overview' })
  const error = getDispatchErrorMessage(queryError)
  useEffect(() => { if (location.state?.selectedHousehold) navigate('/rescue-management/new', { replace: true, state: location.state }) }, [location.state, navigate])
  return <ResponseOperationsLayout><section className="pg on">
    {error && <div className="form-error" role="alert">{error}<button className="bt" type="button" onClick={refetch}>Retry</button></div>}
    {loading && <div className="empty-reference" role="status">Loading communications...</div>}
    {!loading && data && <>
      {!data.active_event && <div className="nb">No active event · dispatch disabled</div>}
      <div className="dispatch-communication-only"><FieldCommunicationPanel compact recordingsOnly /></div>
      <DispatchSidePanel lazy />
    </>}
  </section></ResponseOperationsLayout>
}

function getDispatchErrorMessage(error) {
  if (!error) return ''

  if (error.status === 'NETWORK_ERROR') return 'Cannot connect to the server. Check your connection and try again.'
  if (error.status === 401) return 'Your session has expired. Sign in again to continue.'
  if (error.status === 403) return 'You do not have permission to view dispatch information.'
  if (error.status === 404) return 'Dispatch information was not found. Refresh and try again.'
  if (error.status === 'INVALID_RESPONSE') return error.message
  if (typeof error.status === 'number' && error.status >= 500) {
    return 'Dispatch information could not be loaded because of a server error. Please try again shortly.'
  }

  return 'Dispatch information could not be loaded. Please try again.'
}
