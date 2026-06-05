import SearchInput from '../ui/SearchInput'

const rescuerFilters = [
  { key: 'all', label: 'All' },
  { key: 'SAR', label: 'SAR' },
  { key: 'Evacuation', label: 'Evacuation' },
  { key: 'Medical / First Aid', label: 'Medical' },
  { key: 'Relief & Transport', label: 'Relief / Transport' },
  { key: 'on_duty', label: 'Available' },
  { key: 'training_due', label: 'Training due' },
]

export default function RescuerFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  puroks,
  activeChip,
  onChipChange,
}) {
  return (
    <div className="filter-bar">
      <SearchInput value={search} onChange={onSearchChange} placeholder="Search name, ID, team, skill..." />

      <select className="filter-select" aria-label="Filter rescuers by home purok" value={purok} onChange={(event) => onPurokChange(event.target.value)}>
        <option value="all">All puroks</option>
        {puroks.map((item) => (
          <option value={item} key={item}>{item}</option>
        ))}
      </select>

      <select className="filter-select" aria-label="Filter rescuers by team or duty status" value={activeChip} onChange={(event) => onChipChange(event.target.value)}>
        {rescuerFilters.map((filter) => (
          <option value={filter.key} key={filter.key}>
            {filter.label}
          </option>
        ))}
      </select>
    </div>
  )
}
