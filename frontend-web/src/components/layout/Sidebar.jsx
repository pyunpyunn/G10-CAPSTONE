import { NavLink, useLocation } from 'react-router-dom'
import { useEffect, useState } from 'react'
import ResQperationLogo from './ResQperationLogo'
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

const navGroups = [
  { title: 'General Monitoring', paths: ['/households', '/mapping', '/weather'] },
  { title: 'Response Operations', paths: ['/dispatch', '/rescue-management', '/field-reports'] },
  { title: 'Team Management', paths: ['/rescuers', '/rescuers/teams'] },
  { title: 'Resources & Requests', paths: ['/request-types', '/external-requests', '/resources-requests'] },
  { title: 'Reports Management', paths: ['/situation', '/archive'] },
]

const icons = {
  '/dashboard': LayoutDashboard,
  '/super-admin': Inbox,
  '/broadcast': Radio,
  '/weather': CloudSun,
  '/mapping': Map,
  '/households': House,
  '/dispatch': Route,
  '/rescue-management': Route,
  '/field-reports': FileCheck2,
  '/rescuers/teams': ShieldUser,
  '/request-types': PackageCheck,
  '/external-requests': Inbox,
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
      <div className="left-sidebar-brand" role="img" aria-label="ResQperation">
        <ResQperationLogo />
      </div>

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
        <NavLink
          to="/dashboard"
          end
          className={({ isActive }) => (isActive ? 'nav-item active' : 'nav-item')}
        >
          <LayoutDashboard size={17} />
          <span>Dashboard</span>
        </NavLink>
        {navGroups.map((group) => (
          <NavGroup
            key={group.title}
            title={group.title}
            pages={group.paths.map((path) => pages.find((page) => page.path === path && !page.navHidden)).filter(Boolean)}
            isOpen={openGroups.has(group.title)}
            onToggle={toggleGroup}
          />
        ))}
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
              end
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
  const activePage = pages
    .filter((page) => !page.navHidden)
    .sort((a, b) => b.path.length - a.path.length)
    .find((page) => pathname === page.path || pathname.startsWith(`${page.path}/`))

  return navGroups.find((group) => group.paths.includes(activePage?.path))?.title
}
