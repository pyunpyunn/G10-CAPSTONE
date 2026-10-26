import AnnexHierarchyChart from './AnnexHierarchyChart'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate, useOutletContext } from 'react-router-dom'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import { createRescueTeam, deleteRescueTeam, getRescueTeamConfig, updateRescueTeam } from '../api/rescuerApi'
import { getCurrentUser } from '../api/authApi'
import { rescuerErrorMessage } from '../utils/rescuerHelpers'
import {
  ANNEX_H_SUBCOMMITTEES,
  teamGroupKey,
} from '../utils/annexHTeamHierarchy'


export default function LegacyTeamManagementPage() {
  const navigate = useNavigate()
  const { onUserChange } = useOutletContext()
  const [workspace, setWorkspace] = useState(null)
  const [activeView, setActiveView] = useState('hierarchy')
  const [revision, setRevision] = useState(0)
  const [, setSelectedId] = useState(null)
  const [editor, setEditor] = useState(null)
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
      const initialTeamId = data.selected_team_id || data.teams.find(team => team.team_code !== 'HQCC' && teamGroupKey(team) !== 'unassigned')?.team_id || null
      setWorkspace({ ...data, selected_team_id: initialTeamId })
      setSelectedId(current => data.teams.some(team => String(team.team_id) === String(current))
        ? current
        : initialTeamId)
    }).catch(reason => {
      if (active) setError(rescuerErrorMessage(reason, 'Team structure could not be loaded.'))
    }).finally(() => {
      if (active) setLoading(false)
    })
    return () => { active = false }
  }, [reload])

  async function refreshAccess() {
    try {
      const user = await getCurrentUser()
      onUserChange?.(user)
    } catch {
      setError('Team data was saved, but the current access profile could not be refreshed. Reload the page to retry.')
    }
  }

  async function saveTeam(body) {
    setBusy(true)
    setError('')
    try {
      const data = body.team_id ? await updateRescueTeam(body.team_id, body) : await createRescueTeam(body)
      const savedTeam = data.teams?.find(team => String(team.team_id) === String(body.team_id))
        || data.teams?.find(team => team.team_code === body.team_code || team.team_name === body.team_name)
      setWorkspace({ ...data, selected_team_id: savedTeam?.team_id || body.team_id || data.selected_team_id })
      setSelectedId(savedTeam?.team_id || body.team_id || null)
      setRevision(value => value + 1)
      setEditor(null)
      setNotice('Team saved.')
      await refreshAccess()
    } catch (reason) {
      setError(rescuerErrorMessage(reason))
    } finally {
      setBusy(false)
    }
  }

  async function removeTeam() {
    if (!confirmation) return
    setBusy(true)
    setError('')
    try {
      const data = await deleteRescueTeam(confirmation.team.team_id)
      const nextSelectedId = data.teams?.find(team => team.team_code !== 'HQCC' && teamGroupKey(team) !== 'unassigned')?.team_id || null
      setWorkspace({ ...data, selected_team_id: nextSelectedId })
      setSelectedId(nextSelectedId)
      setRevision(value => value + 1)
      setConfirmation(null)
      setNotice('Team deleted.')
      await refreshAccess()
    } catch (reason) {
      setError(rescuerErrorMessage(reason))
    } finally {
      setBusy(false)
    }
  }

  const teams = workspace?.teams || []
  const editorWorkspace = editor && workspace ? {
    ...workspace,
    teams: editor.team ? [editor.team] : [],
    selected_team_id: editor.team?.team_id || null,
    form_defaults: {
      ...workspace.form_defaults,
      ...(editor.team ? {} : {
        team_type: ANNEX_H_SUBCOMMITTEES.find(committee => committee.key === editor.committeeKey)?.title || '',
        team_name: editor.template?.name || '',
      }),
    },
  } : workspace

  return <div className={activeView === 'teams' ? 'tm-page' : 'annex-h-workspace'} aria-label="Team management">
    {activeView === 'teams' && <div className="annex-h-view-switch"><button type="button" onClick={() => setActiveView('hierarchy')}>Annex H hierarchy</button></div>}
    {activeView === 'teams' ? <>
      {notice && <div className="tm-feedback tm-success" role="status">{notice}<button className="bt sm" type="button" onClick={() => setNotice('')}>Dismiss</button></div>}
      <RescueTeamConfigModal
        key={revision}
        isOpen
        workspace={workspace}
        isLoading={loading}
        isSaving={busy}
        error={error}
        onRetry={() => { setLoading(true); setError(''); setReload(value => value + 1) }}
        onClose={() => navigate('/rescuers')}
        onSave={saveTeam}
        onDelete={(id, name) => {
          const team = teams.find(candidate => String(candidate.team_id) === String(id))
          if (team) setConfirmation({ team: { ...team, team_name: name } })
        }}
        embedded
      />
    </> : <>
      {error && <div className="tm-feedback" role="alert">{error}<button className="bt" onClick={() => {setLoading(true); setError(''); setReload(value => value + 1)}}>Retry</button></div>}
      {notice && <div className="tm-feedback" role="status">{notice}</div>}
      {loading ? <div role="status">Loading Annex H team structure…</div> : workspace && <AnnexHierarchyChart workspace={workspace} onAccounts={account => navigate(account?.responder_id ? `/rescuers?id=${encodeURIComponent(account.responder_id)}&type=${account.account_type === 'command_center' || ['chair','operations_admin','mitigation_prevention','preparedness','response','recovery_rehabilitation'].includes(account.role_key) ? 'command_center' : 'rescuer'}` : '/rescuers')} onTeams={() => setActiveView('teams')} />}
    </>}
    {editor && editorWorkspace && <RescueTeamConfigModal
      key={`${editor.team?.team_id || 'new'}-${editor.committeeKey || ''}`}
      isOpen workspace={editorWorkspace} isLoading={false} isSaving={busy} error=""
      onRetry={() => setReload(value => value + 1)}
      onClose={() => setEditor(null)}
      onSave={saveTeam}
      onDelete={(id, name) => {
        const team = teams.find(candidate => String(candidate.team_id) === String(id))
        if (team) setConfirmation({ team: { ...team, team_name: name } })
      }}
    />}
    {confirmation && <DeleteTeamDialog team={confirmation.team} busy={busy} error={error} onCancel={() => setConfirmation(null)} onConfirm={removeTeam} />}
  </div>
}

