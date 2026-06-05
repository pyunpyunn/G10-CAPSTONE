import { requestChips } from '../../utils/resourceRequestHelpers'

export default function ResourceRequestFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  puroks = [],
  activeChip,
  onChipChange,
}) {
  return (
    <div className="filter-bar rr-filter-bar">
      <input
        className="search-input"
        type="search"
        value={search}
        placeholder="Search request ID, source, item, purok..."
        onChange={(event) => onSearchChange(event.target.value)}
      />
      <select
        className="filter-select"
        value={purok}
        aria-label="Filter requests by purok"
        onChange={(event) => onPurokChange(event.target.value)}
      >
        <option value="all">All puroks</option>
        {puroks.map((item) => (
          <option value={item} key={item}>{item}</option>
        ))}
      </select>

      <select
        className="filter-select"
        value={activeChip}
        aria-label="Filter requests by validation status or category"
        onChange={(event) => onChipChange(event.target.value)}
      >
        {requestChips.map((chip) => (
          <option value={chip.key} key={chip.key}>
            {chip.label}
          </option>
        ))}
      </select>
    </div>
  )
}
