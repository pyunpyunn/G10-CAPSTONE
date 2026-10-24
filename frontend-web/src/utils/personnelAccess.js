export function hasAdminWebAccess(user) {
  return user?.is_active !== false && (user?.permissions?.can_manage_personnel ?? user?.role?.role_key === 'operations_admin')
}

export function hasWebAccess(user) {
  return user?.is_active !== false && (user?.permissions?.web_modules?.length || 0) > 0
}

export function canManagePersonnel(user) {
  return user?.is_active !== false && user?.permissions?.can_manage_personnel === true
}

export function webHomeForUser(user) {
  const homes=[['dashboard','/dashboard'],['geospatial_analytics','/mapping'],['response_operations','/rescue-management'],['resources_requests','/resources-requests'],['reports_documentation','/situation'],['personnel_access','/rescuers'],['profile','/profile'],['notifications','/notifications']]
  return user?.is_active===false ? '/login' : homes.find(([key]) => user?.permissions?.web_modules?.includes(key))?.[1] || '/login'
}

export function canOpenWebPage(user, path) {
  if (user?.is_active === false) return false
  const route = String(path || '').split('?')[0]
  if (route === '/rescuers/view') return hasWebAccess(user)
  let module
  if (route === '/super-admin') module = 'inquiries'
  else if (route === '/dashboard') module = 'dashboard'
  else if (route === '/notifications') module = 'notifications'
  else if (route === '/profile') module = 'profile'
  else if (route === '/rescuers' || route.startsWith('/rescuers/')) module = 'personnel_access'
  else if (['/mapping', '/weather', '/households'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'geospatial_analytics'
  else if (['/broadcast', '/dispatch', '/rescue-management', '/field-reports'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'response_operations'
  else if (['/request-types', '/external-requests', '/resources-requests'].some(prefix => route === prefix || route.startsWith(`${prefix}/`))) module = 'resources_requests'
  else if ((route === '/situation' || route.startsWith('/situation/')) || route === '/archive' || route.startsWith('/archive/')) module = 'reports_documentation'

  return Boolean(module && user?.permissions?.web_modules?.includes(module))
}

export function canApproveRequests(user) {
  return user?.is_active !== false && (user?.permissions?.can_approve_requests ?? user?.role?.role_key === 'chair')
}
