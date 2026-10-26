import { lazy, Suspense, useEffect, useState } from 'react'
import {
  BrowserRouter,
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
  useOutletContext,
} from 'react-router-dom'
import AppShell from './components/layout/AppShell'
import LoginPage from './pages/LoginPage'
import PlaceholderPage from './pages/PlaceholderPage'
import HouseholdReviewPage from './pages/HouseholdReviewPage'
import { getCurrentUser, loginUser, logoutUser, saveRememberedPassword } from './api/authApi'
import { clearToken, getToken, saveToken } from './api/token'
import { clearSessionCache, readSessionCache, removeSessionCache, writeSessionCache } from './api/sessionCache'
import { canOpenWebPage, hasWebAccess, webHomeForUser } from './utils/personnelAccess'
import './App.css'
import './styles/foundation.css'
import './styles/index.css'
import './styles/typography.css'
import './styles/layout.css'
import './styles/main.css'
import './styles/dashboard.css'
import './styles/notifications.css'
import './styles/profile.css'
import './styles/personnel-profile.css'
import './styles/operations.css'
import './styles/archive.css'
import './styles/ui.css'
import './styles/auth.css'
import './styles/households.css'
import './styles/weather.css'
import './styles/broadcast.css'
import './styles/mapping.css'
import './styles/dispatch.css'
import './styles/situation.css'
import './styles/responsive.css'
import './styles/mapmate-reference.css'
import './styles/map-enhancements.css'
import './styles/household-prototype.css'
import './styles/mapping-reference.css'
import './styles/weather-reference.css'
import './styles/status-colors.css'
import './styles/situation-reference.css'
import './styles/situation-workspace.css'
import './styles/annex-h.css'

const pageComponents = {
  '/super-admin': lazy(() => import('./pages/SuperAdminPage')),
  '/dashboard': lazy(() => import('./pages/DashboardPage')),
  '/broadcast': lazy(() => import('./pages/BroadcastPage')),
  '/weather': lazy(() => import('./pages/WeatherPage')),
  '/mapping': lazy(() => import('./pages/MappingPage')),
  '/households': lazy(() => import('./pages/HouseholdStatusPage')),
  '/households/_household-list': lazy(() => import('./pages/HouseholdPurokListPage')),
  '/households/_data-directory': lazy(() => import('./pages/HouseholdDataDirectoryPage')),
  '/rescue-management': lazy(() => import('./pages/RescueManagementPage')),
  '/field-reports': lazy(() => import('./pages/FieldReportsPage')),
  '/dispatch/communication': lazy(() => import('./pages/FieldCommunicationPage')),
  '/request-types': lazy(() => import('./pages/RequestTypesPage')),
  '/external-requests': lazy(() => import('./pages/RequestWorkflowPage')),
  '/dispatch': lazy(() => import('./pages/RescueDispatchPage')),
  '/dispatch/new': lazy(() => import('./pages/NewDispatchPage')),
  '/rescuers': lazy(() => import('./pages/RescuerAccountsPage')),
  '/rescuers/new': lazy(() => import('./pages/RescuerAccountsPage')),
  '/rescuers/teams': lazy(() => import('./pages/RescuerAccountsPage')),
  '/rescuers/view': lazy(() => import('./pages/PersonnelProfilePage')),
  '/rescuers/edit': lazy(() => import('./pages/RescuerAccountsPage')),
  '/resources-requests': lazy(() => import('./pages/RequestWorkflowPage')),
  '/resources-requests/new': lazy(() => import('./pages/ResourceRequestEditorPage')),
  '/resources-requests/edit': lazy(() => import('./pages/ResourceRequestEditorPage')),
  '/situation': lazy(() => import('./pages/SituationReportPage')),
  '/situation/external-reports': lazy(() => import('./pages/ExternalReportsPage')),
  '/archive': lazy(() => import('./pages/ArchivePage')),
  '/notifications': lazy(() => import('./pages/NotificationsPage')),
  '/profile': lazy(() => import('./pages/ProfilePage')),
}

