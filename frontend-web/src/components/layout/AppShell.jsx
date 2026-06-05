import { Outlet } from 'react-router-dom'
import { useState } from 'react'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

export default function AppShell({ user, pages, onLogout, onUserChange }) {
  const [isPinned, setIsPinned] = useState(false)
  const [isPeeking, setIsPeeking] = useState(false)
  const shellClass = [
    'app-shell',
    isPinned ? 'left-pinned' : 'left-hidden',
    isPeeking && !isPinned ? 'left-peeking' : '',
  ].filter(Boolean).join(' ')

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
      <Topbar user={user} />
      <main className="main">
        <Outlet context={{ user, onUserChange }} />
      </main>
    </div>
  )
}
