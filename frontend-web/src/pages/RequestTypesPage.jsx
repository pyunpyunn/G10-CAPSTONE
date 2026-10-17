import { useCallback, useState } from 'react'
import {
  Activity,
  ArrowRight,
  Boxes,
  Calendar,
  CheckCircle2,
  Eye,
  LifeBuoy,
  MapPin,
  Package,
  Plus,
  RotateCw,
  ShieldAlert,
} from 'lucide-react'
import { getResourceRequestTypes } from '../api/resourceRequestApi'
import { useModuleData } from '../utils/useModuleData'
import PageHeader from '../components/ui/PageHeader'
import DataFilterBar from '../components/ui/DataFilterBar'
import PaginationBar from '../components/ui/PaginationBar'
import Badge from '../components/ui/Badge'
import LoadingState from '../components/ui/LoadingState'
import EmptyState from '../components/ui/EmptyState'
import RequestTypeCard from '../components/resources/RequestTypeCard'
import RequestTypeDetailDrawer from '../components/resources/RequestTypeDetailDrawer'
import RequestTypeFilterDrawer from '../components/resources/RequestTypeFilterDrawer'
import DeclareRequestTypeModal from '../components/resources/DeclareRequestTypeModal'
import RequestResourceModal from '../components/resources/RequestResourceModal'

function getCategoryIcon(categoryKey = '') {
  const key = String(categoryKey).toLowerCase()
  if (key.includes('food') || key.includes('water')) return { icon: Package, pillClass: 'cat-pill-food' }
  if (key.includes('med') || key.includes('health')) return { icon: Activity, pillClass: 'cat-pill-medical' }
  if (key.includes('rescue') || key.includes('gear')) return { icon: LifeBuoy, pillClass: 'cat-pill-rescue' }
  if (key.includes('shelter') || key.includes('tent')) return { icon: ShieldAlert, pillClass: 'cat-pill-shelter' }
  return { icon: Boxes, pillClass: 'cat-pill-default' }
}

