import { ARCHIVE_TABS } from '../../utils/archiveHelpers'

export default function ArchiveTabs({ activeCategory, onChange }) {
  return (
    <div className="archive-tabs" role="tablist" aria-label="Archive categories">
      {ARCHIVE_TABS.map((tab) => (
        <button
          className={`archive-tab ${activeCategory === tab.key ? 'active' : ''}`}
          type="button"
          role="tab"
          aria-selected={activeCategory === tab.key}
          key={tab.key}
          onClick={() => onChange(tab.key)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  )
}
