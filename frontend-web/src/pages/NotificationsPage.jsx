import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  clearNotifications,
  deleteSelectedNotifications,
  subscribeNotifications,
  markNotificationsRead,
} from '../api/notificationApi'
import NotificationList from '../components/notifications/NotificationList'
import NotificationToolbar from '../components/notifications/NotificationToolbar'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  deleteItems,
  markItemsRead,
  notificationErrorMessage,
  notificationParams,
} from '../utils/notificationHelpers'

export default function NotificationsPage() {
  const navigate = useNavigate()
  const [hasLoaded, setHasLoaded] = useState(false)
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [items, setItems] = useState([])
  const [, setSummary] = useState({})
  const [pagination, setPagination] = useState({})
  const [scopeNote, setScopeNote] = useState('')
  const [selectedIds, setSelectedIds] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    if (isSaving) return undefined
    return subscribeNotifications(notificationParams(statusFilter, page), (data) => {
      const nextItems = data.notifications?.data || []
      setHasLoaded(true)
      setItems(nextItems)
      setSummary(data.summary || {})
      setPagination(data.notifications || {})
      setScopeNote(data.scope_note || '')
      setSelectedIds((ids) => ids.filter((id) => nextItems.some((item) => item.id === id)))
      setError('')
      setIsLoading(false)
      if (data.notifications?.current_page && data.notifications.current_page !== page) {
        setPage(data.notifications.current_page)
      }
    }, (loadError) => {
      setError(notificationErrorMessage(loadError))
      setIsLoading(false)
    })
  }, [statusFilter, page, isSaving])

  function changeFilter(nextFilter) {
    setStatusFilter(nextFilter)
    setPage(1)
    setMessage('')
    setSelectedIds([])
  }

  function toggleSelected(id, checked) {
    setSelectedIds((currentIds) => {
      if (checked) {
        return currentIds.includes(id) ? currentIds : [...currentIds, id]
      }

      return currentIds.filter((currentId) => currentId !== id)
    })
  }

  async function markAllRead() {
    setIsSaving(true)
    setMessage('')

    try {
      await markNotificationsRead([])
      setItems((currentItems) => markItemsRead(currentItems))
      setSummary((currentSummary) => ({
        ...currentSummary,
        unread: 0,
        selected: selectedIds.length,
      }))
      setMessage('Notifications marked as read in the current HQ view.')
    } catch (saveError) {
      setMessage(notificationErrorMessage(saveError, 'Unable to mark notifications as read.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function deleteSelected() {
    if (selectedIds.length === 0) {
      setMessage('Select at least one notification first.')
      return
    }

    setIsSaving(true)
    setMessage('')

    try {
      await deleteSelectedNotifications(selectedIds)
      const selectedSet = new Set(selectedIds)
      const selectedItems = items.filter((item) => selectedSet.has(item.id))
      const unreadRemoved = selectedItems.filter((item) => !item.read).length
      const criticalRemoved = selectedItems.filter((item) => ['Critical', 'High'].includes(item.priority)).length

      setItems((currentItems) => deleteItems(currentItems, selectedIds))
      setSummary((currentSummary) => ({
        ...currentSummary,
        total: Math.max(0, (currentSummary.total || 0) - selectedIds.length),
        unread: Math.max(0, (currentSummary.unread || 0) - unreadRemoved),
        critical: Math.max(0, (currentSummary.critical || 0) - criticalRemoved),
        selected: 0,
      }))
      setSelectedIds([])
      setMessage('Selected notification(s) hidden from this HQ view.')
    } catch (saveError) {
      setMessage(notificationErrorMessage(saveError, 'Unable to delete selected notifications.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function clearAll() {
    setIsSaving(true)
    setMessage('')

    try {
      await clearNotifications()
      setItems([])
      setSummary({
        total: 0,
        unread: 0,
        critical: 0,
        selected: 0,
      })
      setPagination({
        current_page: 1,
        page_count: 1,
        total: 0,
      })
      setSelectedIds([])
      setMessage('Notifications cleared from this HQ view.')
    } catch (saveError) {
      setMessage(notificationErrorMessage(saveError, 'Unable to clear notifications.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function openNotification(item) {
    try {
      await markNotificationsRead([item.id])
      navigate(item.action_url || '/notifications')
    } catch (saveError) {
      setMessage(notificationErrorMessage(saveError, 'Unable to mark this notification as read.'))
    }
  }

  return (
    <section className="page active notifications-page">
      <PageHeader
        title="Notifications"
        filters={(
          <NotificationToolbar
            statusFilter={statusFilter}
            onFilterChange={changeFilter}
            onMarkAllRead={markAllRead}
            onDeleteSelected={deleteSelected}
            onClearAll={clearAll}
            disabled={isSaving}
          />
        )}
      />

      {message && <div className="notification-page-message">{message}</div>}
      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && (!error || hasLoaded) && (
        <>
          {scopeNote && <div className="notification-scope-note">{scopeNote}</div>}
          <NotificationList
            notifications={items}
            selectedIds={selectedIds}
            pagination={pagination}
            onToggleSelected={toggleSelected}
            onOpenNotification={openNotification}
            onPrevious={() => setPage((currentPage) => Math.max(1, currentPage - 1))}
            onNext={() => setPage((currentPage) => currentPage + 1)}
          />
        </>
      )}
    </section>
  )
}
