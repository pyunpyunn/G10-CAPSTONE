import { useEffect, useState } from 'react'
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

const modulePages = [
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
    return <div className="screen-loader">Checking session...</div>
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
            <AppShell user={user} pages={modulePages} onLogout={handleLogout} />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        {modulePages.map((page) => (
          <Route
            key={page.path}
            path={page.path.replace('/', '')}
            element={<PlaceholderPage page={page} />}
          />
        ))}
      </Route>

      <Route path="*" element={<Navigate to={user ? '/dashboard' : '/login'} replace />} />
    </Routes>
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

function getLoginMessage(error) {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check your entry.'
  }

  return data?.message || 'Unable to sign in. Please check the server and try again.'
}

export default App
