import { NavLink, useLocation } from 'react-router-dom'
import { useEffect, useState } from 'react'
import {
  Archive,
  ChevronDown,
  CloudSun,
  Database,
  FileCheck2,
  House,
  Inbox,
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
  '/super-admin': Inbox,
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
  const location = useLocation()
  const activeGroup = getActiveGroup(location.pathname, pages)
  const [openGroups, setOpenGroups] = useState(() => new Set([activeGroup]))

  useEffect(() => {
    setOpenGroups((groups) => new Set(groups).add(activeGroup))
  }, [activeGroup])

  function toggleGroup(title) {
    setOpenGroups((groups) => {
      const nextGroups = new Set(groups)

      if (nextGroups.has(title)) {
        nextGroups.delete(title)
      } else {
        nextGroups.add(title)
      }

      return nextGroups
    })
  }

  return (
    <aside
      className="leftbar"
      aria-label="Primary modules"
      onMouseEnter={onPeekStart}
      onMouseLeave={onPeekEnd}
      onFocus={onPeekStart}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
          onPeekEnd()
        }
      }}
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
        <NavGroup title="Main Views" pages={pages.slice(0, 3)} isOpen={openGroups.has('Main Views')} onToggle={toggleGroup} />
        <NavGroup title="Response Operations" pages={pages.slice(3, 6)} isOpen={openGroups.has('Response Operations')} onToggle={toggleGroup} />
        <NavGroup title="Management & Reports" pages={pages.slice(6)} isOpen={openGroups.has('Management & Reports')} onToggle={toggleGroup} />
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

function NavGroup({ title, pages, isOpen, onToggle }) {
  return (
    <section className={`nav-group ${isOpen ? 'is-open' : ''}`}>
      <button
        className="nav-section-title"
        type="button"
        aria-expanded={isOpen}
        onClick={() => onToggle(title)}
      >
        <span>{title}</span>
        <ChevronDown size={14} aria-hidden="true" />
      </button>
      <div className="nav-group-items">
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
      </div>
    </section>
  )
}

function getActiveGroup(pathname, pages) {
  if (pages.slice(0, 3).some((page) => pathname === page.path || pathname.startsWith(`${page.path}/`))) {
    return 'Main Views'
  }

  if (pages.slice(3, 6).some((page) => pathname === page.path || pathname.startsWith(`${page.path}/`))) {
    return 'Response Operations'
  }

  return 'Management & Reports'
}
