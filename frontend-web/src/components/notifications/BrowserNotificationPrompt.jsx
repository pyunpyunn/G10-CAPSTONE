import { useEffect, useState } from 'react'
import { browserPushState, enableBrowserPush } from '../../api/browserPush'
import { BellRing } from 'lucide-react'

export default function BrowserNotificationPrompt() {
  const [state, setState] = useState(browserPushState)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  useEffect(() => {
    let disposed = false
    const refresh = async () => {
      const value = browserPushState()
      if (!disposed) setState(value)
      if (value === 'granted') {
        try { await enableBrowserPush({ requestPermission: false }); if (!disposed) setError('') }
        catch (failure) { if (!disposed) setError(failure.message) }
      }
    }
    void refresh()
    window.addEventListener('focus', refresh)
    const changed = event => { if (event.data?.type === 'notifications:changed') window.dispatchEvent(new Event('notifications:changed')) }
    navigator.serviceWorker?.addEventListener('message', changed)
    return () => { disposed = true; window.removeEventListener('focus', refresh); navigator.serviceWorker?.removeEventListener('message', changed) }
  }, [])
  if (state === 'granted' && !error) return null
  const hint = error || ({
      insecure: 'Open the secure HTTPS address to enable background notifications.',
      unsupported: 'Use a browser that supports background push notifications.',
      denied: 'Notifications are blocked. Open this site’s browser permissions, allow notifications, then reload.',
      default: 'Allow notifications for broadcasts, dispatch activity, approval requests, and official advisory syncs—even while this tab is in the background.',
    }[state])
  const content = <><BellRing size={17} aria-hidden="true" /><span>Enable web notifications</span></>
  if (state === 'insecure' && import.meta.env.VITE_PUBLIC_WEB_URL) {
    return <a className="nav-item browser-notification-control" href={import.meta.env.VITE_PUBLIC_WEB_URL} title={hint} aria-label="Enable web notifications">{content}</a>
  }
  return <button className="nav-item browser-notification-control" type="button" title={hint} aria-label="Enable web notifications" aria-busy={busy}
    disabled={busy || state === 'unsupported'} onClick={async () => {
      if (state === 'denied' || state === 'insecure') { window.alert(hint); return }
      setBusy(true); setError('')
      try { setState(await enableBrowserPush()) } catch (failure) { setError(failure.message) }
      finally { setBusy(false) }
    }}>{content}</button>
}
