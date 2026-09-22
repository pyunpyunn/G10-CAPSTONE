import { ARCHIVE_TABS } from '../../utils/archiveHelpers'
import { Archive, Menu } from 'lucide-react'

export default function ArchiveTabs({ activeCategory, onChange, isOpen, onToggle, counts = {} }) {
  return (
    <aside className={`archive-category-rail ${isOpen ? 'is-open' : 'is-collapsed'}`}>
      <button className="archive-rail-toggle" type="button" aria-label="Toggle archive categories" onClick={onToggle}>
        <Menu size={15} />
      </button>
      <p className="archive-rail-label">Archive categories</p>
      <nav className="archive-tabs" role="tablist" aria-label="Archive categories">
        {ARCHIVE_TABS.map((tab) => (
          <button
            className={`archive-tab ${activeCategory === tab.key ? 'active' : ''}`}
            type="button"
            role="tab"
            aria-selected={activeCategory === tab.key}
            key={tab.key}
            onClick={() => onChange(tab.key)}
            title={tab.label}
          >
            <Archive size={14} />
            <span className="archive-tab-label">{tab.label}</span>
            {isOpen && counts[tab.key] !== undefined && <span className="archive-tab-count">{counts[tab.key]}</span>}
          </button>
        ))}
      </nav>
    </aside>
  )
}
