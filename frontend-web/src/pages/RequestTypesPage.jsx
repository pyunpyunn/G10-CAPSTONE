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
      <div className="workflow-page-wrap">
        {/* Operations Hero Header (Matches External Requests) */}
        <header className="workflow-hero-header">
          <div className="workflow-hero-left">
            <div className="workflow-hero-icon-box">
              <Boxes size={22} />
            </div>
            <div>
              <div className="workflow-hero-kicker">
                Resource Catalog & Depot Inventory
              </div>
              <h1 className="workflow-hero-title">
                Available Request Types
              </h1>
              <p className="workflow-hero-desc">
                Browse cataloged emergency relief items, verify depot inventory levels, or declare new resource types.
              </p>
            </div>
          </div>

          <div className="workflow-hero-actions">
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
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <RotateCw size={14} className={loading ? 'spin-icon' : ''} />
              <span>{loading ? 'Refreshing...' : 'Refresh'}</span>
            </button>

            <button
              type="button"
              className="btn btn-primary btn-header-declare"
              onClick={() => setIsDeclareModalOpen(true)}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <Plus size={15} />
              <span>Declare New Type</span>
            </button>
          </div>
        </header>

        {/* Success Notification Banner */}
        {successToast && (
          <div className="req-toast-banner" role="status">
            <Sparkles size={15} className="sparkle-icon" />
            <span>{successToast}</span>
          </div>
        )}

        {/* Executive KPI Stats Cards (Matches RequestWorkflowPage exactly) */}
        <section className="workflow-kpi-grid" aria-label="Catalog Quick Stats">
          {/* Card 1: Total Types */}
          <div className="workflow-kpi-card kpi-indigo">
            <div className="workflow-kpi-icon">
              <Layers size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">{summary.total_types ?? 0}</span>
              <span className="workflow-kpi-label">Total Types</span>
              <span className="workflow-kpi-sub">Emergency cataloged items</span>
            </div>
          </div>

          {/* Card 2: Available In Stock */}
          <div className="workflow-kpi-card kpi-emerald">
            <div className="workflow-kpi-icon">
              <CheckCircle2 size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">{summary.in_stock_count ?? 0}</span>
              <span className="workflow-kpi-label">Available In Stock</span>
              <span className="workflow-kpi-sub">Ready for allocation</span>
            </div>
          </div>

          {/* Card 3: Out of Stock */}
          <div className="workflow-kpi-card kpi-rose">
            <div className="workflow-kpi-icon">
              <XCircle size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">{summary.out_of_stock_count ?? 0}</span>
              <span className="workflow-kpi-label">Out of Stock</span>
              <span className="workflow-kpi-sub">Needs replenishment</span>
            </div>
          </div>

          {/* Card 4: Available Units */}
          <div className="workflow-kpi-card kpi-amber">
            <div className="workflow-kpi-icon">
              <Boxes size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">
                {Number(summary.total_available_units ?? 0).toLocaleString()}
              </span>
              <span className="workflow-kpi-label">Available Units</span>
              <span className="workflow-kpi-sub">Total stock in depot bays</span>
            </div>
          </div>
        </section>

        {/* Unified Filter & Search Toolbar (Matches RequestWorkflowPage) */}
        <section className="workflow-toolbar-box" aria-label="Search and Filter Controls">
          <div className="workflow-toolbar-top">
            {/* Search Input */}
            <div className="workflow-search-wrap">
              <Search size={15} className="workflow-search-icon" />
              <input
                type="text"
                className="workflow-search-input"
                placeholder="Search by name, SKU, or depot..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value)
                  setPage(1)
                }}
              />
              {search && (
                <button
                  type="button"
                  className="workflow-search-clear"
                  onClick={() => {
                    setSearch('')
                    setPage(1)
                  }}
                  aria-label="Clear search"
                >
                  <X size={12} />
                </button>
              )}
            </div>

            {/* Stock Status Channel Pills */}
            <div className="workflow-channel-pills">
              <button
                type="button"
                className={`wf-channel-btn ${status === 'all' ? 'active' : ''}`}
                onClick={() => {
                  setStatus('all')
                  setPage(1)
                }}
              >
                All Types <span style={{ opacity: 0.75, fontSize: '11px', marginLeft: '3px' }}>({summary.total_types ?? 0})</span>
              </button>
              <button
                type="button"
                className={`wf-channel-btn ${status === 'in_stock' ? 'active' : ''}`}
                onClick={() => {
                  setStatus('in_stock')
                  setPage(1)
                }}
              >
                <CheckCircle2 size={13} style={{ color: '#16a34a' }} />
                <span>In Stock</span>
                <span style={{ opacity: 0.75, fontSize: '11px', marginLeft: '3px' }}>({summary.in_stock_count ?? 0})</span>
              </button>
              <button
                type="button"
                className={`wf-channel-btn ${status === 'out_of_stock' ? 'active' : ''}`}
                onClick={() => {
                  setStatus('out_of_stock')
                  setPage(1)
                }}
              >
                <XCircle size={13} style={{ color: '#dc2626' }} />
                <span>Out of Stock</span>
                <span style={{ opacity: 0.75, fontSize: '11px', marginLeft: '3px' }}>({summary.out_of_stock_count ?? 0})</span>
              </button>
            </div>
          </div>

          {/* Secondary Controls Row */}
          <div className="workflow-toolbar-bottom">
            <div className="workflow-filter-selects">
              {/* Category Filter */}
              <label className="workflow-filter-label">
                <span>Category:</span>
                <select
                  className="workflow-filter-select"
                  value={category}
                  onChange={(e) => {
                    setCategory(e.target.value)
                    setPage(1)
                  }}
                  aria-label="Filter by category"
                >
                  {categories.map((cat) => (
                    <option key={cat.key} value={cat.key}>
                      {cat.label} {typeof cat.count === 'number' ? `(${cat.count})` : ''}
                    </option>
                  ))}
                </select>
              </label>

              {/* Sort Filter */}
              <label className="workflow-filter-label">
                <span>Sort by:</span>
                <select
                  className="workflow-filter-select"
                  value={sort}
                  onChange={(e) => {
                    setSort(e.target.value)
                    setPage(1)
                  }}
                  aria-label="Sort catalog items"
                >
                  <option value="newest">Newest First</option>
                  <option value="highest_stock">Highest Stock</option>
                  <option value="lowest_stock">Lowest Stock</option>
                  <option value="name_asc">Name (A-Z)</option>
                </select>
              </label>
            </div>

            {activeFiltersCount > 0 && (
              <button
                type="button"
                className="workflow-reset-btn"
                onClick={handleResetFilters}
                title="Reset all active filters"
              >
                <X size={12} /> Reset filters
              </button>
            )}

            <div style={{ marginLeft: 'auto', fontSize: '12px', color: '#64748b', fontWeight: 650 }}>
              Showing <strong style={{ color: '#0f172a' }}>{items.length}</strong> of {cardsPagination.total ?? items.length} types
            </div>
          </div>
        </section>

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
      </div>

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
