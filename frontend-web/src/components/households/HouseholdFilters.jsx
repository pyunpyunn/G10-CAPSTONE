import DataFilterBar from '../ui/DataFilterBar'
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
    <DataFilterBar
      className="page-heading-filter"
      search={searchText}
      onSearchChange={onSearchTextChange}
      searchPlaceholder="Search household, account ID, purok, device..."
      filters={[
        {
          id: 'purok',
          label: 'Area',
          value: purok,
          onChange: onPurokChange,
          options: [{ value: 'all', label: 'All puroks' }, ...puroks.map((item) => ({ value: item, label: item }))],
        },
        {
          id: 'status',
          label: 'Status',
          value: status,
          onChange: onStatusChange,
          options: statusFilters.map((filter) => ({
            value: filter.key,
            label: `${filter.label} (${summary[filter.countKey] || 0})`,
          })),
        },
      ]}
      onReset={() => {
        onSearchTextChange('')
        onPurokChange('all')
        onStatusChange('all')
      }}
    />
  )
}
