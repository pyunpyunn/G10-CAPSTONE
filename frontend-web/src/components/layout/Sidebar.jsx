import { NavLink } from 'react-router-dom'
import {
  Archive,
  CloudSun,
  Database,
  FileCheck2,
  House,
  LayoutDashboard,
  LogOut,
  Map,
  PackageCheck,
  Pin,
  Radio,
  Route,
  ShieldUser,
} from 'lucide-react'

const icons = {
  '/dashboard': LayoutDashboard,
  '/broadcast': Radio,
  '/weather': CloudSun,
  '/mapping': Map,
  '/households': House,
  '/dispatch': Route,
  '/rescuers': ShieldUser,
  '/resources-requests': PackageCheck,
  '/situation': FileCheck2,
  '/archive': Database,
}

export default function Sidebar({ pages, isPinned, onTogglePin, onPeekStart, onPeekEnd, onLogout }) {
  return (
    <aside
      className="leftbar"
      aria-label="Primary modules"
      onMouseEnter={onPeekStart}
      onMouseLeave={onPeekEnd}
      onFocus={onPeekStart}
      onBlur={onPeekEnd}
    >
      <div className="left-sidebar-actions" aria-label="Navigation controls">
        <button
          className="left-pin-button"
          type="button"
          title={isPinned ? 'Unpin left navigation' : 'Pin left navigation'}
          aria-label={isPinned ? 'Unpin left navigation' : 'Pin left navigation'}
          aria-pressed={isPinned}
          onClick={onTogglePin}
        >
          <Pin size={17} />
        </button>
      </div>

      <nav className="left-nav">
        <NavGroup title="Main Views" pages={pages.slice(0, 3)} />
        <NavGroup title="Response Operations" pages={pages.slice(3, 6)} />
        <NavGroup title="Management & Reports" pages={pages.slice(6)} />
      </nav>

      <div className="left-sidebar-logout">
        <button className="nav-item" type="button" onClick={onLogout}>
          <LogOut size={17} />
          <span>Logout</span>
        </button>
      </div>
    </aside>
  )
}

function NavGroup({ title, pages }) {
  return (
    <>
      <div className="nav-section-title">{title}</div>
      {pages.map((page) => {
        const Icon = icons[page.path] || Archive

        return (
          <NavLink
            key={page.path}
            to={page.path}
            className={({ isActive }) => (isActive ? 'nav-item active' : 'nav-item')}
          >
            <Icon size={17} />
            <span>{page.title}</span>
          </NavLink>
        )
      })}
    </>
  )
}