function DeleteTeamDialog({ team, busy, error, onCancel, onConfirm }) {
  const cancel = useRef(null)
  const dialog = useRef(null)
  useEffect(() => {
    const previous = document.activeElement
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    cancel.current?.focus()
    return () => {
      document.body.style.overflow = previousOverflow
      previous?.focus()
    }
  }, [])
  return createPortal(<div className="tm-page ov on team-delete-overlay" onClick={event => { if (event.target === event.currentTarget && !busy) onCancel() }} onKeyDown={event => {
    if (event.key === 'Escape' && !busy) onCancel()
    if (event.key === 'Tab') {
      const buttons = [...dialog.current.querySelectorAll('button:not(:disabled)')]
      const first = buttons[0], last = buttons[buttons.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
    }
  }}><div className="mb" ref={dialog} role="dialog" aria-modal="true" aria-labelledby="tm-confirm-title" aria-describedby="tm-confirm-description" aria-busy={busy}>
    <h3 id="tm-confirm-title">Delete team</h3><p id="tm-confirm-description">Delete the team “{team.team_name}”?</p>
    {error && <div className="tm-feedback" role="alert">{error}</div>}
    <div className="tm-team-actions"><button ref={cancel} className="bt" type="button" disabled={busy} onClick={onCancel}>Cancel</button><button className="bt d" type="button" disabled={busy} onClick={onConfirm}>{busy ? 'Deleting…' : 'Delete team'}</button></div>
  </div></div>, document.body)
}
