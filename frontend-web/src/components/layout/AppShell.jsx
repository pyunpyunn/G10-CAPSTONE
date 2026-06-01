import { Outlet } from 'react-router-dom'
import { useState } from 'react'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

export default function AppShell({ user, pages, onLogout }) {
  const [isPinned, setIsPinned] = useState(false)
  const shellClass = isPinned ? 'app-shell left-pinned' : 'app-shell left-hidden'

  return (
    <div className={shellClass}>
      <Sidebar
        pages={pages}
        isPinned={isPinned}
        onTogglePin={() => setIsPinned(!isPinned)}
        onLogout={onLogout}
      />
      <div className="workspace">
        <Topbar user={user} />
        <main className="page-area">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
