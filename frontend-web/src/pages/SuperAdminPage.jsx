import { Inbox, RefreshCcw } from 'lucide-react'
import { useEffect, useState } from 'react'
import { getInquiries, updateInquiryStatus } from '../api/inquiryApi'
import Badge from '../components/ui/Badge'
import EmptyState from '../components/ui/EmptyState'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import PaginationBar from '../components/ui/PaginationBar'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import { pageDataError } from '../utils/pageShell'

const statuses = [
  { key: 'all', label: 'All' },
  { key: 'new', label: 'New' },
  { key: 'in_review', label: 'In review' },
  { key: 'responded', label: 'Responded' },
  { key: 'closed', label: 'Closed' },
]

export default function SuperAdminPage() {
  const [payload, setPayload] = useState(null)
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    let ignore = false

    async function load() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getInquiries({ status, page, per_page: 10 })
        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Inquiries cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    load()

    return () => {
      ignore = true
    }
  }, [status, page])

  async function refresh() {
    setIsLoading(true)
    setError('')

    try {
      setPayload(await getInquiries({ status, page, per_page: 10 }))
    } catch {
      setError('Inquiries cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  async function changeStatus(inquiry, nextStatus) {
    setMessage('')
    setError('')

    try {
      await updateInquiryStatus(inquiry.inquiry_id, nextStatus)
      setMessage('Inquiry status updated.')
      await refresh()
    } catch {
      setError('Inquiry status cannot be updated right now.')
    }
  }

  const inquiries = payload?.inquiries?.data || []
  const pagination = payload?.inquiries || {}
  const summary = payload?.summary || {}
  const accounts = payload?.accounts || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const dataError = pageDataError(error, Boolean(payload))

  return (
    <section className="page active super-admin-page">
      <PageHeader
        title="Super Admin"
        actions={
          <button className="btn btn-secondary btn-sm" type="button" onClick={refresh}>
            <RefreshCcw size={14} />
            Refresh
          </button>
        }
      />

      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}
      {isInitialLoading ? <LoadingState /> : null}
      {message && <div className="rr-message">{message}</div>}

      <div className="super-stat-row">
            <SummaryCard label="New" value={summary.new || 0} />
            <SummaryCard label="In review" value={summary.in_review || 0} />
            <SummaryCard label="Responded" value={summary.responded || 0} />
            <SummaryCard label="Closed" value={summary.closed || 0} />
          </div>

          <div className="super-account-panel">
            <div>
              <strong>Account access</strong>
              <span>{accounts.summary?.hq_web || 0} HQ web account(s) - {accounts.summary?.rescuer_mobile || 0} rescuer mobile account(s)</span>
            </div>
            <div className="super-account-list">
              {(accounts.latest || []).slice(0, 6).map((account) => (
                <span key={account.user_id}>
                  {account.name}
                  <small>{account.role_key}</small>
                </span>
              ))}
            </div>
          </div>

          <div className="super-toolbar">
            <div className="super-toolbar-title">
              <Inbox size={16} />
              Landing page inquiries
            </div>
            <select value={status} onChange={(event) => {
              setPage(1)
              setStatus(event.target.value)
            }}>
              {statuses.map((item) => <option value={item.key} key={item.key}>{item.label}</option>)}
            </select>
          </div>

          {!payload?.table_ready && payload ? (
            <div className="form-error">{payload.message || 'landing_inquiries table is not available yet.'}</div>
          ) : null}

          <RefreshOverlay active={isRefreshing}>
            <div className="super-panel">
              {inquiries.length === 0 ? (
                <EmptyState title="No inquiries found" message="Landing page inquiries will appear here after visitors submit the contact form." />
              ) : (
                <div className="super-inquiry-list">
                  {inquiries.map((inquiry) => (
                    <article className="super-inquiry-card" key={inquiry.inquiry_id}>
                      <div>
                        <div className="super-inquiry-head">
                          <strong>{inquiry.name}</strong>
                          <Badge tone={statusTone(inquiry.status)}>{statusLabel(inquiry.status)}</Badge>
                        </div>
                        <div className="rr-meta">
                          {[inquiry.organization, inquiry.email, inquiry.created_at].filter(Boolean).join(' - ')}
                        </div>
                        <p>{inquiry.message}</p>
                      </div>
                      <div className="super-inquiry-actions">
                        <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'in_review')}>Review</button>
                        <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'responded')}>Mark responded</button>
                        <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'closed')}>Close</button>
                      </div>
                    </article>
                  ))}
                </div>
              )}
            </div>
          </RefreshOverlay>

          <PaginationBar meta={pagination} onPageChange={setPage} label="inquiries" />
    </section>
  )
}

function SummaryCard({ label, value }) {
  return (
    <div className="super-stat-card">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}

function statusLabel(status) {
  return String(status || 'new').replace('_', ' ')
}

function statusTone(status) {
  return {
    new: 'amber',
    in_review: 'blue',
    responded: 'green',
    closed: 'gray',
  }[status] || 'gray'
}
