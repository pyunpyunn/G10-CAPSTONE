import { ChevronLeft, ChevronRight, Pencil, Plus, Save, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import Badge from '../ui/Badge'
import LoadingState from '../ui/LoadingState'
import Modal from '../ui/Modal'

function emptyTeam(defaults = {}) {
  return {
    ...defaults,
    team_code: defaults.team_code ?? '', team_name: defaults.team_name ?? '',
    team_type: defaults.team_type ?? '', duty_status: defaults.duty_status ?? '',
    assigned_purok_id: defaults.assigned_purok_id ?? '',
    leader_responder_id: defaults.leader_responder_id ?? '',
    member_ids: [...(defaults.member_ids || [])],
  }
}

export default function RescueTeamConfigModal({
  isOpen,
  workspace,
  isLoading,
  isSaving,
  error,
  onRetry,
  onClose,
  onSave,
  onDelete,
  embedded = false,
}) {
  const membersPerPage = workspace?.members_per_page || Math.max(1, workspace?.responders?.length || 1)
  const constraints = workspace?.form_constraints || {}
  const teams = useMemo(() => workspace?.teams || [], [workspace])
  const responders = useMemo(() => workspace?.responders || [], [workspace])
  const [isEditing, setIsEditing] = useState(!workspace?.teams?.length)
  const [memberPage, setMemberPage] = useState(1)
  const [form, setForm] = useState(() => {
    const firstTeam = workspace?.teams?.find((team) => Number(team.team_id) === Number(workspace.selected_team_id)) || workspace?.teams?.[0]
    return firstTeam ? teamToForm(firstTeam) : emptyTeam(workspace?.form_defaults)
  })

  const selectedTeam = useMemo(() => (
    teams.find((team) => String(team.team_id || team.team_name) === String(form.team_id || form.team_name))
  ), [teams, form.team_id, form.team_name])

  const memberSet = new Set(form.member_ids.map((id) => Number(id)))
  const memberPageCount = Math.max(1, Math.ceil(responders.length / membersPerPage))
  const currentMemberPage = Math.min(memberPage, memberPageCount)
  const visibleResponders = responders.slice((currentMemberPage - 1) * membersPerPage, currentMemberPage * membersPerPage)

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function selectTeam(team) {
    setForm(teamToForm(team))
    setIsEditing(false)
    setMemberPage(1)
  }

  function startNewTeam() {
    setForm(emptyTeam(workspace?.form_defaults))
    setIsEditing(true)
    setMemberPage(1)
  }

  function toggleMember(responderId) {
    const id = Number(responderId)
    const nextMembers = memberSet.has(id)
      ? form.member_ids.filter((memberId) => Number(memberId) !== id)
      : [...form.member_ids, id]

    setForm((current) => ({
      ...current,
      member_ids: nextMembers,
      leader_responder_id: Number(current.leader_responder_id) === id && !nextMembers.includes(id)
        ? ''
        : current.leader_responder_id,
    }))
  }

  function submitTeam(event) {
    event.preventDefault()
    onSave(normalizePayload(form))
  }

  function deleteTeam() {
    if (!form.team_id) {
      return
    }

    onDelete(form.team_id, form.team_name)
  }

  const footer = (
    <>
      <div className="rtc-footer-note">This page manages team membership and deployment readiness. It does not create or delete rescuer accounts.</div>
      <div className="rtc-footer-actions">
        {form.team_id && (
          <button className="btn btn-danger btn-sm" type="button" disabled={isSaving || !selectedTeam?.can_delete} onClick={deleteTeam}>
            <Trash2 size={14} />
            Delete team
          </button>
        )}
        <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={() => { if (isEditing && selectedTeam) { setForm(teamToForm(selectedTeam)); setIsEditing(false) } else onClose() }}>
          Cancel
        </button>
        {isEditing ? <button className="btn btn-primary btn-sm" type="submit" form="teamConfigForm" disabled={isSaving || isLoading || !workspace}>
          <Save size={14} />
          {isSaving ? 'Saving...' : 'Save team'}
        </button> : <button className="btn btn-primary btn-sm" type="button" disabled={isLoading || !selectedTeam} onClick={() => setIsEditing(true)}><Pencil size={14} />Update team</button>}
      </div>
    </>
  )

  const body = (
    <>
      {error && isLoading === false && !workspace && <div className="rtc-error" role="alert">{error}<button type="button" className="button secondary" onClick={onRetry}>Retry</button></div>}
      {isLoading ? (
        <LoadingState label="Loading team configuration..." inline />
      ) : (
        <div className="rtc-layout">
          <aside className="rtc-team-list">
            <div className="rtc-list-heading">
              <div>
                <span className="rtc-kicker">Roster structure</span>
                <strong>{teams.length} configured teams</strong>
              </div>
              <span>Saved to the database</span>
            </div>
            <button className="btn btn-secondary btn-sm rtc-new-team" type="button" disabled={isSaving} onClick={startNewTeam}>
              <Plus size={14} />
              Add rescue team
            </button>
            {teams.length === 0 && <div className="rtc-empty rtc-team-empty">No rescue teams have been configured yet.</div>}
            {teams.map((team) => {
              const isActive = String(team.team_id || team.team_name) === String(form.team_id || form.team_name)
              return (
                <button className={`rtc-team-option ${isActive ? 'active' : ''}`} type="button" key={team.team_id || team.team_name} disabled={isSaving} aria-pressed={isActive} onClick={() => selectTeam(team)}>
                  <span>
                    <strong>{team.team_name}</strong>
                    <small>{team.team_code} - {team.member_count} members</small>
                  </span>
                  <Badge tone="blue">Saved</Badge>
                </button>
              )
            })}
          </aside>

          <form className="rtc-form" id="teamConfigForm" onSubmit={submitTeam}>
            <div className="rtc-form-head">
              <div>
                <span className="rtc-kicker">Team details</span>
                <h3>{form.team_name || 'New rescue team'}</h3>
              </div>
              <Badge tone={form.team_id ? 'green' : 'amber'}>{form.team_id ? 'Configured' : 'New'}</Badge>
            </div>

            {error && (
              <div className="rtc-error">
                <span>{error}</span>
                <button className="btn btn-secondary btn-sm" type="button" onClick={onRetry} disabled={isLoading || isSaving}>Retry</button>
              </div>
            )}
            {selectedTeam && !isEditing && <div className="rtc-team-summary"><Badge tone={selectedTeam.duty_status_display?.tone}>{selectedTeam.duty_status_display?.label}</Badge><span>{selectedTeam.active_dispatch_count} active assignments</span></div>}
            <fieldset className={`rtc-fields ${!isEditing ? 'rtc-readonly' : ''}`} disabled={!isEditing || isSaving}>
            {!isEditing && selectedTeam && <dl className="ra-profile-details rtc-detail-grid">
              {[
                ['Team name', selectedTeam.team_name], ['Team code', selectedTeam.team_code],
                ['Team type', selectedTeam.team_type], ['Duty status', selectedTeam.duty_status_display?.label],
                ['Assigned purok / sitio', selectedTeam.assigned_purok || 'No fixed purok'], ['Team leader', selectedTeam.leader_name || 'No leader assigned'],
              ].map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value || 'Not recorded'}</dd></div>)}
            </dl>}
            {isEditing && <div className="rtc-grid">
              <label>
                <span>Team name</span>
                <input value={form.team_name} onChange={(event) => setField('team_name', event.target.value)} placeholder="Team name" {...constraints.team_name} />
              </label>
              <label>
                <span>Team code</span>
                <input value={form.team_code} onChange={(event) => setField('team_code', event.target.value)} placeholder="Team code" {...constraints.team_code} />
              </label>
              <label>
                <span>Team type</span>
                <input value={form.team_type} list="rescueTeamTypes" onChange={(event) => setField('team_type', event.target.value)} {...constraints.team_type} />
                <datalist id="rescueTeamTypes">{(workspace?.team_types || []).map((type) => <option value={type} key={type} />)}</datalist>
              </label>
              <label>
                <span>Duty status</span>
                <select {...constraints.duty_status} value={form.duty_status} onChange={(event) => setField('duty_status', event.target.value)}>
                  {(workspace?.duty_statuses || []).map((status) => (
                    <option value={status.key} key={status.key}>{status.label}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Assigned purok / sitio</span>
                <select value={form.assigned_purok_id} onChange={(event) => setField('assigned_purok_id', event.target.value)}>
                  <option value="">No fixed purok</option>
                  {(workspace?.puroks || []).map((purok) => (
                    <option value={purok.address_id} key={purok.address_id}>{purok.label}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Team leader</span>
                <select value={form.leader_responder_id} onChange={(event) => setField('leader_responder_id', event.target.value)}>
                  <option value="">No leader assigned</option>
                  {responders.map((responder) => (
                    <option value={responder.responder_id} key={responder.responder_id}>{responder.full_name}</option>
                  ))}
                </select>
              </label>
            </div>

            }
            </fieldset>
            <fieldset className="rtc-fields" disabled={!isEditing || isSaving}>
            <div className="rtc-member-head">
              <div>
                <span className="rtc-kicker">Members</span>
                <strong>{form.member_ids.length} selected</strong>
              </div>
              <span>{isEditing ? 'Busy responders are locked while deployed.' : 'Assigned rescue personnel'}</span>
            </div>

            <div className="rtc-member-list">
              {(isEditing ? responders.length === 0 : form.member_ids.length === 0) ? (
                <div className="rtc-empty">{isEditing ? 'No rescuer accounts created yet.' : 'No members assigned to this team.'}</div>
              ) : (
                (isEditing ? visibleResponders : responders.filter((responder) => memberSet.has(Number(responder.responder_id)))).map((responder) => {
                  const checked = memberSet.has(Number(responder.responder_id))
                  const disabled = !responder.can_change_membership

                  return (
                    <label className={`rtc-member ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}`} key={responder.responder_id}>
                      <input
                        style={!isEditing ? { visibility: 'hidden' } : undefined}
                        type="checkbox"
                        checked={checked}
                        disabled={disabled}
                        onChange={() => toggleMember(responder.responder_id)}
                      />
                      <span className="rtc-member-avatar">{initials(responder.full_name)}</span>
                      <span className="rtc-member-copy">
                        <strong>{responder.full_name}</strong>
                        <small>{responder.title} - {responder.team_name}</small>
                      </span>
                      <Badge tone={responder.membership_status?.tone}>{responder.membership_status?.label}</Badge>
                    </label>
                  )
                })
              )}
            </div>
            {isEditing && memberPageCount > 1 && (
              <div className="rtc-member-pagination">
                <button className="btn btn-secondary btn-sm" type="button" aria-label="Previous members" disabled={currentMemberPage === 1} onClick={() => setMemberPage((current) => current - 1)}>
                  <ChevronLeft size={14} />
                </button>
                <span>Page {currentMemberPage} of {memberPageCount}</span>
                <button className="btn btn-secondary btn-sm" type="button" aria-label="Next members" disabled={currentMemberPage === memberPageCount} onClick={() => setMemberPage((current) => current + 1)}>
                  <ChevronRight size={14} />
                </button>
              </div>
            )}
            </fieldset>
          </form>
        </div>
      )}
    </>
  )

  if (embedded) {
    return <div className="rtc-page-form"><div className="rtc-page-form-panel">{body}<div className="rtc-page-footer">{footer}</div></div></div>
  }

  return <Modal title="Configure rescue teams" isOpen={isOpen} onClose={onClose} footer={footer} className="rtc-modal">{body}</Modal>
}

function teamToForm(team) {
  return {
    team_id: team.team_id || null,
    team_code: team.team_code || '',
    team_name: team.team_name || '',
    team_type: team.team_type || '',
    duty_status: team.duty_status || '',
    assigned_purok_id: team.assigned_purok_id || '',
    leader_responder_id: team.leader_responder_id || '',
    member_ids: team.member_ids || [],
  }
}

function normalizePayload(form) {
  return {
    team_id: form.team_id,
    team_code: form.team_code,
    team_name: form.team_name,
    team_type: form.team_type,
    duty_status: form.duty_status,
    assigned_purok_id: form.assigned_purok_id || null,
    leader_responder_id: form.leader_responder_id || null,
    member_ids: form.member_ids,
  }
}

function initials(name = '') {
  const parts = name.split(' ').filter(Boolean)
  return (parts[0]?.[0] || 'R') + (parts[1]?.[0] || '')
}
