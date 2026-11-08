import { Component, useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'

class Boundary extends Component {
  state = { error: null }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    console.error('Page rendering failed.', error, info)
  }

  render() {
    if (this.state.error) {
      return <section className="page-error-boundary" role="alert">
        <h2>This page could not be displayed</h2>
        <p>An unexpected error interrupted this page. Reload it to try again.</p>
        <button type="button" onClick={() => window.location.reload()}>Reload page</button>
      </section>
    }

    return this.props.children
  }
}

export default function PageErrorBoundary({ children }) {
  const location = useLocation()
  return <Boundary key={location.pathname}>{children}</Boundary>
}

export function RuntimeErrorNotice() {
  const [message, setMessage] = useState('')

  useEffect(() => {
    function showError(event) {
      const error = event.reason || event.error
      const status = error?.response?.status
      const nextMessage = status === 503
        ? 'The service or database is unavailable. Please try again shortly.'
        : status === 401
          ? 'Your session has expired. Sign in again to continue.'
          : status === 403
            ? 'You no longer have permission to complete that action.'
            : error?.friendlyMessage || 'An unexpected error interrupted an action. Check the page and try again.'
      setMessage(nextMessage)
    }

    window.addEventListener('error', showError)
    window.addEventListener('unhandledrejection', showError)
    return () => {
      window.removeEventListener('error', showError)
      window.removeEventListener('unhandledrejection', showError)
    }
  }, [])

  if (!message) return null

  return <div className="app-runtime-error" role="alert">
    <span>{message}</span>
    <button type="button" onClick={() => setMessage('')} aria-label="Dismiss error">Dismiss</button>
  </div>
}
