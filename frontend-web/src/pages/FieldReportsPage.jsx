import { RefreshCcw } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { getFieldReports } from '../api/fieldReportsApi'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import SummaryTable from '../components/ui/SummaryTable'
import { pageDataError } from '../utils/pageShell'
import { readQueryParam, setQueryParams } from '../utils/pageQuery'

function formatSubmittedAt(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-PH')
}

export default function FieldReportsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const statusFilter = readQueryParam(searchParams, 'status', 'all')
  const statusId = readQueryParam(searchParams, 'status_id')
  const eventId = readQueryParam(searchParams, 'event_id')

  const loadReports = useCallback(async (showMessage = '') => {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getFieldReports({
        status: statusFilter,
        status_id: statusId,
        event_id: eventId,
      })
      setPayload(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch {
      setError('Field reports cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }, [statusFilter, statusId, eventId])

  useEffect(() => {
    loadReports()
  }, [loadReports])

  useEffect(() => {
    const activeEventId = payload?.active_event?.event_id

    if (!activeEventId || eventId === activeEventId) {
      return
    }

    setQueryParams(setSearchParams, searchParams, { event_id: activeEventId }, { replace: true })
  }, [payload?.active_event?.event_id, eventId, searchParams, setSearchParams])

  function selectStatusRow(row) {
    const nextStatus = statusFilter === row.key ? 'all' : row.key

    setQueryParams(setSearchParams, searchParams, {
      status: nextStatus,
      status_id: nextStatus === 'all' ? null : row.status_id,
      event_id: eventId || payload?.active_event?.event_id || null,
    })
  }

  const reports = payload?.reports || []
  const summaryRows = payload?.summary?.rows || []
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const dataError = pageDataError(error, Boolean(payload))

  return (
    <section className="page active field-ops-page">
      <PageHeader
        title="Field Operations"
        actions={
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => loadReports('Latest field reports loaded.')}>
            <RefreshCcw size={14} />
            Refresh
          </button>
        }
      />

      {isInitialLoading ? <LoadingState label="Loading field operations..." /> : null}
      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}
      {message ? <div className="page-data-notice is-success">{message}</div> : null}

      <SummaryTable
        rows={summaryRows}
        activeKey={statusFilter}
        onSelectRow={selectStatusRow}
        showIdColumn={false}
      />

      <RefreshOverlay active={isRefreshing}>
        <div className="rr-panel">
          <div className="rr-panel-head">
            <span className="rr-title">Responder field reports</span>
            <span className="rr-subtle">{reports.length ? `${reports.length} record(s)` : 'No records yet'}</span>
          </div>
          <div className="rr-table-wrap">
            {reports.length === 0 ? (
              <p className="muted-copy fo-empty-copy">No responder field reports recorded for the selected filters yet.</p>
            ) : (
              <table className="rr-table data-table">
                <thead>
                  <tr>
                    <th>Household</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Submitted</th>
                  </tr>
                </thead>
                <tbody>
                  {reports.map((report) => (
                    <tr key={report.status_log_id || `${report.household_id}-${report.submitted_at}`}>
                      <td>
                        <strong>{report.household_head_name || report.household_code || report.household_id}</strong>
                        <div className="table-sub">{report.household_id}</div>
                      </td>
                      <td>{report.status_label || report.status_key || '—'}</td>
                      <td>{report.notes || '—'}</td>
                      <td>{formatSubmittedAt(report.submitted_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </RefreshOverlay>
    </section>
  )
}
