import { useMemo } from 'react'
import { getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { ListFilter, RotateCcw, Search } from 'lucide-react'

/* eslint-disable react-hooks/incompatible-library -- TanStack Table is the filter state engine for this controlled toolbar. */

/**
 * Shared filter controls backed by TanStack Table's controlled global and
 * column-filter state. Pages own the values so they can drive API requests;
 * filter definitions and labels remain page-configurable.
 */
export default function DataFilterBar({
  search,
  onSearchChange,
  searchPlaceholder = 'Search records...',
  filters = [],
  onReset,
  trailing,
  className = '',
}) {
  const columns = useMemo(() => filters.map((filter) => ({
    id: filter.id,
    accessorFn: () => '',
    enableGlobalFilter: false,
  })), [filters])
  const columnFilters = filters
    .filter((filter) => filter.value !== undefined && filter.value !== '' && filter.value !== 'all')
    .map((filter) => ({ id: filter.id, value: filter.value }))

  const table = useReactTable({
    data: EMPTY_ROWS,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualFiltering: true,
    state: {
      globalFilter: search || '',
      columnFilters,
    },
    onGlobalFilterChange: (updater) => {
      const next = typeof updater === 'function' ? updater(search || '') : updater
      onSearchChange?.(next)
    },
    onColumnFiltersChange: (updater) => {
      const nextFilters = typeof updater === 'function' ? updater(columnFilters) : updater
      filters.forEach((filter) => {
        const nextValue = nextFilters.find((item) => item.id === filter.id)?.value ?? 'all'
        if (nextValue !== filter.value) filter.onChange(nextValue)
      })
    },
  })
  const hasValues = Boolean(search) || filters.some((filter) => filter.value && filter.value !== 'all')

  return (
    <div className={`data-filter-bar ${className}`.trim()} role="search" aria-label="Search and filter records">
      {onSearchChange && (
        <label className="data-filter-search">
          <Search size={16} aria-hidden="true" />
          <span className="sr-only">Search records</span>
          <input
            type="search"
            value={table.getState().globalFilter || ''}
            placeholder={searchPlaceholder}
            onChange={(event) => table.setGlobalFilter(event.target.value)}
          />
        </label>
      )}
      {filters.length > 0 && (
        <div className="data-filter-fields">
          {filters.map((filter) => (
            <label className="data-filter-field" key={filter.id}>
              <span>{filter.label}</span>
              <select
                aria-label={filter.ariaLabel || filter.label}
                value={filter.value}
                onChange={(event) => table.getColumn(filter.id)?.setFilterValue(event.target.value)}
              >
                {filter.options.map((option) => (
                  <option value={option.value} key={option.value}>{option.label}</option>
                ))}
              </select>
            </label>
          ))}
        </div>
      )}
      {filters.length > 0 && <span className="data-filter-caption"><ListFilter size={14} /> Filters</span>}
      {trailing}
      {onReset && (
        <button className="data-filter-reset" type="button" onClick={onReset} disabled={!hasValues}>
          <RotateCcw size={14} /> Reset
        </button>
      )}
    </div>
  )
}

const EMPTY_ROWS = []
