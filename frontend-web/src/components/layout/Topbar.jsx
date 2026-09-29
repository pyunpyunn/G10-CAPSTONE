import { Bell, CheckCircle2, Eye, Filter, Inbox, Search, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { searchGlobalRecords } from '../../api/globalSearchApi'
import { getNotifications, markNotificationsRead } from '../../api/notificationApi'

const searchTypes = [
  { id: 'pages', label: 'Pages' },
  { id: 'events', label: 'Disasters' },
  { id: 'broadcasts', label: 'Broadcasts' },
  { id: 'households', label: 'Households' },
  { id: 'responders', label: 'Responders' },
  { id: 'dispatches', label: 'Dispatches' },
  { id: 'resources', label: 'Resource requests' },
  { id: 'sitreps', label: 'Situation reports' },
]

const searchablePages = [
  { title: 'Inquiries', href: '/super-admin', keywords: 'super admin inquiries access', superAdminOnly: true },
  { title: 'Dashboard', href: '/dashboard', keywords: 'overview command home' },
  { title: 'Disaster Broadcasting', href: '/broadcast', keywords: 'alerts messages disasters' },
  { title: 'Weather Updates', href: '/weather', keywords: 'weather pagasa forecast' },
  { title: 'Mapping', href: '/mapping', keywords: 'map routes evacuation centers' },
  { title: 'Household Status', href: '/households', keywords: 'residents family safety' },
  { title: 'Rescue Dispatch', href: '/dispatch', keywords: 'teams rescue assignments' },
  { title: 'Rescuer Accounts', href: '/rescuers', keywords: 'responders personnel' },
  { title: 'Resources & Requests', href: '/resources-requests', keywords: 'supplies inventory requests' },
  { title: 'Situation Reporting', href: '/situation', keywords: 'sitrep reports' },
  { title: 'Archive', href: '/archive', keywords: 'history records logs' },
  { title: 'Notifications', href: '/notifications', keywords: 'alerts inbox' },
  { title: 'Profile', href: '/profile', keywords: 'account settings' },
]

export default function Topbar({ user, onMouseEnter, onMouseLeave, onFocusCapture, onBlurCapture }) {
  const navigate = useNavigate()
  const [isOpen, setIsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [searchTerm, setSearchTerm] = useState('')
  const [recordResults, setRecordResults] = useState([])
  const [selectedTypes, setSelectedTypes] = useState(() => searchTypes.map((type) => type.id))
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [isFilterOpen, setIsFilterOpen] = useState(false)
  const [isSearching, setIsSearching] = useState(false)
  const [searchError, setSearchError] = useState('')
  const popoverRef = useRef(null)
  const bellButtonRef = useRef(null)
  const searchControlsRef = useRef(null)
  const searchInputRef = useRef(null)
  const roleName = user?.role?.role_name || 'HQ'
  const displayName = user?.full_name || 'HQ Admin'
  const initials = getInitials(user?.full_name)
  const unreadLabel = unreadCount > 99 ? '99+' : String(unreadCount)
  const canViewInquiries = user?.role?.role_key === 'super_admin'
  const pageResults = searchTerm.trim().length >= 2 && selectedTypes.includes('pages')
    ? searchablePages
      .filter((page) => (!page.superAdminOnly || canViewInquiries))
      .filter((page) => `${page.title} ${page.keywords}`.toLowerCase().includes(searchTerm.trim().toLowerCase()))
      .map((page) => ({ ...page, type: 'pages', subtitle: 'Open page' }))
    : []
  const searchResults = [...pageResults, ...recordResults]

  useEffect(() => {
    const query = searchTerm.trim()
    const types = selectedTypes.filter((type) => type !== 'pages')

    if (query.length < 2 || types.length === 0) {
      setRecordResults([])
      setSearchError('')
      setIsSearching(false)
      return undefined
    }

    let ignore = false
    const timeout = window.setTimeout(async () => {
      setIsSearching(true)
      setSearchError('')

      try {
        const results = await searchGlobalRecords(query, types)

        if (!ignore) {
          setRecordResults(results)
        }
      } catch {
        if (!ignore) {
          setRecordResults([])
          setSearchError('Search is unavailable right now.')
        }
      } finally {
        if (!ignore) {
          setIsSearching(false)
        }
      }
    }, 220)

    return () => {
      ignore = true
      window.clearTimeout(timeout)
    }
  }, [searchTerm, selectedTypes])

  useEffect(() => {
    if (!isSearchOpen && !isFilterOpen) {
      return undefined
    }

    function closeSearchControls(event) {
      if (!searchControlsRef.current?.contains(event.target)) {
        setIsSearchOpen(false)
        setIsFilterOpen(false)
      }
    }

    function closeSearchOnEscape(event) {
      if (event.key === 'Escape') {
        setIsSearchOpen(false)
        setIsFilterOpen(false)
        searchInputRef.current?.blur()
      }
    }

    document.addEventListener('pointerdown', closeSearchControls)
    document.addEventListener('keydown', closeSearchOnEscape)

    return () => {
      document.removeEventListener('pointerdown', closeSearchControls)
      document.removeEventListener('keydown', closeSearchOnEscape)
    }
  }, [isFilterOpen, isSearchOpen])

  useEffect(() => {
    let ignore = false

    async function loadPreview() {
      try {
        const data = await getNotifications({ status: 'all', page: 1 })

        if (!ignore) {
          setNotifications(data.preview || [])
          setUnreadCount(data.summary?.unread || 0)
        }
      } catch {
        if (!ignore) {
          setNotifications([])
          setUnreadCount(0)
        }
      }
    }

    async function refreshPreview() {
      try {
        const data = await getNotifications({ status: 'all', page: 1 })

        setNotifications(data.preview || [])
        setUnreadCount(data.summary?.unread || 0)
      } catch {
        setNotifications([])
        setUnreadCount(0)
      }
    }

    loadPreview()
    window.addEventListener('notifications:changed', refreshPreview)

    return () => {
      ignore = true
      window.removeEventListener('notifications:changed', refreshPreview)
    }
  }, [])

  useEffect(() => {
    if (!isOpen) {
      return undefined
    }

    function closeOnOutsideClick(event) {
      const clickedPopover = popoverRef.current?.contains(event.target)
      const clickedBell = bellButtonRef.current?.contains(event.target)

      if (!clickedPopover && !clickedBell) {
        setIsOpen(false)
      }
    }

    function closeOnEscape(event) {
      if (event.key === 'Escape') {
        setIsOpen(false)
        bellButtonRef.current?.focus()
      }
    }

    document.addEventListener('pointerdown', closeOnOutsideClick)
    document.addEventListener('keydown', closeOnEscape)

    return () => {
      document.removeEventListener('pointerdown', closeOnOutsideClick)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [isOpen])

  async function markAllRead() {
    try {
      await markNotificationsRead([])
      window.dispatchEvent(new Event('notifications:changed'))
    } finally {
      setNotifications((currentItems) => currentItems.map((item) => ({ ...item, read: true })))
      setUnreadCount(0)
    }
  }

  function openNotificationsPage() {
    setIsOpen(false)
    navigate('/notifications')
  }

  function openInquiryPage() {
    navigate('/super-admin')
  }

  function openSearchResult(result) {
    setIsSearchOpen(false)
    setIsFilterOpen(false)
    setSearchTerm('')
    navigate(result.href)
  }

  function toggleSearchType(typeId) {
    setSelectedTypes((current) => current.includes(typeId)
      ? current.filter((type) => type !== typeId)
      : [...current, typeId])
  }

  async function openNotification(item) {
    try {
      await markNotificationsRead([item.id])
      window.dispatchEvent(new Event('notifications:changed'))
    } finally {
      setIsOpen(false)
      navigate(item.action_url || '/notifications')
    }
  }

  return (
    <header
      className="topbar"
      onMouseEnter={onMouseEnter}
      onMouseLeave={onMouseLeave}
      onFocusCapture={onFocusCapture}
      onBlurCapture={onBlurCapture}
    >
      <div className="topbar-search-controls" ref={searchControlsRef}>
        <label className="topbar-search-field">
          <Search size={16} aria-hidden="true" />
          <span className="sr-only">Search all modules</span>
          <input
            ref={searchInputRef}
            type="search"
            value={searchTerm}
            placeholder="Search all modules"
            aria-label="Search all modules"
            aria-expanded={isSearchOpen}
            aria-controls="topbar-search-results"
            onFocus={() => setIsSearchOpen(true)}
            onChange={(event) => {
              setSearchTerm(event.target.value)
              setIsSearchOpen(true)
            }}
            onKeyDown={(event) => {
              if (event.key === 'Enter' && searchResults[0]) {
                openSearchResult(searchResults[0])
              }
            }}
          />
          {searchTerm && (
            <button
              className="topbar-search-clear"
              type="button"
              aria-label="Clear search"
              onClick={() => {
                setSearchTerm('')
                searchInputRef.current?.focus()
              }}
            >
              <X size={14} />
            </button>
          )}
        </label>
        <button
          className="header-action topbar-filter-button"
          type="button"
          title="Filter search results"
          aria-label="Filter search results"
          aria-haspopup="dialog"
          aria-expanded={isFilterOpen}
          onClick={() => {
            setIsFilterOpen((open) => !open)
            setIsSearchOpen(false)
          }}
        >
          <Filter size={16} />
          {selectedTypes.length < searchTypes.length && <span className="topbar-filter-indicator" aria-hidden="true" />}
        </button>

        {isSearchOpen && (
          <div className="topbar-search-popover" id="topbar-search-results" role="listbox" aria-label="Search results">
            {searchTerm.trim().length < 2 ? (
              <div className="topbar-search-empty">Type at least 2 characters to search.</div>
            ) : isSearching ? (
              <div className="topbar-search-empty">Searching records...</div>
            ) : searchError ? (
              <div className="topbar-search-empty" role="status">{searchError}</div>
            ) : searchResults.length === 0 ? (
              <div className="topbar-search-empty">No matching pages or records.</div>
            ) : (
              searchResults.map((result, index) => (
                <button
                  className="topbar-search-result"
                  type="button"
                  role="option"
                  aria-selected="false"
                  key={`${result.type}-${result.href}-${result.title}-${index}`}
                  onClick={() => openSearchResult(result)}
                >
                  <span className="topbar-search-result-copy">
                    <strong>{result.title}</strong>
                    <span>{result.subtitle}</span>
                  </span>
                  <span className="topbar-search-result-type">{searchTypes.find((type) => type.id === result.type)?.label}</span>
                </button>
              ))
            )}
          </div>
        )}

        {isFilterOpen && (
          <div className="topbar-filter-popover" role="dialog" aria-label="Search result types">
            <div className="topbar-filter-heading">Search in</div>
            {searchTypes.map((type) => (
              <label className="topbar-filter-option" key={type.id}>
                <input
                  type="checkbox"
                  checked={selectedTypes.includes(type.id)}
                  onChange={() => toggleSearchType(type.id)}
                />
                <span>{type.label}</span>
              </label>
            ))}
          </div>
        )}
      </div>

      <div className="header-actions" aria-label="Header actions">
        {canViewInquiries && (
          <button
            className="header-action"
            type="button"
            title="Inquiries"
            aria-label="Open admin inquiries"
            onClick={openInquiryPage}
          >
            <Inbox size={16} />
          </button>
        )}

        <button
          className="header-action"
          ref={bellButtonRef}
          type="button"
          title="Notifications"
          aria-label={`${unreadLabel} unread notifications`}
          aria-haspopup="dialog"
          aria-expanded={isOpen}
          onClick={() => setIsOpen(!isOpen)}
        >
          <Bell size={17} />
          {unreadCount > 0 && <span className="notification-badge" aria-hidden="true">{unreadLabel}</span>}
        </button>

        <button
          className="header-profile"
          type="button"
          title="Profile"
          aria-label="Profile"
          onClick={() => navigate('/profile')}
        >
          <span className="profile-avatar">{initials}</span>
          <span className="profile-copy">
            <span className="profile-label">{displayName}</span>
            <span className="profile-role-label">{roleName}</span>
          </span>
        </button>
      </div>

      {isOpen && (
        <div className="notification-popover" ref={popoverRef} role="dialog" aria-label="Recent notifications">
          <div className="notification-popover-head">
            <div className="notification-popover-title">
              <Bell size={16} />
              Notifications
            </div>
            <span className="notification-count-pill">{unreadCount} unread</span>
          </div>
          <div className="notification-popover-list">
            {notifications.length === 0 ? (
              <div className="notification-empty">
                <strong>No notifications yet</strong>
                <span>Actionable alerts will appear here.</span>
              </div>
            ) : (
              notifications.map((item) => (
                <button
                  className={`notification-preview-item ${item.read ? 'is-read' : ''}`}
                  key={item.id}
                  type="button"
                  onClick={() => openNotification(item)}
                >
                  <span className="notification-preview-dot" aria-hidden="true" />
                  <div>
                    <div className="notification-preview-title">{item.title}</div>
                    <div className="notification-preview-body">{item.body}</div>
                    <div className="notification-preview-time">{item.time} - {item.type}</div>
                  </div>
                </button>
              ))
            )}
          </div>
          <div className="notification-popover-actions">
            <button className="btn btn-secondary btn-sm" type="button" onClick={markAllRead}>
              <CheckCircle2 size={14} />
              Mark as read
            </button>
            <button className="btn btn-primary btn-sm" type="button" onClick={openNotificationsPage}>
              <Eye size={14} />
              View all
            </button>
          </div>
        </div>
      )}
    </header>
  )
}

function getInitials(name = '') {
  const parts = name.trim().split(' ').filter(Boolean)
  const initials = parts.map((part) => part[0]).join('').slice(0, 2)
  return initials || 'HQ'
}
