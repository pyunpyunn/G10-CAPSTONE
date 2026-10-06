import DataFilterBar from '../ui/DataFilterBar'

export default function ResourceRequestFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  puroks = [],
  statuses = [],
  categories = [],
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
          options: [
            { value: 'all', label: 'All' },
            ...statuses.map((item) => ({ value: `status:${item.key}`, label: item.label })),
            ...categories.map((item) => ({ value: `category:${item.key}`, label: item.label })),
          ],
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
