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
    <div className="hh-filter-bar grid grid-cols-12 gap-3 items-center" role="search" aria-label="Search and filter households">
      <div className="hh-search-wrap col-span-6 flex items-center">
        <Search size={15} />
        <input
          className="hh-search w-full"
          type="search"
          placeholder="Search household, account ID, purok, device..."
          value={searchText}
          onChange={(event) => onSearchTextChange(event.target.value)}
        />
      </div>

      <select
        className="hh-filter-select col-span-3 w-full"
        value={purok}
        onChange={(event) => onPurokChange(event.target.value)}
        aria-label="Filter households by purok"
      >
        <option value="all">All puroks</option>
        {puroks.map((item) => (
          <option key={item} value={item}>
            {item}
          </option>
        ))}
      </select>

      <select
        className="hh-filter-select col-span-3 w-full"
        value={status}
        onChange={(event) => onStatusChange(event.target.value)}
        aria-label="Filter households by status"
      >
        {statusFilters.map((filter) => (
          <option key={filter.key} value={filter.key}>
            {filter.label} ({summary[filter.countKey] || 0})
          </option>
        ))}
      </select>
    </div>
  )
}