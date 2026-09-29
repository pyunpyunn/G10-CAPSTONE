import DataFilterBar from '../ui/DataFilterBar'

export default function ArchiveFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  eventId,
  onEventChange,
  status,
  onStatusChange,
  onReset,
  filters = {},
}) {
  return (
    <DataFilterBar
      className="archive-filter-bar page-heading-filter"
      search={search}
      onSearchChange={onSearchChange}
      searchPlaceholder="Search event, household, team, request, date..."
      filters={[
        {
          id: 'purok', label: 'Area', value: purok, onChange: onPurokChange,
          options: [{ value: 'all', label: 'All puroks' }, ...(filters.puroks || []).map((item) => ({ value: item, label: item }))],
        },
        {
          id: 'event', label: 'Event', value: eventId, onChange: onEventChange,
          options: [{ value: 'all', label: 'All events' }, ...(filters.events || []).map((item) => ({ value: item.event_id, label: item.label || item.name }))],
        },
        {
          id: 'status', label: 'Status', value: status, onChange: onStatusChange,
          options: [{ value: 'all', label: 'All statuses' }, ...(filters.statuses || []).map((item) => ({ value: item.key, label: item.label }))],
        },
      ]}
      onReset={onReset}
    />
  )
}
