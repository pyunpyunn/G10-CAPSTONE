export function notificationParams(statusFilter, page = 1) {
  return {
    status: statusFilter,
    page,
  }
}

export function notificationErrorMessage(error, fallback = 'Notifications cannot be loaded right now.') {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : fallback
  }

  return data?.message || fallback
}

export function groupNotifications(items = []) {
  return items.reduce((groups, item) => {
    const date = item.date || 'No date'

    if (!groups[date]) {
      groups[date] = []
    }

    groups[date].push(item)
    return groups
  }, {})
}

export function summaryFromItems(items = [], selectedIds = []) {
  return {
    total: items.length,
    unread: items.filter((item) => !item.read).length,
    critical: items.filter((item) => ['Critical', 'High'].includes(item.priority)).length,
    selected: selectedIds.length,
  }
}

export function markItemsRead(items = [], ids = []) {
  const idSet = new Set(ids)
  const markAll = ids.length === 0

  return items.map((item) => (markAll || idSet.has(item.id) ? { ...item, read: true } : item))
}

export function deleteItems(items = [], ids = []) {
  const idSet = new Set(ids)

  return items.filter((item) => !idSet.has(item.id))
}
