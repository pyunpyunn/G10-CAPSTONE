export function hasAdminWebAccess(user) {
  return user?.is_active !== false && ['super_admin', 'hq_leader'].includes(user?.role?.role_key)
}

export function hasWebAccess(user) {
  return user?.is_active !== false && (user?.permissions?.web_modules?.length || 0) > 0
}

export function canManagePersonnel(user) {
  return user?.is_active !== false && user?.permissions?.can_manage_personnel === true
}

export function webHomeForUser(user) {
  return hasWebAccess(user) ? '/dashboard' : '/login'
}

export function canOpenWebPage(user, path) {
  if (user?.is_active === false) return false
  const route = String(path || '').split('?')[0]
  let module
  if (route === '/super-admin') module = 'inquiries'
  else if (route === '/dashboard') module = 'dashboard'
  else if (route === '/notifications') module = 'notifications'
  else if (route === '/profile') module = 'profile'
  else if (route === '/rescuers' || route.startsWith('/rescuers/')) module = 'personnel_access'
  else if (['/mapping', '/weather', '/households'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'geospatial_analytics'
  else if (['/broadcast', '/dispatch', '/rescue-management', '/field-reports'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'response_operations'
  else if (['/request-types', '/external-requests', '/resources-requests'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'resources_requests'
  else if (route === '/situation' || route === '/archive' || route.startsWith('/archive/')) module = 'reports_documentation'

  return Boolean(module && user?.permissions?.web_modules?.includes(module))
}

export function canApproveRequests(user) {
  return user?.is_active !== false && ['super_admin', 'hq_leader'].includes(user?.role?.role_key)
}
