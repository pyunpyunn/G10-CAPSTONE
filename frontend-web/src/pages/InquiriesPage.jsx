import { Inbox, RefreshCcw, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { getInquiries, updateInquiryStatus } from '../api/inquiryApi'
import Badge from '../components/ui/Badge'
import EmptyState from '../components/ui/EmptyState'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import PaginationBar from '../components/ui/PaginationBar'
import RefreshOverlay from '../components/ui/RefreshOverlay'

const statuses = [
  { key: 'all', label: 'All' },
  { key: 'new', label: 'New' },
  { key: 'in_review', label: 'In review' },
  { key: 'responded', label: 'Responded' },
  { key: 'closed', label: 'Closed' },
]

export default function InquiriesPage() {
  const [payload, setPayload] = useState(null)
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
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
        const data = await getInquiries({ status, search, page, per_page: 10 })
        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Inquiries could not be loaded. The page stays available while the backend reconnects.')
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
  }, [status, search, page])

  async function refresh() {
    setIsLoading(true)
    setError('')

    try {
      setPayload(await getInquiries({ status, search, page, per_page: 10 }))
    } catch {
      setError('Inquiries could not be loaded. The page stays available while the backend reconnects.')
    } finally {
      setIsLoading(false)
    }
  }

  async function changeStatus(inquiry, nextStatus) {
    setMessage('')
    setError('')

    try {
      await updateInquiryStatus(inquiry.inquiry_id, nextStatus)
      setMessage('Status updated.')
      await refresh()
    } catch {
      setError('Status could not be updated right now.')
    }
  }

  const inquiries = payload?.inquiries?.data || []
  const pagination = payload?.inquiries || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)

  return (
    <section className="page active inquiries-page">
      <PageHeader
        title="Inquiries"
        actions={
          <button className="btn btn-secondary btn-sm" type="button" onClick={refresh}>
            <RefreshCcw size={14} />
            Refresh
          </button>
        }
      />

      {error ? <div className="page-data-notice is-error">{error}</div> : null}
      {message ? <div className="page-data-notice is-success">{message}</div> : null}

      <div className="inquiries-toolbar">
        <div className="inquiries-search">
          <Search size={16} />
          <input
            type="search"
            value={search}
            placeholder="Search name, email, or message"
            aria-label="Search inquiries"
            onChange={(event) => {
              setPage(1)
              setSearch(event.target.value)
            }}
          />
        </div>
        <div className="super-toolbar-title">
          <Inbox size={16} />
          Status
        </div>
        <select
          value={status}
          aria-label="Filter inquiry status"
          onChange={(event) => {
            setPage(1)
            setStatus(event.target.value)
          }}
        >
          {statuses.map((item) => <option value={item.key} key={item.key}>{item.label}</option>)}
        </select>
      </div>

      <RefreshOverlay active={isRefreshing}>
        <div className="super-panel page-data-panel">
          {isInitialLoading ? <LoadingState /> : null}

          {!isInitialLoading && payload && !payload.table_ready ? (
            <div className="page-data-notice is-error">{payload.message || 'Inquiry storage is not ready yet.'}</div>
          ) : null}

          {!isInitialLoading && inquiries.length === 0 ? (
            <EmptyState
              title="No inquiries"
              message={error ? 'Data will appear here once the backend is reachable.' : 'New landing page messages will show up here.'}
            />
          ) : null}

          {inquiries.length > 0 ? (
            <div className="super-inquiry-list">
              {inquiries.map((inquiry) => (
                <article className="super-inquiry-card" key={inquiry.inquiry_id}>
                  <div>
                    <div className="super-inquiry-head">
                      <strong>{inquiry.name}</strong>
                      <Badge tone={statusTone(inquiry.status)}>{statusLabel(inquiry.status)}</Badge>
                    </div>
                    <div className="rr-meta">
                      {[inquiry.organization, inquiry.email, inquiry.created_at].filter(Boolean).join(' · ')}
                    </div>
                    <p>{inquiry.message}</p>
                  </div>
                  <div className="super-inquiry-actions">
                    <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'in_review')}>Review</button>
                    <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'responded')}>Responded</button>
                    <button className="btn btn-secondary btn-sm" type="button" onClick={() => changeStatus(inquiry, 'closed')}>Close</button>
                  </div>
                </article>
              ))}
            </div>
          ) : null}
        </div>
      </RefreshOverlay>

      <PaginationBar meta={pagination} onPageChange={setPage} label="inquiries" />
    </section>
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
