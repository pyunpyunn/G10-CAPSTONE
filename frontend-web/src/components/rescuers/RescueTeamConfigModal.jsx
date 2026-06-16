import { Plus, Save, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import Badge from '../ui/Badge'
import LoadingState from '../ui/LoadingState'
import Modal from '../ui/Modal'

const blankTeam = {
  team_id: null,
  team_code: '',
  team_name: '',
  team_type: '',
  duty_status: 'standby',
  assigned_purok_id: '',
  leader_responder_id: '',
  member_ids: [],
}

export default function RescueTeamConfigModal({
  isOpen,
  workspace,
  isLoading,
  isSaving,
  error,
  onClose,
  onSave,
  onDelete,
}) {
  const teams = useMemo(() => workspace?.teams || [], [workspace])
  const responders = useMemo(() => workspace?.responders || [], [workspace])
  const [form, setForm] = useState(() => {
    const firstTeam = workspace?.teams?.[0]
    return firstTeam ? teamToForm(firstTeam) : blankTeam
  })

  const selectedTeam = useMemo(() => (
    teams.find((team) => String(team.team_id || team.team_name) === String(form.team_id || form.team_name))
  ), [teams, form.team_id, form.team_name])

  const memberSet = new Set(form.member_ids.map((id) => Number(id)))

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function selectTeam(team) {
    setForm(teamToForm(team))
  }

  function startNewTeam() {
    setForm({ ...blankTeam, duty_status: 'standby' })
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
      <div className="rtc-footer-note">Team changes update responder group assignment only. Rescuer accounts are retained.</div>
      <div className="rtc-footer-actions">
        {form.team_id && (
          <button className="btn btn-danger btn-sm" type="button" disabled={isSaving || selectedTeam?.can_delete === false} onClick={deleteTeam}>
            <Trash2 size={14} />
            Delete team
          </button>
        )}
        <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>
          Cancel
        </button>
        <button className="btn btn-primary btn-sm" type="submit" form="teamConfigForm" disabled={isSaving}>
          <Save size={14} />
          {isSaving ? 'Saving...' : 'Save team'}
        </button>
      </div>
    </>
  )

  return (
    <Modal title="Configure rescue teams" isOpen={isOpen} onClose={onClose} footer={footer} className="rtc-modal">
      {isLoading ? (
        <LoadingState label="Loading team configuration..." inline />
      ) : (
        <div className="rtc-layout">
          <aside className="rtc-team-list">
            <button className="btn btn-secondary btn-sm rtc-new-team" type="button" onClick={startNewTeam}>
              <Plus size={14} />
              Add rescue team
            </button>
            {teams.map((team) => {
              const isActive = String(team.team_id || team.team_name) === String(form.team_id || form.team_name)
              return (
                <button className={`rtc-team-option ${isActive ? 'active' : ''}`} type="button" key={team.team_id || team.team_name} onClick={() => selectTeam(team)}>
                  <span>
                    <strong>{team.team_name}</strong>
                    <small>{team.team_code} - {team.member_count || 0} members</small>
                  </span>
                  <Badge tone={team.is_configured ? 'blue' : 'gray'}>{team.is_configured ? 'Saved' : 'Template'}</Badge>
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

            {error && <div className="form-error">{error}</div>}
            {selectedTeam?.active_dispatch_count > 0 && (
              <div className="rtc-warning">This team has an active dispatch. Delete is locked until the dispatch is completed.</div>
            )}

            <div className="rtc-grid">
              <label>
                <span>Team name</span>
                <input value={form.team_name} onChange={(event) => setField('team_name', event.target.value)} placeholder="Search & Rescue" required />
              </label>
              <label>
                <span>Team code</span>
                <input value={form.team_code} onChange={(event) => setField('team_code', event.target.value.toUpperCase())} placeholder="SAR" maxLength={8} required />
              </label>
              <label>
                <span>Team type</span>
                <select value={form.team_type} onChange={(event) => setField('team_type', event.target.value)} required>
                  <option value="">Select team type</option>
                  {(workspace?.team_types || []).map((type) => (
                    <option value={type} key={type}>{type}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Duty status</span>
                <select value={form.duty_status} onChange={(event) => setField('duty_status', event.target.value)}>
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

            <div className="rtc-member-head">
              <div>
                <span className="rtc-kicker">Members</span>
                <strong>{form.member_ids.length} selected</strong>
              </div>
              <span>Busy responders are locked while deployed.</span>
            </div>

            <div className="rtc-member-list">
              {responders.length === 0 ? (
                <div className="rtc-empty">No rescuer accounts created yet.</div>
              ) : (
                responders.map((responder) => {
                  const checked = memberSet.has(Number(responder.responder_id))
                  const busyInOtherTeam = responder.is_busy && responder.team_id && Number(responder.team_id) !== Number(form.team_id)
                  const busyInCurrentTeam = responder.is_busy && checked
                  const disabled = busyInOtherTeam || busyInCurrentTeam

                  return (
                    <label className={`rtc-member ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}`} key={responder.responder_id}>
                      <input
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
                      <Badge tone={responder.is_busy ? 'amber' : 'green'}>{responder.is_busy ? 'Busy' : 'Available'}</Badge>
                    </label>
                  )
                })
              )}
            </div>
          </form>
        </div>
      )}
    </Modal>
  )
}

function teamToForm(team) {
  return {
    team_id: team.team_id || null,
    team_code: team.team_code || '',
    team_name: team.team_name || '',
    team_type: team.team_type || '',
    duty_status: team.duty_status === 'not_created' ? 'standby' : team.duty_status || 'standby',
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
