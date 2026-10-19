import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate, useOutletContext } from 'react-router-dom'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import { createRescueTeam, deleteRescueTeam, getRescueTeamConfig, updateRescueTeam } from '../api/rescuerApi'
import { getCurrentUser } from '../api/authApi'
import { rescuerErrorMessage } from '../utils/rescuerHelpers'

export default function LegacyTeamManagementPage() {
  const navigate = useNavigate()
  const { onUserChange } = useOutletContext()
  const [workspace, setWorkspace] = useState(null)
  const [revision, setRevision] = useState(0)
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [confirmation, setConfirmation] = useState(null)

  useEffect(() => {
    let active = true
    getRescueTeamConfig().then(data => {
      if (!active) return
      setWorkspace(data)
      setRevision(value => value + 1)
    }).catch(reason => {
      if (active) setError(rescuerErrorMessage(reason))
    }).finally(() => {
      if (active) setLoading(false)
    })
    return () => { active = false }
  }, [reload])

  async function refreshAccess() {
    try { onUserChange?.(await getCurrentUser()) } catch { /* The next session refresh will retry. */ }
  }

  async function saveTeam(body) {
    setBusy(true)
    setError('')
    try {
      const data = body.team_id ? await updateRescueTeam(body.team_id, body) : await createRescueTeam(body)
      setWorkspace(data)
      setRevision(value => value + 1)
      setNotice('Team saved.')
      await refreshAccess()
    } catch (reason) {
      setError(rescuerErrorMessage(reason))
    } finally { setBusy(false) }
  }

  async function removeTeam() {
    setBusy(true)
    setError('')
    try {
      setWorkspace(await deleteRescueTeam(confirmation.id))
      setRevision(value => value + 1)
      setConfirmation(null)
      setNotice('Team deleted.')
      await refreshAccess()
    } catch (reason) {
      setError(rescuerErrorMessage(reason))
    } finally { setBusy(false) }
  }

  return <main className="tm-page" aria-label="Team Management">
    <div className="pg on">
      {notice && <div className="tm-feedback tm-success" role="status">{notice}<button className="bt sm" type="button" onClick={() => setNotice('')}>Dismiss</button></div>}
      <RescueTeamConfigModal key={revision} embedded isOpen workspace={workspace} isLoading={loading} isSaving={busy} error={error}
        onRetry={() => { setLoading(true); setError(''); setReload(value => value + 1) }}
        onClose={() => navigate('/rescuers')} onSave={saveTeam}
        onDelete={(id, name) => { setError(''); setConfirmation({ id, name }) }} />
    </div>
    {confirmation && <DeleteTeamDialog name={confirmation.name} busy={busy} error={error} onCancel={() => setConfirmation(null)} onConfirm={removeTeam} />}
  </main>
}

function DeleteTeamDialog({ name, busy, error, onCancel, onConfirm }) {
  const cancel = useRef(null)
  const dialog = useRef(null)
  useEffect(() => {
    const previous = document.activeElement
    cancel.current?.focus()
    return () => previous?.focus()
  }, [])
  return createPortal(<div className="tm-page ov on" onClick={event => { if (event.target === event.currentTarget && !busy) onCancel() }} onKeyDown={event => {
    if (event.key === 'Escape' && !busy) onCancel()
    if (event.key === 'Tab') {
      const buttons = [...dialog.current.querySelectorAll('button:not(:disabled)')]
      const first = buttons[0], last = buttons[buttons.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
    }
  }}><div className="mb" ref={dialog} role="dialog" aria-modal="true" aria-labelledby="tm-confirm-title">
    <h3 id="tm-confirm-title">Delete team</h3><div>{name}</div>
    {error && <div className="tm-feedback" role="alert">{error}</div>}
    <div className="tm-team-actions"><button ref={cancel} className="bt" type="button" disabled={busy} onClick={onCancel}>Cancel</button><button className="bt d" type="button" disabled={busy} onClick={onConfirm}>{busy ? 'Deleting…' : 'Delete team'}</button></div>
  </div></div>, document.body)
}
