import SearchInput from '../ui/SearchInput'

const dutyFilters = [
  { key: 'all', label: 'All' },
  { key: 'on_duty', label: 'Available' },
  { key: 'training_due', label: 'Training due' },
]

export default function RescuerFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  puroks,
  teamOptions = [],
  activeChip,
  onChipChange,
}) {
  const teamFilters = teamOptions
    .filter((team) => team.team_code && team.team_name)
    .map((team) => ({
      key: `team:${team.team_code}`,
      label: team.team_code,
    }))

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
        {dutyFilters.map((filter) => (
          <option value={filter.key} key={filter.key}>
            {filter.label}
          </option>
        ))}
        {teamFilters.map((filter) => (
          <option value={filter.key} key={filter.key}>
            {filter.label}
          </option>
        ))}
      </select>
    </div>
  )
}
