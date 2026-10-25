import { useEffect, useState } from 'react'
import { useNavigate, useOutletContext, useSearchParams } from 'react-router-dom'
import { getPersonnelProfile } from '../api/rescuerApi'
import { webHomeForUser } from '../utils/personnelAccess'
import '../styles/personnel-profile.css'

export default function PersonnelProfilePage({ requestedId }) {
  const [params] = useSearchParams()
  const id = requestedId || params.get('id')
  const { user } = useOutletContext()
  const navigate = useNavigate()
  const [revision, setRevision] = useState(0)
  const [state, setState] = useState({ loading: true })

  useEffect(() => {
    const controller = new AbortController()
    getPersonnelProfile(id || 'missing', controller.signal)
      .then(profile => { if (!controller.signal.aborted) setState({ profile, id }) })
      .catch(error => {
        if (!controller.signal.aborted) setState({ id, error: error.response?.status === 404
          ? 'This personnel profile is unavailable or has been removed.'
          : 'The profile could not be loaded. Please try again.' })
      })
    return () => controller.abort()
  }, [id, revision])

  const current = state.id === id ? state : { loading: true }
  const profile = current.profile
  const rows = profile ? [
    ['Role / access', profile.role_name], ['Position', profile.title],
    ['Team', profile.team_name], ['Team type', profile.team_type],
    ['Duty', profile.duty_status?.replaceAll('_', ' ')],
    ['Account status', profile.is_active ? 'Active' : 'Inactive'],
    ['Contact number', profile.contact_number], ['Email', profile.email], ['Skills', profile.skills],
  ] : []

  return <section className="personnel-profile" aria-label="Personnel profile">
    <button className="btn" type="button" onClick={() => window.history.state?.idx > 0 ? navigate(-1) : navigate(webHomeForUser(user))}>‹ Back</button>
    {current.loading && <p role="status">Loading profile…</p>}
    {current.error && <div role="alert"><p>{current.error}</p><button className="btn" type="button" onClick={() => { setState({ loading: true }); setRevision(value => value + 1) }}>Retry</button></div>}
    {profile && <article key={id}>
      <header><span className="personnel-profile-avatar" aria-hidden="true">{(profile.full_name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('')}</span><div><p>Personnel profile · View only</p><h1>{profile.full_name || 'Personnel'}</h1><p>{profile.role_name || profile.title || 'Role not recorded'}</p></div></header>
      <dl>{rows.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value || 'Not recorded'}</dd></div>)}</dl>
    </article>}
  </section>
}
