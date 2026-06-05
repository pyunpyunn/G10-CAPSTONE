import SearchInput from '../ui/SearchInput'

const chipFilters = [
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
      <div className="filter-chip-row">
        {chipFilters.map((chip) => (
          <button
            className={`filter-chip ${activeChip === chip.key ? 'active' : ''}`}
            type="button"
            key={chip.key}
            onClick={() => onChipChange(chip.key)}
          >
            {chip.label}
          </button>
        ))}
      </div>
    </div>
  )
}