export default function RequestTypesPage() {
  // Pagination & Filter States
  const [page, setPage] = useState(1)
  const perPage = 24
  const [category, setCategory] = useState('all')
  const [status, setStatus] = useState('all')
  const [sort, setSort] = useState('newest')
  const [search, setSearch] = useState('')
  const [viewMode, setViewMode] = useState('cards') // 'cards' (List of Request Types) | 'inventory' (Request Type Inventory with Status)

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

  const filtersConfig = [
    {
      id: 'status',
      label: 'Stock Status',
      value: status,
      onChange: (value) => {
        setStatus(value)
        setPage(1)
      },
      options: [
        { value: 'all', label: 'All stock statuses' },
        { value: 'in_stock', label: 'In Stock' },
        { value: 'out_of_stock', label: 'Out of Stock' },
      ],
    },
    {
      id: 'category',
      label: 'Category',
      value: category,
      onChange: (value) => {
        setCategory(value)
        setPage(1)
      },
      options: categories.map((c) => ({
        value: c.key,
        label: c.count !== undefined ? `${c.label} (${c.count})` : c.label,
      })),
    },
    {
      id: 'sort',
      label: 'Sort',
      value: sort,
      onChange: (value) => {
        setSort(value)
        setPage(1)
      },
      options: [
        { value: 'newest', label: 'Newest First' },
        { value: 'highest_stock', label: 'Highest Stock' },
        { value: 'lowest_stock', label: 'Lowest Stock' },
        { value: 'name_asc', label: 'Name (A-Z)' },
      ],
    },
  ]

  return (
    <main className="ops-page resources-page">
      {/* ResQperation Native Page Header */}
      <PageHeader
        title="Request Types"
        subtitle={
          viewMode === 'cards'
            ? 'List of Request Types · Relief goods catalog and emergency dispatch assets'
            : 'Request Type Inventory with Status · Depot stock levels and reservation states'
        }
        actions={
          <div className="pg-actions">
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
              className="btn btn-secondary"
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
              className="button review"
              onClick={() => setIsDeclareModalOpen(true)}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <Plus size={15} />
              <span>Declare Request Type</span>
            </button>
          </div>
        }
      />

      {/* Success Notification Banner */}
      {successToast && (
        <div className="ra-success" role="status">
          <CheckCircle2 size={16} style={{ color: '#16a34a', flexShrink: 0 }} />
          <span>{successToast}</span>
        </div>
      )}

      {/* ResQperation Submodule Segmented Tabs (Native account-management-tabs) */}
      <div className="account-management-tabs" role="tablist" aria-label="Request types views">
        <button
          type="button"
          role="tab"
          aria-selected={viewMode === 'cards'}
          className={viewMode === 'cards' ? 'active' : ''}
          onClick={() => setViewMode('cards')}
        >
          List of Request Types
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={viewMode === 'inventory'}
          className={viewMode === 'inventory' ? 'active' : ''}
          onClick={() => setViewMode('inventory')}
        >
          Request Type Inventory with Status
        </button>
      </div>

      {/* Native ResQperation KPI Stat Row (.rr-stat-row) */}
      <div className="rr-stat-row">
        <div className="rr-stat">
          <div className="k">Total Types</div>
          <div className="v">{Number(summary.total_types ?? items.length).toLocaleString()}</div>
          <div className="n">cataloged emergency items</div>
        </div>
        <div className="rr-stat">
          <div className="k">Available in Stock</div>
          <div className="v">{Number(summary.in_stock_count ?? 0).toLocaleString()}</div>
          <div className="n">ready for allocation</div>
        </div>
        <div className="rr-stat">
          <div className="k">Out of Stock</div>
          <div className="v">{Number(summary.out_of_stock_count ?? 0).toLocaleString()}</div>
          <div className="n">needs depot replenishment</div>
        </div>
        <div className="rr-stat">
          <div className="k">Total Units</div>
          <div className="v">{Number(summary.total_available_units ?? 0).toLocaleString()}</div>
          <div className="n">depot warehouse stock</div>
        </div>
      </div>

      {/* Shared TanStack DataFilterBar */}
      <DataFilterBar
        search={search}
        onSearchChange={(value) => {
          setSearch(value)
          setPage(1)
        }}
        searchPlaceholder="Search request types by name, SKU, or depot..."
        filters={filtersConfig}
        onReset={handleResetFilters}
      />

      {/* Main Content: Native ResQperation Panel */}
      <section
        className="panel"
        aria-label={viewMode === 'cards' ? 'List of Request Types' : 'Request Type Inventory with Status'}
      >
        <div className="panel-head">
          <span className="panel-title">
            {viewMode === 'cards' ? 'List of Request Types' : 'Request Type Inventory with Status'}
          </span>
          <span className="rr-subtle">
            {items.length} of {cardsPagination.total ?? items.length} types
          </span>
        </div>

        {loading && items.length === 0 ? (
          <div style={{ padding: '36px 16px', textAlign: 'center' }}>
            <LoadingState label="Loading request types catalog..." />
          </div>
        ) : error ? (
          <div style={{ padding: '36px 16px', textAlign: 'center' }}>
            <div className="form-error" role="alert" style={{ maxWidth: '400px', margin: '0 auto 12px' }}>
              {error}
            </div>
            <button type="button" className="btn btn-secondary" onClick={refresh}>
              Retry Connection
            </button>
          </div>
        ) : items.length === 0 ? (
          <div style={{ padding: '36px 16px', textAlign: 'center' }}>
            <EmptyState
              title="No request types found"
              message="No catalog resources matched your active filters or search query."
            />
          </div>
        ) : viewMode === 'cards' ? (
          <div style={{ padding: '16px' }}>
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
          </div>
        ) : (
          <div className="tbl-wrap compact-table">
            <table>
              <thead>
                <tr>
                  <th>SKU / Code</th>
                  <th>Relief Resource Item</th>
                  <th>Category</th>
                  <th>Classification</th>
                  <th>Depot Location</th>
                  <th>Current Stock</th>
                  <th>Availability</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item, index) => {
                  const isOutOfStock = Boolean(item.is_out_of_stock || item.quantity <= 0)
                  const catMeta = getCategoryIcon(item.category_key || item.category)
                  const CatIcon = catMeta.icon
                  return (
                    <tr
                      key={item.id || item.sku || index}
                      onClick={() => handleSelectItem(item)}
                      style={{ cursor: 'pointer' }}
                    >
                      <td>
                        <strong style={{ fontFamily: 'monospace', color: 'var(--color-text-1)' }}>
                          {item.sku || `ITEM-${item.id}`}
                        </strong>
                      </td>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <div
                            className={`card-category-avatar ${catMeta.pillClass}`}
                            style={{ width: '28px', height: '28px', borderRadius: '6px', flexShrink: 0 }}
                          >
                            <CatIcon size={14} />
                          </div>
                          <div>
                            <div style={{ fontWeight: 650, color: 'var(--color-text-1)', fontSize: '13px' }}>
                              {item.name || item.label}
                            </div>
                            {item.expiration_date && (
                              <div style={{ fontSize: '11px', color: '#b45309', display: 'flex', alignItems: 'center', gap: '4px' }}>
                                <Calendar size={10} />
                                <span>Exp: {item.expiration_date}</span>
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td>
                        <span className="channel-chip hq">
                          {item.category_label || item.category || 'Relief Goods'}
                        </span>
                      </td>
                      <td>
                        <span style={{ fontSize: '12px', color: 'var(--color-text-2)', fontWeight: 550 }}>
                          {item.type || 'Consumable'}
                        </span>
                      </td>
                      <td>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '12px', color: 'var(--color-text-3)' }}>
                          <MapPin size={11} style={{ flexShrink: 0 }} />
                          <span>{item.storage_location || 'Warehouse Depot'}</span>
                        </div>
                      </td>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: '4px' }}>
                          <strong style={{ fontSize: '14px', color: isOutOfStock ? 'var(--color-text-3)' : 'var(--color-text-1)' }}>
                            {isOutOfStock ? 0 : Number(item.quantity).toLocaleString()}
                          </strong>
                          <span style={{ fontSize: '11px', color: 'var(--color-text-3)' }}>
                            {item.unit || 'units'}
                          </span>
                        </div>
                      </td>
                      <td>
                        {isOutOfStock ? (
                          <Badge tone="danger">Out of Stock</Badge>
                        ) : Number(item.quantity) <= 20 ? (
                          <Badge tone="warning">Low Stock ({item.quantity})</Badge>
                        ) : (
                          <Badge tone="success">Available</Badge>
                        )}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', justifyContent: 'flex-end' }}>
                          <button
                            type="button"
                            className="button secondary"
                            style={{ padding: '4px 8px', fontSize: '11.5px' }}
                            onClick={(e) => {
                              e.stopPropagation()
                              handleSelectItem(item)
                            }}
                            title="View resource specifications"
                          >
                            <Eye size={12} />
                            <span>Specs</span>
                          </button>

                          {isOutOfStock ? (
                            <span style={{ fontSize: '11.5px', color: 'var(--color-text-3)', fontStyle: 'italic', padding: '4px 8px' }}>
                              Unavailable
                            </span>
                          ) : (
                            <button
                              type="button"
                              className="button review"
                              style={{ padding: '4px 10px', fontSize: '11.5px' }}
                              onClick={(e) => {
                                e.stopPropagation()
                                handleRequestNow(item)
                              }}
                              title="Create a resource request for this item"
                            >
                              <span>Request</span>
                              <ArrowRight size={12} />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {cardsPagination.total > cardsPagination.per_page && (
          <div style={{ padding: '12px 18px', borderTop: '1px solid var(--color-border-subtle, #f1f5f9)' }}>
            <PaginationBar
              meta={cardsPagination}
              onPageChange={setPage}
              label="request types"
            />
          </div>
        )}
      </section>

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
      {isRequestModalOpen && requestModalItem && <RequestResourceModal
        item={requestModalItem}
        isOpen={isRequestModalOpen}
        onClose={() => {
          setIsRequestModalOpen(false)
          setRequestModalItem(null)
        }}
        onSuccess={handleRequestSuccess}
      />}
    </main>
  )
}