const modulePages = [
  {
    path: '/super-admin',
    title: 'Inquiries',
    kicker: 'Operations/Admin',
    summary: 'Review public inquiries and manage technical account access.',
    superOnly: true,
    navHidden: true,
  },
  {
    path: '/dashboard',
    title: 'Dashboard',
    kicker: 'Command overview',
    summary: 'Active event, household reporting progress, dispatch status, weather, requests, and recent activity.',
  },
  {
    path: '/broadcast',
    title: 'Disaster Broadcasting',
    kicker: 'Alerts',
    summary: 'Create disaster events and send official barangay or purok-specific instructions.',
    navHidden: true,
  },
  {
    path: '/weather',
    title: 'Weather Updates',
    kicker: 'Monitoring',
    summary: 'Display saved PAGASA/Open-Meteo snapshots fetched by Laravel for archive and reports.',
  },
  {
    path: '/mapping',
    title: 'Mapping',
    kicker: 'Geotagging',
    summary: 'View household status points, evacuation centers, dispatch markers, and route lines.',
  },
  {
    path: '/households',
    title: 'Household Status',
    kicker: 'Reports only',
    summary: 'Review latest household reports, members, devices, battery level, and last known location.',
  },
  {
    path: '/dispatch',
    title: 'Dispatch Dashboard',
    kicker: 'Operations',
    summary: 'Assign teams, monitor progress, and record field outcomes from rescuer updates.',
  },
  {
    path: '/resources-requests',
    title: 'Request Monitoring',
    kicker: 'Validation queue',
    summary: 'Validate EvaTrack/manual requests before forwarding verified records to TrackingAid/HQ.',
  },
  {
    path: '/rescuers',
    title: 'Account Management',
    kicker: 'Verified accounts',
    summary: 'Manage field-team and BDRRMC committee accounts.',
  },
  {
    path: '/rescuers/teams',
    title: 'Team Management',
    summary: 'Configure rescue teams and their members.',
  },
  {
    path: '/rescue-management',
    title: 'Rescue Management',
    summary: 'Review and manage saved operational records.',
  },
  {
    path: '/field-reports',
    title: 'Field Reports',
    summary: 'Review and manage saved operational records.',
  },
  {
    path: '/request-types',
    title: 'Request Types',
    summary: 'Review and manage saved operational records.',
  },
  {
    path: '/external-requests',
    title: 'External Requests',
    summary: 'Review and manage saved operational records.',
  },
  {
    path: '/situation',
    title: 'Situation Reporting',
    kicker: 'Reports',
    summary: 'Generate saved event snapshots for household, dispatch, resources, weather, and casualty summaries.',
  },
  { path: '/situation/external-reports', title: 'External Reports', kicker: 'Reports', summary: 'Request reports from connected systems and review incoming section requests.' },
  {
    path: '/archive',
    title: 'Archive',
    kicker: 'Records',
    summary: 'Search and export historical event records, household logs, dispatches, requests, and SitReps.',
  },
]

function App() {
  return (
    <BrowserRouter>
      <AuthRoutes />
    </BrowserRouter>
  )
}

