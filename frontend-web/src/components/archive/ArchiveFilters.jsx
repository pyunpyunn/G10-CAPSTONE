import SearchInput from '../ui/SearchInput'

export default function ArchiveFilters({
  search,
  onSearchChange,
  purok,
  onPurokChange,
  eventId,
  onEventChange,
  status,
  onStatusChange,
  filters = {},
}) {
  return (
    <div className="filter-bar archive-filter-bar">
      <SearchInput
        value={search}
        onChange={onSearchChange}
        placeholder="Search event, household, team, request, date..."
      />

      <select className="filter-select" value={purok} onChange={(event) => onPurokChange(event.target.value)}>
        <option value="all">All puroks</option>
        {(filters.puroks || []).map((item) => (
          <option value={item} key={item}>{item}</option>
        ))}
      </select>

      <select className="filter-select" value={eventId} onChange={(event) => onEventChange(event.target.value)}>
        <option value="all">All events</option>
        {(filters.events || []).map((item) => (
          <option value={item.event_id} key={item.event_id}>{item.label || item.name}</option>
        ))}
      </select>

      <select className="filter-select" value={status} onChange={(event) => onStatusChange(event.target.value)}>
        <option value="all">All statuses</option>
        {(filters.statuses || []).map((item) => (
          <option value={item.key} key={item.key}>{item.label}</option>
        ))}
      </select>
    </div>
  )
}
