import DataFilterBar from '../ui/DataFilterBar'

const dutyFilters = [
  { key: 'all', label: 'All duty statuses' },
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
    .map((team) => ({ value: `team:${team.team_code}`, label: team.team_code }))

  return (
    <DataFilterBar
      search={search}
      onSearchChange={onSearchChange}
      className="page-heading-filter"
      searchPlaceholder="Search name, ID, team, skill..."
      filters={[
        {
          id: 'purok',
          label: 'Home area',
          value: purok,
          onChange: onPurokChange,
          options: [{ value: 'all', label: 'All puroks' }, ...puroks.map((item) => ({ value: item, label: item }))],
        },
        {
          id: 'team-duty',
          label: 'Team or duty',
          value: activeChip,
          onChange: onChipChange,
          options: [...dutyFilters.map(({ key, label }) => ({ value: key, label })), ...teamFilters],
        },
      ]}
      onReset={() => {
        onSearchChange('')
        onPurokChange('all')
        onChipChange('all')
      }}
    />
  )
}
