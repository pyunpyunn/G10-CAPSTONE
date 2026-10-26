import { Fragment, useCallback, useEffect, useState } from 'react'
import { LockKeyhole, RefreshCw } from 'lucide-react'
import { getInquiries, updateInquiryStatus } from '../api/inquiryApi'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import '../styles/super-admin-reference.css'

const STATES = [['new', 'New'], ['in_review', 'In review'], ['responded', 'Responded'], ['closed', 'Closed']]
const NEXT = { new: [['Review', 'in_review'], ['Close', 'closed']], in_review: [['Mark responded', 'responded'], ['Close', 'closed']], responded: [['Close', 'closed']], closed: [] }
const label = (status) => STATES.find(([key]) => key === status)?.[1] || status
const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('')
function ago(value) {
  const time = new Date(value).getTime()
  if (!Number.isFinite(time)) return ''
  const minutes = Math.max(0, Math.floor((Date.now() - time) / 60000))
  return minutes < 1 ? 'now' : minutes < 60 ? `${minutes}m` : minutes < 1440 ? `${Math.floor(minutes / 60)}h` : `${Math.floor(minutes / 1440)}d`
}
function date(value) {
  const time = new Date(value)
  return Number.isNaN(time.getTime()) ? '' : time.toLocaleString('en', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

export default function SuperAdminPage() {
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [selectedId, setSelectedId] = useState(null)
  const [pending, setPending] = useState(false)
  const [actionError, setActionError] = useState('')
  const [toast, setToast] = useState('')
  const [activity, setActivity] = useState({})
  const loader = useCallback(() => getInquiries({ status, page, per_page: 50 }), [status, page])
  const { data: payload, error, loading, refresh } = useModuleData(loader, ['inquiries', 'accounts'], { keepPreviousData: true })
  const inquiries = payload?.inquiries?.data || []
  const selected = inquiries.find((item) => item.inquiry_id === selectedId) || inquiries[0]
  const summary = payload?.summary || {}
  const accounts = payload?.accounts?.summary || {}
  const index = STATES.findIndex(([key]) => key === selected?.status)

  useEffect(() => {
    if (!toast) return undefined
    const timer = window.setTimeout(() => setToast(''), 2500)
    return () => window.clearTimeout(timer)
  }, [toast])

  async function changeStatus(nextStatus) {
    if (!selected || pending) return
    setPending(true)
    setActionError('')
    try {
      await updateInquiryStatus(selected.inquiry_id, nextStatus)
      setActivity((previous) => ({ ...previous, [selected.inquiry_id]: [{ status: nextStatus, time: new Date().toISOString() }, ...(previous[selected.inquiry_id] || [])] }))
      setToast({ in_review: 'Moved to review', responded: 'Marked responded', closed: 'Closed' }[nextStatus])
      refresh()
    } catch (failure) {
      setActionError(failure.response?.data?.message || 'Inquiry status cannot be updated right now.')
    } finally {
      setPending(false)
    }
  }

  function navigateList(event) {
    if (!['ArrowDown', 'ArrowUp'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey) return
    const current = inquiries.findIndex((item) => item.inquiry_id === selected?.inquiry_id)
    const next = inquiries[Math.max(0, Math.min(inquiries.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1)))]
    if (!next) return
    event.preventDefault()
    setSelectedId(next.inquiry_id)
    const button = event.currentTarget.querySelector(`[data-inquiry-id="${next.inquiry_id}"]`)
    button?.focus()
    button?.scrollIntoView({ block: 'nearest' })
  }

  return (
    <section className="super-inquiries" aria-label="Super admin inquiries" aria-busy={loading || pending}>
      <div className="hd">
        {[['all', 'All'], ...STATES].map(([key, title]) => (
          <button type="button" className={`fc${status === key ? ' on' : ''}`} key={key} aria-pressed={status === key} disabled={pending} onClick={() => { setStatus(key); setPage(1); setSelectedId(null); setActionError('') }}>
            {key !== 'all' && <i aria-hidden="true" />}{title}<em>{summary[key === 'all' ? 'total' : key] || 0}</em>
          </button>
        ))}
        <span className="sp" />
        <span className="lk">HQ · Web <b>{accounts.hq_web || 0}</b></span>
        <span className="lk">Rescuers · Mobile <b>{accounts.rescuer_mobile || 0}</b></span>
        <span className="lk"><LockKeyhole size={12} /> Superadmin</span>
        <button type="button" className={`ib${loading ? ' spin' : ''}`} title="Refresh" aria-label="Refresh inquiries" disabled={loading || pending} onClick={refresh}><i><RefreshCw size={14} /></i></button>
      </div>
      {(actionError || error) && <div className="notice" role="alert">{actionError || error}</div>}
      {payload && !payload.table_ready && <div className="notice" role="alert">{payload.message || 'Inquiries are not available yet.'}</div>}
      <div className="sp2">
        <div className="cd list-column">
          <div className="ls" aria-label="Inquiries" onKeyDown={navigateList}>
            {inquiries.map((inquiry) => (
              <button type="button" key={inquiry.inquiry_id} data-inquiry-id={inquiry.inquiry_id} className={`it${inquiry.inquiry_id === selected?.inquiry_id ? ' on' : ''}${inquiry.status === 'new' ? ' nw' : ''}`} aria-pressed={inquiry.inquiry_id === selected?.inquiry_id} disabled={pending} onClick={() => setSelectedId(inquiry.inquiry_id)}>
                <span className="av">{initials(inquiry.name)}</span>
                <div className="r1"><b>{inquiry.name}</b>{inquiry.status === 'new' && <i className="nd" aria-label="New inquiry" />}<em>{ago(inquiry.created_at)}</em></div>
                <div className="r2"><span>{inquiry.organization || inquiry.email}</span><i title={label(inquiry.status)} /></div>
              </button>
            ))}
            {!inquiries.length && <div className="emp">{loading ? 'Loading inquiries…' : 'No inquiries'}</div>}
          </div>
          {payload?.inquiries?.last_page > 1 && <PaginationBar reference meta={payload.inquiries} onPageChange={(nextPage) => { setPage(nextPage); setSelectedId(null) }} label="inquiries" />}
        </div>
        <div className="cd dt">
          {selected ? <div className="dc" key={selected.inquiry_id}>
            <div className="cover">
              <span className="slot" />
              <span className="av">{initials(selected.name)}</span>
              <span className="bs">{label(selected.status)}</span>
              <div className="cn"><div className="nm">{selected.name}</div><div className="cm">{selected.organization}</div><a href={`mailto:${selected.email}`}>{selected.email}</a></div>
            </div>
            <div className="body">
              <div className="track" aria-label={`Status: ${label(selected.status)}`}>
                {STATES.map(([key, title], position) => <Fragment key={key}>
                  {position > 0 && <span className={`ln${position <= index ? ' f' : ''}`} />}
                  <div className={`tk${position < index ? ' done' : position === index ? ' cur' : ''}`} aria-current={position === index ? 'step' : undefined}><i>{position < index ? '✓' : position + 1}</i><span>{title}</span></div>
                </Fragment>)}
              </div>
              <div className="acts">
                {(NEXT[selected.status] || []).map(([title, nextStatus], position) => <button type="button" className={`bt ${nextStatus === 'closed' ? 'd' : position === 0 ? 'p' : ''}`} key={nextStatus} disabled={pending || loading} onClick={() => changeStatus(nextStatus)}>{title}</button>)}
                <a className="bt" href={`mailto:${selected.email}?subject=${encodeURIComponent('Re: your inquiry')}`}>Reply by email</a>
              </div>
              <div className="sec msg"><div className="mt">Received · {date(selected.created_at)}</div><p>{selected.message}</p></div>
              {!!activity[selected.inquiry_id]?.length && <div className="sec"><div className="mt">Activity · this session</div>{activity[selected.inquiry_id].map((entry, position) => <div className="lg" key={`${entry.time}-${position}`}><i /><span>{label(entry.status)}</span><em>{date(entry.time)}</em></div>)}</div>}
            </div>
          </div> : <div className="emp">{loading ? 'Loading inquiries…' : 'No inquiries'}</div>}
        </div>
      </div>
      <div className={`toast${toast ? ' s' : ''}`} role="status">{toast}</div>
    </section>
  )
}
