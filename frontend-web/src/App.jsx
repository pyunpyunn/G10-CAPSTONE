import { lazy, Suspense, useEffect, useState } from 'react'
import {
  BrowserRouter,
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from 'react-router-dom'
import AppShell from './components/layout/AppShell'
import LoginPage from './pages/LoginPage'
import PlaceholderPage from './pages/PlaceholderPage'
import { getCurrentUser, loginUser, logoutUser } from './api/authApi'
import { clearToken, getToken, saveToken } from './api/token'
import './App.css'

const webRoles = ['super_admin', 'admin']

const pageComponents = {
  '/super-admin': lazy(() => import('./pages/SuperAdminPage')),
  '/dashboard': lazy(() => import('./pages/DashboardPage')),
  '/broadcast': lazy(() => import('./pages/BroadcastPage')),
  '/weather': lazy(() => import('./pages/WeatherPage')),
  '/mapping': lazy(() => import('./pages/MappingPage')),
  '/households': lazy(() => import('./pages/HouseholdStatusPage')),
  '/dispatch': lazy(() => import('./pages/RescueDispatchPage')),
  '/rescuers': lazy(() => import('./pages/RescuerAccountsPage')),
  '/resources-requests': lazy(() => import('./pages/ResourcesRequestsPage')),
  '/situation': lazy(() => import('./pages/SituationReportPage')),
  '/archive': lazy(() => import('./pages/ArchivePage')),
  '/notifications': lazy(() => import('./pages/NotificationsPage')),
  '/profile': lazy(() => import('./pages/ProfilePage')),
}

const modulePages = [
  {
    path: '/super-admin',
    title: 'Inquiries',
    kicker: 'Super Admin',
    summary: 'Review landing page inquiries and manage command-level account access.',
    superOnly: true,
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
    title: 'Rescue Dispatch',
    kicker: 'Operations',
    summary: 'Assign teams, monitor progress, and record field outcomes from rescuer updates.',
  },
  {
    path: '/rescuers',
    title: 'Rescuer Accounts',
    kicker: 'Verified accounts',
    summary: 'Create and manage HQ-created rescuer accounts, teams, duty status, and contact details.',
  },
  {
    path: '/resources-requests',
    title: 'Resources & Requests',
    kicker: 'Validation queue',
    summary: 'Validate EvaTrack/manual requests before forwarding verified records to TrackingAid/HQ.',
  },
  {
    path: '/situation',
    title: 'Situation Reporting',
    kicker: 'Reports',
    summary: 'Generate saved event snapshots for household, dispatch, resources, weather, and casualty summaries.',
  },
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
  const [user, setUser] = useState(null)
  const [checkingSession, setCheckingSession] = useState(true)
  const [loginError, setLoginError] = useState('')
  const navigate = useNavigate()

  useEffect(() => {
    async function loadSession() {
      if (!getToken()) {
        setCheckingSession(false)
        return
      }

      try {
        const currentUser = await getCurrentUser()
        if (isWebUser(currentUser)) {
          setUser(currentUser)
        } else {
          clearToken()
          setLoginError('This account is for the mobile app.')
        }
      } catch {
        clearToken()
      } finally {
        setCheckingSession(false)
      }
    }

    loadSession()
  }, [])

  async function handleLogin(form) {
    setLoginError('')

    try {
      const data = await loginUser(form)
      const nextUser = data.user

      if (!isWebUser(nextUser)) {
        clearToken()
        setLoginError('Household and rescuer accounts should use the mobile app.')
        return
      }

      saveToken(data.token)
      setUser(nextUser)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      setLoginError(getLoginMessage(error))
    }
  }

  async function handleLogout() {
    try {
      await logoutUser()
    } finally {
      clearToken()
      setUser(null)
      navigate('/login', { replace: true })
    }
  }

  if (checkingSession) {
    return <div className="screen-loader">Loading...</div>
  }

  return (
    <Routes>
      <Route
        path="/login"
        element={
          user ? (
            <Navigate to="/dashboard" replace />
          ) : (
            <LoginPage onLogin={handleLogin} error={loginError} />
          )
        }
      />

      <Route
        path="/"
        element={
          <ProtectedRoute user={user}>
            <AppShell user={user} pages={pagesForUser(user)} onLogout={handleLogout} onUserChange={setUser} />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        {pagesForUser(user).map((page) => (
          <Route
            key={page.path}
            path={page.path.replace('/', '')}
            element={<LazyPage page={page} />}
          />
        ))}
        <Route path="notifications" element={<LazyPage page={{ path: '/notifications' }} />} />
        <Route path="profile" element={<LazyPage page={{ path: '/profile' }} />} />
      </Route>

      <Route path="*" element={<Navigate to={user ? '/dashboard' : '/login'} replace />} />
    </Routes>
  )
}

function LazyPage({ page }) {
  const PageComponent = pageComponents[page.path]

  if (!PageComponent) {
    return <PlaceholderPage page={page} />
  }

  return (
    <Suspense fallback={<div className="screen-loader">Loading...</div>}>
      <PageComponent />
    </Suspense>
  )
}

function ProtectedRoute({ user, children }) {
  const location = useLocation()

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  return children
}

function isWebUser(user) {
  return webRoles.includes(user?.role?.role_key)
}

function pagesForUser(user) {
  const roleKey = user?.role?.role_key

  return modulePages.filter((page) => !page.superOnly || roleKey === 'super_admin')
}

function getLoginMessage(error) {
  if (!error?.response) {
    return 'Cannot connect to the server right now. Please check if the backend is running.'
  }

  const data = error?.response?.data
  const status = error?.response?.status

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
