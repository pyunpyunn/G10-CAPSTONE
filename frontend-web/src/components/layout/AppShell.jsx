import { Outlet, useLocation } from 'react-router-dom'
import { useEffect, useRef, useState } from 'react'
import Sidebar from './Sidebar'
import Topbar from './Topbar'
import { connectRealtimeNotifications } from '../../api/realtimeNotifications'
import { topicsForPath } from '../../utils/realtimeTopics'

export default function AppShell({ user, pages, onLogout, onUserChange }) {
  const [isPinned, setIsPinned] = useState(false)
  const [isPeeking, setIsPeeking] = useState(false)
  const [isScrolled, setIsScrolled] = useState(false)
  const [isTopbarHovered, setIsTopbarHovered] = useState(false)
  const [isTopEdgeHovered, setIsTopEdgeHovered] = useState(false)
  const mainRef = useRef(null)
  const revealTimeoutRef = useRef(null)
  const userId = user?.user_id || user?.id
  const location = useLocation()
  const topicKey = topicsForPath(location.pathname, user?.role?.role_key === 'super_admin').join(',')
  useEffect(() => connectRealtimeNotifications(userId, topicKey.split(',')), [userId, topicKey])
  const shellClass = [
    'app-shell',
    isPinned ? 'left-pinned' : 'left-hidden',
    isPeeking && !isPinned ? 'left-peeking' : '',
    isScrolled && !isTopbarHovered && !isTopEdgeHovered ? 'topbar-hidden' : '',
  ].filter(Boolean).join(' ')

  useEffect(() => {
    const scrollContainer = mainRef.current

    if (!scrollContainer) return undefined

    function updateScrollState() {
      setIsScrolled(scrollContainer.scrollTop > 8)
    }

    scrollContainer.addEventListener('scroll', updateScrollState, { passive: true })

    return () => scrollContainer.removeEventListener('scroll', updateScrollState)
  }, [])

  useEffect(() => () => window.clearTimeout(revealTimeoutRef.current), [])

  function clearRevealTimeout() {
    window.clearTimeout(revealTimeoutRef.current)
  }

  function revealFromTopEdge() {
    clearRevealTimeout()
    setIsTopEdgeHovered(true)
  }

  function deferTopEdgeHide() {
    clearRevealTimeout()
    revealTimeoutRef.current = window.setTimeout(() => {
      setIsTopEdgeHovered(false)
    }, 320)
  }

  function revealFromTopbar() {
    clearRevealTimeout()
    setIsTopEdgeHovered(false)
    setIsTopbarHovered(true)
  }

  function hideFromTopbar(event) {
    if (event?.currentTarget?.contains(event.relatedTarget)) return

    setIsTopbarHovered(false)
  }

  return (
    <div className={shellClass}>
      <Sidebar
        pages={pages}
        isPinned={isPinned}
        onTogglePin={() => setIsPinned(!isPinned)}
        onPeekStart={() => setIsPeeking(true)}
        onPeekEnd={() => setIsPeeking(false)}
        onLogout={onLogout}
      />
      <div
        className="topbar-hover-zone"
        aria-hidden="true"
        onMouseEnter={revealFromTopEdge}
        onMouseLeave={deferTopEdgeHide}
      />
      <Topbar
        user={user}
        onMouseEnter={revealFromTopbar}
        onMouseLeave={hideFromTopbar}
        onFocusCapture={revealFromTopbar}
        onBlurCapture={hideFromTopbar}
      />
      <main className="main" ref={mainRef}>
        <div className="main-view">
          <Outlet context={{ user, onUserChange }} />
        </div>
      </main>
    </div>
  )
}
