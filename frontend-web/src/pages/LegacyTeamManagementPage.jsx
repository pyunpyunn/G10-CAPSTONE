import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate, useOutletContext } from 'react-router-dom'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import { createRescueTeam, deleteRescueTeam, getRescueTeamConfig, updateRescueTeam } from '../api/rescuerApi'
import { getCurrentUser } from '../api/authApi'
import { rescuerErrorMessage } from '../utils/rescuerHelpers'
import {
  annexHTeamCode,
  annexHTeamCodeLabel,
  annexHTeamName,
  ANNEX_H_ROLE_LABELS,
  ANNEX_H_DESK_FUNCTIONS,
  ANNEX_H_TEAM_GROUPS,
  ANNEX_H_SUBCOMMITTEES,
  roleAccountsForCommittee,
  teamGroupKey,
  teamsForCommittee,
} from '../utils/annexHTeamHierarchy'

const statusLabel = team => team.duty_status_display?.label || team.duty_status || 'Not recorded'

export default function LegacyTeamManagementPage() {
  const navigate = useNavigate()
  const { onUserChange } = useOutletContext()
  const [workspace, setWorkspace] = useState(null)
  const [activeView, setActiveView] = useState('teams')
  const [revision, setRevision] = useState(0)
  const [selectedId, setSelectedId] = useState(null)
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
  const responders = workspace?.responders || []
  const selected = teams.find(team => String(team.team_id) === String(selectedId))
  const selectedMembers = responders.filter(responder => String(responder.team_id) === String(selectedId))
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

  function teamButton(team) {
    const active = String(team.team_id) === String(selectedId)
    return <button
      className={`annex-h-team${active ? ' on' : ''}`}
      type="button"
      key={team.team_id}
      aria-pressed={active}
      onClick={() => setSelectedId(team.team_id)}
    >
      <span><b>{annexHTeamName(team)}</b><small>{annexHTeamCodeLabel(team)} · {team.member_count || 0} members</small></span>
      <span className={`annex-h-status${/off|disabled/i.test(team.duty_status || '') ? ' off' : ''}`}>{statusLabel(team)}</span>
    </button>
  }

  function committeeSection(committee) {
    const accounts = roleAccountsForCommittee(responders, committee.roleKeys)
    const committeeTeams = teamsForCommittee(teams, committee.key).filter(team => team.team_code !== 'HQCC')
    return <section className="annex-h-committee" key={committee.key}>
      <div className="annex-h-node-heading">
        <div><span className="annex-h-rank">SUB-COMMITTEE</span><h2>{committee.title}</h2></div>
        <span className="annex-h-count">{accounts.length} role account{accounts.length === 1 ? '' : 's'} · {committeeTeams.length} team{committeeTeams.length === 1 ? '' : 's'}</span>
      </div>
      <div className="annex-h-account-list">
        {accounts.length ? accounts.map(account => <span className="annex-h-account" key={account.responder_id}>
          <b>{account.full_name}</b><small>{account.role_name || ANNEX_H_ROLE_LABELS[account.role_key]}</small>
        </span>) : <span className="annex-h-muted">No account assigned to this role.</span>}
      </div>
      {committee.teamGroupKeys.map(groupKey => {
        const teamGroup = committeeTeams.filter(team => teamGroupKey(team) === groupKey)
        const group = ANNEX_H_TEAM_GROUPS.find(item => item.key === groupKey)
        const groupTitle = group?.title || 'Committee Teams'
        const units = (group?.units || []).map(unit => ({
          unit,
          team: teamGroup.find(candidate => annexHTeamCode(candidate) === unit.code),
        }))
        return <div className="annex-h-team-group" key={groupKey}>
          <div className="annex-h-group-title">{groupTitle}</div>
          {units.map(({ unit, team }) => team
            ? <div className={unit.parentCode ? 'annex-h-team-nested' : ''} key={unit.code}>{teamButton(team)}</div>
            : <button className={`annex-h-team annex-h-team-missing${unit.parentCode ? ' nested' : ''}`} type="button" key={unit.code} disabled={busy} onClick={() => setEditor({ committeeKey: committee.key, template: unit })}><span><b>{unit.name}</b><small>{unit.code} · Not configured</small></span><span className="annex-h-status off">Add team</span></button>
          )}
          {teamGroup.filter(team => !group?.units?.some(unit => unit.code === annexHTeamCode(team))).map(teamButton)}
          {!units.length && !teamGroup.length && <div className="annex-h-muted">No teams assigned.</div>}
        </div>
      })}
      {committee.teamGroupKeys.some(key => ANNEX_H_TEAM_GROUPS.find(group => group.key === key)?.units.length) && <div className="annex-h-section-actions">
        <button className="bt sm" type="button" disabled={busy} onClick={() => setEditor({ committeeKey: committee.key })}>+ Add team</button>
      </div>}
      {(ANNEX_H_DESK_FUNCTIONS[committee.key] || []).length > 0 && <div className="annex-h-desk-functions">
        <div className="annex-h-group-title">Web / desk functions — no mobile team roster</div>
        {ANNEX_H_DESK_FUNCTIONS[committee.key].map(unit => <div className="annex-h-desk-unit" key={unit.code}>
          <span><b>{unit.name}</b><small>{unit.code} · web role access only</small></span>
          <span>Managed through the {committee.shortTitle} role; individual desk assignments are not recorded here.</span>
        </div>)}
      </div>}
    </section>
  }

  const unclassified = teams.filter(team => team.team_code !== 'HQCC' && teamGroupKey(team) === 'unassigned')
  const leadershipTeam = teams.find(team => team.team_code === 'HQCC')
  const chairAccounts = roleAccountsForCommittee(responders, ['chair'])
  const technicalAccounts = roleAccountsForCommittee(responders, ['operations_admin'])

  return <main className="tm-page" aria-label="Team management">
    <div className="annex-h-view-switch" role="tablist" aria-label="Team display">
      <button type="button" role="tab" aria-selected={activeView === 'teams'} className={activeView === 'teams' ? 'on' : ''} onClick={() => setActiveView('teams')}>Team management</button>
      <button type="button" role="tab" aria-selected={activeView === 'hierarchy'} className={activeView === 'hierarchy' ? 'on' : ''} onClick={() => setActiveView('hierarchy')}>Annex H hierarchy</button>
    </div>
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
    </> : <div className="annex-h-page" aria-label="Annex H team structure">
    <div className="annex-h-page-head">
      <div>
        <div className="annex-h-eyebrow">Personnel Access / Team Management</div>
        <h1>Annex H organizational structure</h1>
        <p>Committee roles and operational teams are shown in their separate places in the hierarchy.</p>
      </div>
      <button className="bt" type="button" onClick={() => navigate('/rescuers')}>Back to accounts</button>
    </div>
    {notice && <div className="tm-feedback tm-success" role="status">{notice}<button className="bt sm" type="button" onClick={() => setNotice('')}>Dismiss</button></div>}
    {error && <div className="tm-feedback" role="alert">{error}<button className="bt sm" type="button" disabled={busy} onClick={() => { setLoading(true); setError(''); setReload(value => value + 1) }}>Retry</button></div>}
    <div className="annex-h-policy-note">
      <b>Access rule:</b> a person’s role determines permissions; team assignment determines mission eligibility only. Team leaders do not receive additional web access from leadership of a field team.
    </div>
    {loading ? <div className="cd emp" role="status">Loading Annex H team structure…</div> : workspace && <>
      <section className="annex-h-command">
        <div className="annex-h-command-heading"><span className="annex-h-level">LEVEL 1</span><div><h2>Barangay Disaster Risk Reduction and Management Committee</h2><p>Chairperson and committee leadership</p></div></div>
        <div className="annex-h-chair-row">
          <div className="annex-h-chair-card"><span className="annex-h-level">CHAIRPERSON</span><b>{chairAccounts.map(account => account.full_name).join(', ') || 'No Chair account assigned'}</b><small>Final committee authority</small></div>
          {leadershipTeam && <div className="annex-h-roster-card"><span className="annex-h-level">COMMITTEE ROSTER</span><b>{leadershipTeam.member_count || 0} committee accounts linked</b><small>Role assignments remain independent of this roster.</small><button className="bt sm" type="button" onClick={() => setEditor({ team: leadershipTeam })}>Manage roster</button></div>}
          <div className="annex-h-technical-card"><span className="annex-h-level">TECHNICAL ACCESS</span><b>Operations/Admin</b><small>No incident command, dispatch approval, or household access.</small><span>{technicalAccounts.length ? technicalAccounts.map(account => account.full_name).join(', ') : 'No account assigned'}</span></div>
        </div>
      </section>
      <div className="annex-h-level-label"><span className="annex-h-level">LEVEL 2</span><h2>Sub-committees and assigned teams</h2></div>
      <div className="annex-h-subcommittees">{ANNEX_H_SUBCOMMITTEES.map(committeeSection)}</div>
      {unclassified.length > 0 && <section className="annex-h-committee annex-h-unclassified">
        <div className="annex-h-node-heading"><div><span className="annex-h-rank">REVIEW REQUIRED</span><h2>Teams without a committee classification</h2></div><span className="annex-h-count">{unclassified.length} team{unclassified.length === 1 ? '' : 's'}</span></div>
        <p>These existing teams do not match an approved field-team unit. Desk functions such as CWT, RPT, LHT, and ISH are web-only and must not be given mobile rescuer accounts. Existing assignments are preserved until reviewed.</p>
        <div className="annex-h-team-group">{unclassified.map(team => <button className="annex-h-team" key={team.team_id} type="button" onClick={() => setEditor({ team })}><span><b>{team.team_name}</b><small>{team.team_code} · {team.team_type || 'No committee set'}</small></span><span className="annex-h-status">Set committee</span></button>)}</div>
      </section>}
      <section className="annex-h-selected">
        {!selected ? <div className="cd emp">Choose a team from the hierarchy to inspect its roster and duty state.</div> : <>
          <div className="annex-h-selected-head"><div><span className="annex-h-level">SELECTED TEAM</span><h2>{annexHTeamName(selected)} <small>{annexHTeamCodeLabel(selected)}</small></h2>{(selected.team_name !== annexHTeamName(selected) || selected.team_code !== annexHTeamCode(selected)) && <p className="annex-h-legacy-label">Stored account/team identifiers are preserved: {selected.team_name} · {selected.team_code}</p>}</div><div className="annex-h-selected-actions"><button className="bt sm" type="button" disabled={busy} onClick={() => setEditor({ team: selected })}>Edit team</button><button className="bt d sm" type="button" disabled={busy || !selected.can_delete} title={!selected.can_delete ? 'Teams with active assignments or deployed members cannot be deleted' : undefined} onClick={() => setConfirmation({ team: selected })}>Delete team</button></div></div>
          <div className="annex-h-facts"><span>Committee<b>{selected.team_type || 'Not classified'}</b></span><span>Team leader<b>{selected.leader_name || 'Not assigned'}</b></span><span>Duty status<b>{statusLabel(selected)}</b></span><span>Assigned area<b>{selected.assigned_purok || 'Barangay-wide'}</b></span><span>Active assignments<b>{selected.active_dispatch_count || 0}</b></span></div>
          <h3>Team members ({selectedMembers.length})</h3>
          <div className="annex-h-members">{selectedMembers.length ? selectedMembers.map(member => <div className="annex-h-member" key={member.responder_id}><span><b>{member.full_name}</b><small>{member.title || 'Team Member'}</small></span><span className={`annex-h-status${member.is_deployed ? ' off' : ''}`}>{member.membership_status?.label || (member.is_deployed ? 'Deployed' : 'Available')}</span></div>) : <div className="annex-h-muted">No members assigned to this team.</div>}</div>
        </>}
      </section>
    </>}
    </div>}
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
  </main>
}

function DeleteTeamDialog({ team, busy, error, onCancel, onConfirm }) {
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
    <h3 id="tm-confirm-title">Delete team</h3><div>{team.team_name}</div>
    {error && <div className="tm-feedback" role="alert">{error}</div>}
    <div className="tm-team-actions"><button ref={cancel} className="bt" type="button" disabled={busy} onClick={onCancel}>Cancel</button><button className="bt d" type="button" disabled={busy} onClick={onConfirm}>{busy ? 'Deleting…' : 'Delete team'}</button></div>
  </div></div>, document.body)
}
