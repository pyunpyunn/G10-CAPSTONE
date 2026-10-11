import { useCallback, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  AlertCircle,
  Boxes,
  CheckCircle2,
  Database,
  Filter,
  Layers,
  Package,
  Plus,
  RotateCw,
  Search,
  SlidersHorizontal,
  Sparkles,
  X,
  XCircle,
} from 'lucide-react'
import { getResourceRequestTypes } from '../api/resourceRequestApi'
import { useModuleData } from '../utils/useModuleData'
import PageHeader from '../components/ui/PageHeader'
import PaginationBar from '../components/ui/PaginationBar'
import RequestTypeCard from '../components/resources/RequestTypeCard'
import RequestTypeDetailDrawer from '../components/resources/RequestTypeDetailDrawer'
import RequestTypeFilterDrawer from '../components/resources/RequestTypeFilterDrawer'
import DeclareRequestTypeModal from '../components/resources/DeclareRequestTypeModal'
import RequestResourceModal from '../components/resources/RequestResourceModal'

export default function RequestTypesPage() {
  const navigate = useNavigate()

  // Pagination & Filter States
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(24)
  const [category, setCategory] = useState('all')
  const [status, setStatus] = useState('all')
  const [sort, setSort] = useState('newest')
  const [search, setSearch] = useState('')

  // UI Interactive States
  const [selectedItem, setSelectedItem] = useState(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isFilterDrawerOpen, setIsFilterDrawerOpen] = useState(false)
  const [isDeclareModalOpen, setIsDeclareModalOpen] = useState(false)
  const [requestModalItem, setRequestModalItem] = useState(null)
  const [isRequestModalOpen, setIsRequestModalOpen] = useState(false)
  const [successToast, setSuccessToast] = useState(null)

  // API Data Fetcher
  const loader = useCallback(
    () =>
      getResourceRequestTypes({
        page,
        per_page: perPage,
        category,
        status,
        sort,
        search,
      }),
    [page, perPage, category, status, sort, search],
  )

  const { data, error, loading, refresh } = useModuleData(loader)

  // Extract pagination, items, summary & categories
  const cardsPagination = data?.cards || data?.inventory || {}
  const items = cardsPagination.data || []
  const summary = data?.summary || {
    total_types: items.length,
    in_stock_count: items.filter((i) => !i.is_out_of_stock && i.quantity > 0).length,
    out_of_stock_count: items.filter((i) => i.is_out_of_stock || i.quantity <= 0).length,
    total_available_units: items.reduce((sum, i) => sum + (i.is_out_of_stock ? 0 : Number(i.quantity || 0)), 0),
  }
  const categories = data?.categories || [
    { key: 'all', label: 'All Categories', count: summary.total_types },
  ]
  const isTrackingAidLive =
    data?.source === 'trackingaid_db' || data?.source === 'trackingaid_shared_db'

  // Compute active filters count for FAB badge
  const activeFiltersCount = useMemo(() => {
    let count = 0
    if (category !== 'all') count += 1
    if (status !== 'all') count += 1
    if (sort !== 'newest') count += 1
    if (search.trim()) count += 1
    return count
  }, [category, status, sort, search])

  // Handlers
  const handleSelectItem = (item) => {
    setSelectedItem(item)
    setIsDetailOpen(true)
  }

  const handleRequestNow = (item) => {
    setRequestModalItem(item)
    setIsRequestModalOpen(true)
  }

  const handleRequestSuccess = (res, itm, qty) => {
    const reqId = res?.data?.request?.request_id || ''
    setSuccessToast(`Resource request ${reqId ? `#${reqId} ` : ''}for ${qty} ${itm.unit || 'units'} of "${itm.name || itm.label}" was successfully submitted!`)
    setTimeout(() => setSuccessToast(null), 6000)
    refresh()
  }

  const handleResetFilters = () => {
    setCategory('all')
    setStatus('all')
    setSort('newest')
    setSearch('')
    setPage(1)
  }

  const handleDeclareSuccess = () => {
    setSuccessToast('New request type successfully registered and prioritized in catalog!')
    setTimeout(() => setSuccessToast(null), 5000)
    refresh()
  }

  return (
    <main className="ops-page resources-page req-types-page">
      {/* Top Header */}
      <PageHeader
        title="Available Request Types"
        actions={
          <div className="req-header-actions">
            {/* Live Shared DB Indicator */}
            <div
              className={`db-sync-indicator ${isTrackingAidLive ? 'is-live' : 'is-fallback'}`}
              title={
                isTrackingAidLive
                  ? 'Connected to TrackingAid shared database (127.0.0.1:3306)'
                  : 'Operating via local disaster records'
              }
            >
              <span className="db-indicator-dot" />
              <span className="db-indicator-label">
                {isTrackingAidLive ? 'TrackingAid Shared DB' : 'Local Fallback'}
              </span>
            </div>

            <button
              type="button"
              className="btn btn-secondary btn-header-refresh"
              onClick={refresh}
              disabled={loading}
              title="Refresh inventory and catalog"
            >
              <RotateCw size={14} className={loading ? 'spin-icon' : ''} />
              <span>Refresh</span>
            </button>

            <button
              type="button"
              className="btn btn-primary btn-header-declare"
              onClick={() => setIsDeclareModalOpen(true)}
            >
              <Plus size={15} />
              <span>Declare New Type</span>
            </button>
          </div>
        }
      />

      {/* Success Notification Banner */}
      {successToast && (
        <div className="req-toast-banner" role="status">
          <Sparkles size={15} className="sparkle-icon" />
          <span>{successToast}</span>
        </div>
      )}

      {/* Sleek KPI Stats Strip */}
      <section className="req-stats-strip" aria-label="Catalog Quick Stats">
        <div className="kpi-mini-card">
          <div className="kpi-icon-box tone-blue">
            <Layers size={17} />
          </div>
          <div className="kpi-content">
            <span className="kpi-label">Total Types</span>
            <span className="kpi-value">{summary.total_types ?? 0}</span>
          </div>
        </div>

        <div className="kpi-mini-card">
          <div className="kpi-icon-box tone-emerald">
            <CheckCircle2 size={17} />
          </div>
          <div className="kpi-content">
            <span className="kpi-label">Available In Stock</span>
            <span className="kpi-value in-stock-val">{summary.in_stock_count ?? 0}</span>
          </div>
        </div>

        <div className="kpi-mini-card">
          <div className="kpi-icon-box tone-rose">
            <XCircle size={17} />
          </div>
          <div className="kpi-content">
            <span className="kpi-label">Out of Stock</span>
            <span className="kpi-value out-of-stock-val">{summary.out_of_stock_count ?? 0}</span>
          </div>
        </div>

        <div className="kpi-mini-card">
          <div className="kpi-icon-box tone-amber">
            <Boxes size={17} />
          </div>
          <div className="kpi-content">
            <span className="kpi-label">Available Units</span>
            <span className="kpi-value">
              {Number(summary.total_available_units ?? 0).toLocaleString()}
            </span>
          </div>
        </div>
      </section>

      {/* Simplified, Professional Filter Toolbar */}
      <div className="req-toolbar">
        {/* Search input with clean search icon */}
        <div className="req-search-box">
          <Search size={14} className="search-icon" />
          <input
            type="text"
            placeholder="Search by name, SKU, or depot..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="req-search-input"
          />
          {search && (
            <button
              type="button"
              className="req-clear-search-btn"
              onClick={() => {
                setSearch('')
                setPage(1)
              }}
              aria-label="Clear search"
            >
              <X size={13} />
            </button>
          )}
        </div>

        {/* Stock Status Segmented Control */}
        <div className="req-status-segmented">
          <button
            type="button"
            className={`seg-btn ${status === 'all' ? 'active' : ''}`}
            onClick={() => {
              setStatus('all')
              setPage(1)
            }}
          >
            All <span className="seg-count">{summary.total_types ?? 0}</span>
          </button>
          <button
            type="button"
            className={`seg-btn ${status === 'in_stock' ? 'active seg-in-stock' : ''}`}
            onClick={() => {
              setStatus('in_stock')
              setPage(1)
            }}
          >
            In Stock <span className="seg-count">{summary.in_stock_count ?? 0}</span>
          </button>
          <button
            type="button"
            className={`seg-btn ${status === 'out_of_stock' ? 'active seg-out-stock' : ''}`}
            onClick={() => {
              setStatus('out_of_stock')
              setPage(1)
            }}
          >
            Out of Stock <span className="seg-count">{summary.out_of_stock_count ?? 0}</span>
          </button>
        </div>

        {/* Category Dropdown & Results Counter */}
        <div className="req-toolbar-right">
          <div className="req-category-select-wrap">
            <Filter size={13} className="filter-select-icon" />
            <select
              value={category}
              onChange={(e) => {
                setCategory(e.target.value)
                setPage(1)
              }}
              className="req-category-select"
              aria-label="Filter by category"
            >
              {categories.map((cat) => (
                <option key={cat.key} value={cat.key}>
                  {cat.label} {typeof cat.count === 'number' ? `(${cat.count})` : ''}
                </option>
              ))}
            </select>
          </div>

          {activeFiltersCount > 0 && (
            <button
              type="button"
              className="btn-toolbar-reset"
              onClick={handleResetFilters}
              title="Reset all active filters"
            >
              Reset
            </button>
          )}

          <span className="req-results-counter">
            Showing <strong>{items.length}</strong> of {cardsPagination.total ?? items.length}
          </span>
        </div>
      </div>

      {/* Main Content: Card Grid */}
      <section className="req-catalog-wrapper" aria-label="Available Request Types Catalog">
        {loading && items.length === 0 ? (
          <div className="req-grid-loading">
            <div className="loading-spinner-wrap">
              <RotateCw size={26} className="spin-icon text-primary" />
              <span>Loading Available Request Catalog...</span>
            </div>
          </div>
        ) : error ? (
          <div className="req-grid-error" role="alert">
            <AlertCircle size={28} className="text-danger" />
            <div className="error-title">Failed to load request types</div>
            <p className="error-desc">{error}</p>
            <button type="button" className="btn btn-secondary" onClick={refresh}>
              Retry Connection
            </button>
          </div>
        ) : items.length === 0 ? (
          <div className="req-grid-empty">
            <Package size={40} className="empty-icon" />
            <h3>No Request Types Found</h3>
            <p>No catalog resources matched your active filters or search query.</p>
            {activeFiltersCount > 0 && (
              <button
                type="button"
                className="btn btn-secondary"
                onClick={handleResetFilters}
              >
                Clear All Filters
              </button>
            )}
          </div>
        ) : (
          <div className="req-types-card-grid">
            {items.map((item, index) => (
              <RequestTypeCard
                key={item.id || item.sku || index}
                item={item}
                isFirst={index === 0}
                onSelect={handleSelectItem}
                onRequestNow={handleRequestNow}
              />
            ))}
          </div>
        )}

        {/* Pagination Bar */}
        {cardsPagination.total > cardsPagination.per_page && (
          <PaginationBar
            meta={cardsPagination}
            onPageChange={setPage}
            label="request types"
          />
        )}
      </section>

      {/* Bottom-Right Circular Floating Action Button (FAB) */}
      {/* Matches user handwritten sketch: "button overlay at bottom right to filter request types" */}
      <aside className="req-fab-overlay-container">
        <button
          type="button"
          className={`req-fab-btn ${activeFiltersCount > 0 ? 'has-active-filters' : ''}`}
          onClick={() => setIsFilterDrawerOpen(true)}
          aria-label="Filter and Sort Request Types"
          title="Open Filter & Sort Drawer"
        >
          <SlidersHorizontal size={20} className="fab-icon" />
          {activeFiltersCount > 0 && (
            <span className="fab-badge" title={`${activeFiltersCount} filters active`}>
              {activeFiltersCount}
            </span>
          )}
        </button>
      </aside>

      {/* Filter Drawer Overlay */}
      <RequestTypeFilterDrawer
        isOpen={isFilterDrawerOpen}
        onClose={() => setIsFilterDrawerOpen(false)}
        categories={categories}
        activeCategory={category}
        onCategoryChange={(val) => {
          setCategory(val)
          setPage(1)
        }}
        activeStatus={status}
        onStatusChange={(val) => {
          setStatus(val)
          setPage(1)
        }}
        activeSort={sort}
        onSortChange={(val) => {
          setSort(val)
          setPage(1)
        }}
        searchTerm={search}
        onSearchChange={(val) => {
          setSearch(val)
          setPage(1)
        }}
        onResetFilters={handleResetFilters}
        totalResults={cardsPagination.total ?? items.length}
      />

      {/* Detailed Spec Drawer */}
      <RequestTypeDetailDrawer
        item={selectedItem}
        isOpen={isDetailOpen}
        onClose={() => {
          setIsDetailOpen(false)
          setSelectedItem(null)
        }}
        onRequestNow={handleRequestNow}
      />

      {/* Declare New Type Modal */}
      <DeclareRequestTypeModal
        isOpen={isDeclareModalOpen}
        onClose={() => setIsDeclareModalOpen(false)}
        onSuccess={handleDeclareSuccess}
      />

      {/* Interactive Resource Request Modal */}
      <RequestResourceModal
        item={requestModalItem}
        isOpen={isRequestModalOpen}
        onClose={() => {
          setIsRequestModalOpen(false)
          setRequestModalItem(null)
        }}
        onSuccess={handleRequestSuccess}
      />
    </main>
  )
}
