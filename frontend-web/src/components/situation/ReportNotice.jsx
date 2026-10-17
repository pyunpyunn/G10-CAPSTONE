import { useEffect, useState } from 'react'
import { X } from 'lucide-react'

export default function ReportNotice({ children, error = false, duration = 5000, onDismiss }) {
  const [paused, setPaused] = useState(false)
  const [closing, setClosing] = useState(false)
  useEffect(() => {
    if (error || !duration || paused) return
    const timer = window.setTimeout(() => setClosing(true), duration)
    return () => window.clearTimeout(timer)
  }, [error, duration, paused])
  useEffect(() => {
    if (!closing) return
    const timer = window.setTimeout(onDismiss, 200)
    return () => window.clearTimeout(timer)
  }, [closing, onDismiss])
  return <div className={`notice report-notice ${error ? 'error' : ''} ${closing ? 'notice-closing' : ''}`} role={error ? 'alert' : 'status'} onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)} onFocusCapture={() => setPaused(true)} onBlurCapture={event => { if (!event.currentTarget.contains(event.relatedTarget)) setPaused(false) }}>
    <div className="notice-content">{children}</div>
    <button type="button" className="notice-dismiss" aria-label="Dismiss notification" onClick={onDismiss}><X size={18} aria-hidden="true" /></button>
  </div>
}
