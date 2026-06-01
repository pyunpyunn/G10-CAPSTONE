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

export default function Sidebar({ pages, onLogout }) {
  return (
    <aside className="sidebar" aria-label="Primary modules">
      <div className="brand-block">
        <span className="brand-mark">R</span>
        <div>
          <strong>RESQPERATION</strong>
          <span>HQ Command</span>
        </div>
      </div>

      <nav className="module-nav">
        <NavGroup title="Main Views" pages={pages.slice(0, 3)} />
        <NavGroup title="Response Operations" pages={pages.slice(3, 6)} />
        <NavGroup title="Management & Reports" pages={pages.slice(6)} />
      </nav>

      <button className="logout-button" type="button" onClick={onLogout}>
        <LogOut size={18} />
        <span>Logout</span>
      </button>
    </aside>
  )
}

function NavGroup({ title, pages }) {
  return (
    <div className="nav-group">
      <div className="nav-title">{title}</div>
      {pages.map((page) => {
        const Icon = icons[page.path] || Archive

        return (
          <NavLink key={page.path} to={page.path} className="nav-item">
            <Icon size={18} />
            <span>{page.title}</span>
          </NavLink>
        )
      })}
    </div>
  )
}
