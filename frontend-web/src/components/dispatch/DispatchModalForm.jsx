import { useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import {
  dispatchStatuses,
  label,
  priorityOptions,
  setFormNumber,
  setFormValue,
} from '../../utils/dispatchHelpers'

export default function DispatchModalForm({
  editingDispatch,
  form,
  setForm,
  formError,
  assignmentOption,
  setAssignmentOption,
  teams,
  responders,
  riskAreas,
  selectedRiskId,
  onSelectRiskArea,
  onSelectRiskHousehold,
  onSubmit,
  showOutcomeUpdate = true,
}) {
  const [isAreasCollapsed, setIsAreasCollapsed] = useState(false)

  return (
    <form id="dispatchForm" className="dp-dispatch-form" onSubmit={onSubmit}>
      <div className={`dp-dispatch-panels ${isAreasCollapsed ? 'is-areas-collapsed' : ''}`}>
        <div className="dp-panel-column dp-affected-areas-column">
          <section className="dp-modal-section dp-affected-areas-panel">
            <div className="dp-modal-section-head">
              <span className="dp-modal-section-title">{isAreasCollapsed ? 'Areas' : 'Affected areas'}</span>
              {!isAreasCollapsed && <span className="dp-modal-section-sub">Purok assignment</span>}
              <button
                className="dp-panel-toggle"
                type="button"
                aria-label={isAreasCollapsed ? 'Expand affected areas' : 'Collapse affected areas'}
                title={isAreasCollapsed ? 'Expand affected areas' : 'Collapse affected areas'}
                onClick={() => setIsAreasCollapsed((current) => !current)}
              >
                {isAreasCollapsed ? <ChevronRight size={15} /> : <ChevronLeft size={15} />}
              </button>
            </div>
            {!isAreasCollapsed && (
              <div className="dp-modal-section-body dp-affected-areas-body">
                <RiskAreaList
                  areas={riskAreas}
                  selectedRiskId={selectedRiskId}
                  selectedHouseholdId={form.household_id}
                  onSelect={onSelectRiskArea}
                  onSelectHousehold={onSelectRiskHousehold}
                />
              </div>
            )}
          </section>
        </div>

        <div className="dp-panel-column dp-dispatch-assignment-column">
          <section className="dp-modal-section">
            <div className="dp-modal-section-head">
              <span className="dp-modal-section-title">{editingDispatch ? 'Update assignment' : 'Dispatch assignment'}</span>
              <span className={`dp-priority-pill dp-priority-${form.priority_level}`}>{label(form.priority_level)}</span>
            </div>
            <div className="dp-modal-section-body">
              <TargetHouseholdDetails household={form.target_household} />
              <DispatchFormFields
                form={form}
                setForm={setForm}
                assignmentOption={assignmentOption}
                setAssignmentOption={setAssignmentOption}
                teams={teams}
                responders={responders}
                editingDispatch={editingDispatch}
                showOutcomeUpdate={showOutcomeUpdate}
              />
              {formError && <div className="form-error">{formError}</div>}
            </div>
          </section>
        </div>
      </div>
    </form>
  )
}

function RiskAreaList({ areas, selectedRiskId, selectedHouseholdId, onSelect, onSelectHousehold }) {
  const selectedArea = areas.find((area) => area.id === selectedRiskId) || null

  if (areas.length === 0) {
    return <EmptyState title="No dispatch area yet" message="Areas appear after households send disaster status." />
  }

  return (
    <div className="dp-risk-selector">
      <label>
        <span className="form-label">Select purok</span>
        <select
          value={selectedRiskId}
          onChange={(event) => {
            const area = areas.find((item) => item.id === event.target.value)
            if (area) {
              onSelect(area)
            }
          }}
        >
          <option value="">Choose affected purok</option>
          {areas.map((area) => (
            <option value={area.id} key={area.id}>
              {area.area_name} - {area.unsafe_households || 0}/{area.total_households || 0} unsafe
            </option>
          ))}
        </select>
      </label>

      {selectedArea ? (
        <div className="dp-purok-households">
          <div className="dp-purok-summary">
            <div><strong>{selectedArea.unsafe_households || 0}</strong><span>Unsafe HH</span></div>
            <div><strong>{selectedArea.unchecked_households || 0}</strong><span>Unchecked HH</span></div>
            <div><strong>{selectedArea.to_cover || 0}</strong><span>To cover</span></div>
          </div>
          <HouseholdTargetList
            area={selectedArea}
            selectedHouseholdId={selectedHouseholdId}
            onSelectHousehold={onSelectHousehold}
          />
        </div>
      ) : (
        <div className="dp-household-empty">Select a purok to display its households.</div>
      )}
    </div>
  )
}

function TargetHouseholdDetails({ household }) {
  return (
    <div className="dp-target-household">
      <span className="dp-form-block-title">Target household</span>
      {household ? (
        <div className="dp-target-household-table-wrap">
          <table className="dp-target-household-table">
            <thead>
              <tr><th>Household ID</th><th>Members</th><th>Status</th><th>Reported unsafe</th></tr>
            </thead>
            <tbody>
              <tr>
                <td>{household.household_id}</td>
                <td>{household.member_count || 0}</td>
                <td>{household.status_label || 'Status unavailable'}</td>
                <td>{household.reported_unsafe_count || 0}</td>
              </tr>
            </tbody>
          </table>
        </div>
      ) : (
        <div className="dp-select-placeholder">Select a household from the purok table.</div>
      )}
    </div>
  )
}

function DispatchFormFields({ form, setForm, assignmentOption, setAssignmentOption, teams, responders, editingDispatch, showOutcomeUpdate }) {
  const outcomeDisabled = !['on_scene', 'completed'].includes(form.status)
  const isEditing = Boolean(editingDispatch)
  const selectedTeamId = getSelectedTeamId(assignmentOption)
  const availableTeams = teams.filter((team) => team.team_id && team.is_available
    && (form.dispatch_type !== 'welfare_check' || Number(team.available_responder_count) >= 2))
  const currentTeam = teams.find((team) => String(team.team_id) === String(selectedTeamId))

  return (
    <>
      <div className="dp-form-block">
        <div className="dp-form-block-title">Responder assignment</div>
        <div className="dp-field-grid">
          <label>
            <span className="form-label">Available team</span>
            <select value={assignmentOption} disabled={isEditing} onChange={(event) => handleTeamChange(event.target.value, setAssignmentOption, setForm)}>
              <option value="">Select available team</option>
              {isEditing && currentTeam && (
                <option value={`team:${currentTeam.team_id}`}>{currentTeam.team_name}</option>
              )}
              {availableTeams.map((team) => (
                <option value={`team:${team.team_id}`} key={`team-${team.team_id}`}>
                  {team.team_name}
                </option>
              ))}
            </select>
            <span className="dp-field-help">
              {isEditing ? 'Assigned responders cannot be changed here.' : 'Choose a team and the number of available rescuers to send.'}
            </span>
          </label>

          <label>
            <span className="form-label">Field status</span>
            <select value={form.status} onChange={(event) => setFormValue(setForm, 'status', event.target.value)}>
              {dispatchStatuses.map((status) => (
                <option value={status.value} key={status.value}>{status.label}</option>
              ))}
            </select>
          </label>

          {form.dispatch_type === 'welfare_check' && (
            <div className="dp-field-help">Welfare Check: exactly two available rescuers are required.</div>
          )}

          <label>
            <span className="form-label">Assigned area</span>
            <input value={form.assigned_area} readOnly />
          </label>

          <label>
            <span className="form-label">Rescuers to dispatch</span>
            <input
              type="number"
              min="1"
              max={Math.max(1, Number(currentTeam?.available_responder_count) || 0)}
              value={form.responder_count || 1}
              disabled={isEditing || !currentTeam || !currentTeam.available_responder_count}
              onChange={(event) => setFormNumber(setForm, 'responder_count', form.dispatch_type === 'welfare_check' ? 2 : event.target.value)}
            />
            <span className="dp-field-help">{currentTeam?.available_responder_count || 0} currently available in this team. The system selects and notifies them.</span>
          </label>

          <label className="dp-field-wide">
            <span className="form-label">Priority level</span>
            <select value={form.priority_level} onChange={(event) => setFormValue(setForm, 'priority_level', event.target.value)}>
              {priorityOptions.map((priority) => (
                <option value={priority.value} key={priority.value}>{priority.label}</option>
              ))}
            </select>
          </label>
        </div>
      </div>

      {showOutcomeUpdate && (
        <div className="dp-form-block">
          <div className="dp-form-block-title">On-scene outcome update</div>
          <div className="dp-outcome-grid">
            <OutcomeInput label="Safe" name="safe_count" value={form.safe_count} disabled={outcomeDisabled} setForm={setForm} className="safe" />
            <OutcomeInput label="Evacuated" name="evacuated_count" value={form.evacuated_count} disabled={outcomeDisabled} setForm={setForm} className="evac" />
            <OutcomeInput label="Unsafe" name="unsafe_count" value={form.unsafe_count} disabled={outcomeDisabled} setForm={setForm} className="unsafe" />
            <OutcomeInput label="Pending" name="pending_count" value={form.pending_count} disabled={outcomeDisabled} setForm={setForm} className="pending" />
          </div>
        </div>
      )}

      <div className="dp-form-block">
        <label>
          <span className="form-label">Dispatch remarks</span>
          <textarea value={form.dispatch_notes} placeholder="Resource needs, vulnerable households, blocked routes, or access issues..." onChange={(event) => setFormValue(setForm, 'dispatch_notes', event.target.value)} />
        </label>
      </div>
    </>
  )
}

function HouseholdTargetList({ area, selectedHouseholdId, onSelectHousehold }) {
  const households = area.households || area.recommended_households || []

  if (households.length === 0) {
    return <div className="dp-household-empty">No household rows found for this purok.</div>
  }

  return (
    <div className="dp-household-table-wrap">
      <table className="dp-household-table">
        <thead>
          <tr><th>Family name</th><th>Household ID</th><th>Action</th></tr>
        </thead>
        <tbody>
          {households.map((household) => {
            const cannotSelect = !household.is_available_for_dispatch || !household.has_geotag
            const isSelected = selectedHouseholdId === household.household_id

            return (
              <tr className={isSelected ? 'active' : ''} key={household.household_id}>
                <td>{household.household_name}</td>
                <td>{household.household_id}</td>
                <td>
                  <button className="dp-household-select-button" type="button" disabled={cannotSelect} onClick={() => onSelectHousehold(area, household)}>
                    Select
                  </button>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

function handleTeamChange(value, setAssignmentOption, setForm) {
  setAssignmentOption(value)
  setForm((current) => ({
    ...current,
    selected_responder_ids: [],
    responder_count: 1,
  }))
}

function getSelectedTeamId(option) {
  const [type, id] = String(option || '').split(':')
  return type === 'team' ? id : ''
}


function OutcomeInput({ label: inputLabel, name, value, disabled, setForm, className }) {
  return (
    <label className={`dp-outcome-input ${className}`}>
      <span className="form-label">{inputLabel}</span>
      <input min="0" type="number" value={value} disabled={disabled} onChange={(event) => setFormNumber(setForm, name, event.target.value)} />
    </label>
  )
}
