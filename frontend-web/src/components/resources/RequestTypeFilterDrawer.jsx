import {
  Check,
  Filter,
  RotateCcw,
  Search,
  SlidersHorizontal,
  X,
} from 'lucide-react'

export default function RequestTypeFilterDrawer({
  isOpen,
  onClose,
  categories = [],
  activeCategory,
  onCategoryChange,
  activeStatus,
  onStatusChange,
  activeSort,
  onSortChange,
  searchTerm,
  onSearchChange,
  onResetFilters,
  totalResults = 0,
}) {
  if (!isOpen) return null

  const isFiltered = activeCategory !== 'all' || activeStatus !== 'all' || activeSort !== 'newest' || Boolean(searchTerm)

  return (
    <div className="rr-drawer-backdrop fab-filter-backdrop" onClick={onClose} role="presentation">
      <div
        className="rr-drawer-container fab-filter-container"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Filter Request Types"
      >
        {/* Header */}
        <div className="rr-drawer-header">
          <div className="rr-drawer-title-group">
            <span className="rr-drawer-kicker">Catalog Preferences</span>
            <h2 className="rr-drawer-title">
              <SlidersHorizontal size={18} className="inline-icon" /> Filter Request Types
            </h2>
          </div>
          <button
            className="rr-drawer-close"
            type="button"
            onClick={onClose}
            aria-label="Close filter drawer"
          >
            <X size={20} />
          </button>
        </div>

        {/* Filter Content */}
        <div className="rr-drawer-body fab-filter-body">
          {/* Keyword Search */}
          <div className="filter-group">
            <label className="filter-label" htmlFor="filter-search-input">
              Search by Item or SKU
            </label>
            <div className="filter-search-wrap">
              <Search size={16} className="search-icon" />
              <input
                id="filter-search-input"
                type="text"
                placeholder="e.g. Rice, SKU-RICE, Medical..."
                value={searchTerm}
                onChange={(e) => onSearchChange(e.target.value)}
                className="filter-search-input"
              />
              {searchTerm && (
                <button
                  type="button"
                  className="btn-clear-search"
                  onClick={() => onSearchChange('')}
                  aria-label="Clear search"
                >
                  <X size={14} />
                </button>
              )}
            </div>
          </div>

          {/* Stock Availability Filter ("pwede out of stock diri") */}
          <div className="filter-group">
            <label className="filter-label">Stock Availability</label>
            <div className="filter-button-group">
              <button
                type="button"
                className={`filter-btn-pill ${activeStatus === 'all' ? 'active' : ''}`}
                onClick={() => onStatusChange('all')}
              >
                All Availability
              </button>
              <button
                type="button"
                className={`filter-btn-pill ${activeStatus === 'in_stock' ? 'active status-in' : ''}`}
                onClick={() => onStatusChange('in_stock')}
              >
                In Stock Only
              </button>
              <button
                type="button"
                className={`filter-btn-pill ${activeStatus === 'out_of_stock' ? 'active status-out' : ''}`}
                onClick={() => onStatusChange('out_of_stock')}
              >
                Out of Stock Only
              </button>
            </div>
          </div>

          {/* Category Filter */}
          <div className="filter-group">
            <label className="filter-label">Resource Category</label>
            <div className="category-pills-wrap">
              {categories.map((cat) => {
                const isSelected = activeCategory === cat.key
                return (
                  <button
                    key={cat.key}
                    type="button"
                    className={`cat-filter-chip ${isSelected ? 'selected' : ''}`}
                    onClick={() => onCategoryChange(cat.key)}
                  >
                    <span>{cat.label}</span>
                    {typeof cat.count === 'number' && (
                      <span className="chip-badge">{cat.count}</span>
                    )}
                  </button>
                )
              })}
            </div>
          </div>

          {/* Sorting Options */}
          <div className="filter-group">
            <label className="filter-label">Sort Order</label>
            <div className="filter-select-wrap">
              <select
                value={activeSort}
                onChange={(e) => onSortChange(e.target.value)}
                className="filter-select-input"
              >
                <option value="newest">1st Card Priority (Newest First)</option>
                <option value="name">Name (Alphabetical A - Z)</option>
                <option value="quantity_desc">Quantity (High to Low)</option>
              </select>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="rr-drawer-footer fab-filter-footer">
          <button
            type="button"
            className="btn btn-secondary btn-reset-filters"
            onClick={onResetFilters}
            disabled={!isFiltered}
          >
            <RotateCcw size={14} /> Reset Filters
          </button>

          <button
            type="button"
            className="btn btn-primary btn-apply-filters"
            onClick={onClose}
          >
            Show {totalResults} Results
          </button>
        </div>
      </div>
    </div>
  )
}
