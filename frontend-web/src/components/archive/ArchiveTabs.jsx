import { ARCHIVE_TABS } from '../../utils/archiveHelpers'
import { Archive, Menu } from 'lucide-react'

export default function ArchiveTabs({ activeCategory, onChange, isOpen, onToggle, counts = {}, selectedEventId = 'all', eventCounts = {}, selectedEventName = '' }) {
  return (
    <aside className={`archive-category-rail ${isOpen ? 'is-open' : 'is-collapsed'}`}>
      <div className="archive-rail-head">
        <p className="archive-rail-label">Archive categories</p>
        <button className="archive-rail-toggle" type="button" aria-label="Toggle archive categories" onClick={onToggle}>
          <Menu size={15} />
        </button>
      </div>
      <nav className="archive-tabs" role="tablist" aria-label="Archive categories">
        {ARCHIVE_TABS.map((tab) => (
          <button
            className={`archive-tab ${activeCategory === tab.key ? 'active' : ''} ${tab.key !== 'disaster-events' && selectedEventId === 'all' ? 'is-disabled' : ''}`}
            type="button"
            role="tab"
            aria-selected={activeCategory === tab.key}
            aria-disabled={tab.key !== 'disaster-events' && selectedEventId === 'all'}
            key={tab.key}
            onClick={() => onChange(tab.key)}
            title={tab.label}
            disabled={tab.key !== 'disaster-events' && selectedEventId === 'all'}
          >
            <Archive size={14} />
            <span className="archive-tab-label">{tab.label}</span>
            {isOpen && (selectedEventId !== 'all' ? (eventCounts[tab.key] ?? 0) : counts[tab.key]) !== undefined && <span className="archive-tab-count">{selectedEventId !== 'all' ? (eventCounts[tab.key] ?? 0) : counts[tab.key]}</span>}
          </button>
        ))}
      </nav>
      {isOpen && selectedEventId !== 'all' && <div className="archive-selected-event"><span>Selected disaster event</span><strong>{selectedEventName}</strong><button type="button" onClick={() => onChange('disaster-events')}>Select another event</button></div>}
    </aside>
  )
}
