import { Search } from 'lucide-react'
import { statusFilters } from '../../utils/householdStatusHelpers'

export default function HouseholdFilters({
  searchText,
  purok,
  status,
  summary,
  puroks,
  onSearchTextChange,
  onPurokChange,
  onStatusChange,
}) {
  return (
    <div className="hh-filter-bar">
      <div className="hh-search-wrap">
        <Search size={15} />
        <input
          className="hh-search"
          type="search"
          placeholder="Search household, account ID, purok, device..."
          value={searchText}
          onChange={(event) => onSearchTextChange(event.target.value)}
        />
      </div>

      <select className="hh-filter-select" value={purok} onChange={(event) => onPurokChange(event.target.value)} aria-label="Filter households by purok">
        <option value="all">All puroks</option>
        {puroks.map((item) => (
          <option key={item} value={item}>{item}</option>
        ))}
      </select>

      {statusFilters.map((filter) => (
        <button
          className={`hh-filter-chip ${status === filter.key ? 'active' : ''} ${filter.urgent ? 'urgent' : ''}`}
          type="button"
          key={filter.key}
          onClick={() => onStatusChange(filter.key)}
        >
          {filter.label} ({summary[filter.countKey] || 0})
        </button>
      ))}
    </div>
  )
}
