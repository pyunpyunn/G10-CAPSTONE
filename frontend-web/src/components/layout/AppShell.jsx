import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

export default function AppShell({ user, pages, onLogout }) {
  return (
    <div className="app-shell">
      <Sidebar pages={pages} onLogout={onLogout} />
      <div className="workspace">
        <Topbar user={user} />
        <main className="page-area">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