function AuthRoutes() {
  const [user, setUser] = useState(() => {
    const cached = readSessionCache('web-user', 24 * 60 * 60 * 1000)
    return isWebUser(cached) ? cached : null
  })
  const [checkingSession, setCheckingSession] = useState(true)
  const [loginError, setLoginError] = useState('')
  const navigate = useNavigate()
  const sessionUserId = user?.user_id || user?.id

  useEffect(() => {
    if (user && getToken()) writeSessionCache('web-user', user)
    else removeSessionCache('web-user')
  }, [user])

  useEffect(() => {
    if (!sessionUserId) return undefined
    let disposed = false
    let inFlight = false
    async function refreshAccess() {
      if (disposed || !getToken()) return
      if (inFlight) return
      inFlight = true
      const token = getToken()
      try {
        const nextUser = await getCurrentUser()
        if (disposed || token !== getToken()) return
        if (isWebUser(nextUser)) {
          setUser(current => JSON.stringify(current) === JSON.stringify(nextUser) ? current : nextUser)
        } else {
          clearToken()
          clearSessionCache()
          setUser(null)
          setLoginError('Your role access changed. Please sign in again.')
          navigate('/login', { replace: true })
        }
      } catch (error) {
        if (!disposed && token === getToken() && [401, 403].includes(error.response?.status)) {
          clearToken()
          clearSessionCache()
          setUser(null)
          navigate('/login', { replace: true })
        }
      } finally {
        inFlight = false
      }
    }
    const onVisible = () => { if (!document.hidden) refreshAccess() }
    const onOperations = event => { if (event.detail?.topics?.includes('accounts')) refreshAccess() }
    const interval = window.setInterval(onVisible, 60000)
    window.addEventListener('personnel-access:changed', refreshAccess)
    window.addEventListener('operations:changed', onOperations)
    window.addEventListener('focus', onVisible)
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      disposed = true
      window.clearInterval(interval)
      window.removeEventListener('personnel-access:changed', refreshAccess)
      window.removeEventListener('operations:changed', onOperations)
      window.removeEventListener('focus', onVisible)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [sessionUserId, navigate])

  useEffect(() => {
    async function loadSession() {
      if (!getToken()) {
        clearSessionCache()
        setCheckingSession(false)
        return
      }

      try {
        const currentUser = await getCurrentUser()
        if (isWebUser(currentUser)) {
          setUser(currentUser)
        } else {
          clearToken()
          clearSessionCache()
          setUser(null)
          setLoginError('This account is for the mobile app.')
        }
      } catch (error) {
        clearToken()
        clearSessionCache()
        setUser(null)
        if (error.response?.status === 503) {
          setLoginError(error.response.data?.message || 'The database is unavailable. Please try again when the backend is connected.')
        }
      } finally {
        setCheckingSession(false)
      }
    }

    const sessionCheck = window.setTimeout(() => loadSession(), 0)
    return () => window.clearTimeout(sessionCheck)
  }, [])

  async function handleLogin(form) {
    setLoginError('')

    try {
      const data = await loginUser(form)
      const nextUser = data.user

      if (!isWebUser(nextUser)) {
        clearToken()
        clearSessionCache()
        throw new Error('Household and rescuer accounts should use the mobile app.')
      }

      await saveRememberedPassword(form.login, form.password, form.rememberPassword)
      saveToken(data.token)
      setUser(nextUser)
      navigate(webHomeForUser(nextUser), { replace: true })
    } catch (error) {
      throw new Error(error.message === 'Household and rescuer accounts should use the mobile app.' ? error.message : getLoginMessage(error), { cause: error })
    }
  }

  async function handleLogout() {
    try {
      await logoutUser()
    } finally {
      clearToken()
      clearSessionCache()
      setUser(null)
      navigate('/login', { replace: true })
    }
  }

  function handleUserChange(nextUser) {
    if (isWebUser(nextUser)) {
      setUser(nextUser)
    } else {
      clearToken()
      clearSessionCache()
      setUser(null)
      navigate('/login', { replace: true })
    }
  }

  if (checkingSession && !user) {
    return <div className="screen-loader">Loading...</div>
  }

  return (
    <Routes>
      <Route
        path="/login"
        element={
          user ? (
            <Navigate to={webHomeForUser(user)} replace />
          ) : (
            <LoginPage onLogin={handleLogin} error={loginError} />
          )
        }
      />

      <Route
        path="/"
        element={
          <ProtectedRoute user={user}>
            <AppShell user={user} pages={pagesForUser(user)} onLogout={handleLogout} onUserChange={handleUserChange} />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to={webHomeForUser(user)} replace />} />
        <Route path="dispatch/communication" element={<LazyPage page={{ path: '/dispatch/communication' }} />} />
        <Route path="rescue-management/new" element={<LazyPage page={{ path: '/dispatch/new' }} />} />
        <Route path="rescue-management/:assignmentId/edit" element={<LazyPage page={{ path: '/dispatch/new' }} />} />
        <Route path="dispatch/new" element={<LazyPage page={{ path: '/dispatch/new' }} />} />
        <Route path="rescuers/new" element={<LazyPage page={{ path: '/rescuers/new' }} />} />
        <Route path="rescuers" element={<GuardedRescuersPage />} />
        <Route path="rescuers/view" element={<LegacyPersonnelRedirect />} />
        <Route path="rescuers/edit" element={<LazyPage page={{ path: '/rescuers/edit' }} />} />
        <Route path="resources-requests/new" element={<LazyPage page={{ path: '/resources-requests/new' }} />} />
        <Route path="resources-requests/:requestId/:mode" element={<LazyPage page={{ path: '/resources-requests/edit' }} />} />
        {pagesForUser(user).filter(page => page.path !== '/rescuers').map((page) => (
          <Route
            key={page.path}
            path={page.path.replace('/', '')}
            element={<LazyPage page={page} />}
          />
        ))}
        <Route path="households/_household-list" element={<LazyPage page={{ path: '/households/_household-list' }} />} />
        <Route path="households/_data-directory" element={<LazyPage page={{ path: '/households/_data-directory' }} />} />
        <Route path="households/:householdId" element={<GuardedHouseholdReviewPage />} />
        <Route path="notifications" element={<LazyPage page={{ path: '/notifications' }} />} />
        <Route path="profile" element={<LazyPage page={{ path: '/profile' }} />} />
      </Route>

      <Route path="*" element={<Navigate to={user ? webHomeForUser(user) : '/login'} replace />} />
    </Routes>
  )
}

function LazyPage({ page }) {
  const { user } = useOutletContext()
  const PageComponent = pageComponents[page.path]

  if (!canOpenWebPage(user, page.path)) {
    return <Navigate to={webHomeForUser(user)} replace />
  }

  if (!PageComponent) {
    return <PlaceholderPage page={page} />
  }

  return (
    <Suspense fallback={<div className="screen-loader">Loading...</div>}>
      <PageComponent />
    </Suspense>
  )
}

function GuardedHouseholdReviewPage() {
  const { user } = useOutletContext()
  if (!canOpenWebPage(user, '/households')) return <Navigate to={webHomeForUser(user)} replace />
  return <HouseholdReviewPage />
}

function LegacyPersonnelRedirect() {
  const location = useLocation()
  const id = new URLSearchParams(location.search).get('id')
  return <Navigate to="/rescuers" replace state={{ ...location.state, selectedProfileId: id }} />
}

function GuardedRescuersPage() {
  const { user } = useOutletContext()
  const location = useLocation()
  const id = location.state?.selectedProfileId || new URLSearchParams(location.search).get('id')
  const Page = pageComponents['/rescuers']
  if (!canOpenWebPage(user, '/rescuers') && !(hasWebAccess(user) && /^\d+$/.test(String(id || '')))) {
    return <Navigate to={webHomeForUser(user)} replace />
  }
  return <Suspense fallback={<div className="screen-loader">Loading...</div>}><Page /></Suspense>
}

function ProtectedRoute({ user, children }) {
  const location = useLocation()

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  return children
}

function isWebUser(user) {
  return hasWebAccess(user)
}

function pagesForUser(user) {
  const roleKey = user?.role?.role_key

  return modulePages.filter((page) => canOpenWebPage(user, page.path) && (!page.superOnly || roleKey === 'operations_admin'))
}

function getLoginMessage(error) {
  if (!error?.response) {
    return 'Cannot connect to the server right now. Please check if the backend is running.'
  }

  const data = error?.response?.data
  const status = error?.response?.status

  if (status === 429) {
    return 'Unable to log in right now. Please wait a moment and try again.'
  }

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check your entry.'
  }

  if (status === 401 || status === 422) {
    return data?.message || 'The account ID or password is incorrect.'
  }

  if (status === 403) {
    return data?.message || 'This account is not allowed to access the web dashboard.'
  }

  if (status === 503) {
    return data?.message || 'The database is not available right now. Please contact the system administrator.'
  }

  if (status >= 500) {
    return 'Something went wrong while logging in. Please contact the system administrator.'
  }

  return data?.message || 'Unable to log in. Please try again.'
}

export default App
