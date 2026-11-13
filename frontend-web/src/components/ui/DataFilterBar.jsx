import { useMemo, useState, useRef, useEffect } from 'react'
import { getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { Filter, RotateCcw, Search } from 'lucide-react'

/* eslint-disable react-hooks/incompatible-library -- TanStack Table is the filter state engine for this controlled toolbar. */

/**
 * Compact DataFilterBar with popover filter dropdown and inline trailing metrics.
 * Designed to minimize vertical page footprint while maintaining quick access
 * to TanStack table column filters.
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
  const [isOpen, setIsOpen] = useState(false)
  const popoverRef = useRef(null)
  const triggerRef = useRef(null)

  const columns = useMemo(() => filters.map((filter) => ({
    id: filter.id,
    accessorFn: () => '',
    enableGlobalFilter: false,
  })), [filters])

  const columnFilters = filters
    .filter((filter) => filter.value !== undefined && filter.value !== '' && filter.value !== 'all')
    .map((filter) => ({ id: filter.id, value: filter.value }))

  const activeFilterCount = columnFilters.length

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

  const hasValues = Boolean(search) || activeFilterCount > 0

  useEffect(() => {
    function handleClickOutside(event) {
      if (
        isOpen &&
        popoverRef.current &&
        !popoverRef.current.contains(event.target) &&
        triggerRef.current &&
        !triggerRef.current.contains(event.target)
      ) {
        setIsOpen(false)
      }
    }
    function handleKeyDown(event) {
      if (event.key === 'Escape' && isOpen) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleKeyDown)
    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [isOpen])

  return (
    <div className={`data-filter-bar ${className}`.trim()} role="search" aria-label="Search and filter records">
      <div className="data-filter-bar-left">
        {onSearchChange && (
          <label className="data-filter-search">
            <Search size={15} aria-hidden="true" />
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
          <div className="data-filter-trigger-wrapper">
            <button
              ref={triggerRef}
              type="button"
              className={`data-filter-trigger ${activeFilterCount > 0 ? 'is-active' : ''}`}
              onClick={() => setIsOpen((prev) => !prev)}
              aria-expanded={isOpen}
              aria-haspopup="dialog"
              title="Toggle filter options"
            >
              <Filter size={14} aria-hidden="true" />
              <span>Filters</span>
              {activeFilterCount > 0 && (
                <span className="data-filter-badge">{activeFilterCount}</span>
              )}
            </button>

            {isOpen && (
              <div className="data-filter-popover" ref={popoverRef} role="dialog" aria-label="Filters Menu">
                <div className="data-filter-popover-head">
                  <span className="data-filter-popover-title">Filter Records</span>
                  {hasValues && onReset && (
                    <button
                      type="button"
                      className="data-filter-popover-reset"
                      onClick={() => {
                        onReset()
                        setIsOpen(false)
                      }}
                    >
                      <RotateCcw size={12} /> Reset
                    </button>
                  )}
                </div>
                <div className="data-filter-popover-body">
                  {filters.map((filter) => (
                    <div className="data-filter-popover-group" key={filter.id}>
                      <label className="data-filter-popover-label">{filter.label}</label>
                      <select
                        aria-label={filter.ariaLabel || filter.label}
                        value={filter.value}
                        onChange={(event) => {
                          table.getColumn(filter.id)?.setFilterValue(event.target.value)
                        }}
                        className="data-filter-popover-select"
                      >
                        {filter.options.map((option) => (
                          <option value={option.value} key={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {trailing && <div className="data-filter-bar-trailing">{trailing}</div>}
    </div>
  )
}

const EMPTY_ROWS = []
