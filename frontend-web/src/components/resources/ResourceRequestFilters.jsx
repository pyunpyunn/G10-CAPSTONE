import DataFilterBar from '../ui/DataFilterBar'
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
    <DataFilterBar
      className="rr-filter-bar page-heading-filter"
      search={search}
      onSearchChange={onSearchChange}
      searchPlaceholder="Search request ID, source, item, purok..."
      filters={[
        {
          id: 'purok',
          label: 'Area',
          value: purok,
          onChange: onPurokChange,
          options: [{ value: 'all', label: 'All puroks' }, ...puroks.map((item) => ({ value: item, label: item }))],
        },
        {
          id: 'request-type',
          label: 'Status or type',
          value: activeChip,
          onChange: onChipChange,
          options: requestChips.map((chip) => ({ value: chip.key, label: chip.label })),
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
