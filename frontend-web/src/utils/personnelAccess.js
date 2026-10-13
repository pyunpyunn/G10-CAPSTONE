export function hasAdminWebAccess(user) {
  return ['super_admin', 'admin'].includes(user?.role?.role_key)
}

export function canManagePersonnel(user) {
  return user?.is_active !== false && (hasAdminWebAccess(user) || user?.permissions?.can_manage_personnel === true)
}

export function webHomeForUser(user) {
  return hasAdminWebAccess(user) ? '/dashboard' : canManagePersonnel(user) ? '/rescuers' : '/login'
}

export function canOpenWebPage(user, path) {
  return path === '/rescuers' || path.startsWith('/rescuers/') ? canManagePersonnel(user) : hasAdminWebAccess(user)
}
