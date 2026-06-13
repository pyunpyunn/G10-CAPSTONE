import { useState } from 'react'
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
  onSubmit,
}) {
  return (
    <form id="dispatchForm" className="dp-dispatch-form" onSubmit={onSubmit}>
      <section className="dp-modal-section">
        <div className="dp-modal-section-head">
          <span className="dp-modal-section-title">Affected areas</span>
          <span className="dp-modal-section-sub">Purok assignment</span>
        </div>
        <div className="dp-modal-section-body">
          <RiskAreaSelector
            areas={riskAreas}
            selectedRiskId={selectedRiskId}
            onSelect={onSelectRiskArea}
          />
        </div>
      </section>

      <section className="dp-modal-section">
        <div className="dp-modal-section-head">
          <span className="dp-modal-section-title">{editingDispatch ? 'Update assignment' : 'Dispatch assignment'}</span>
          <span className={`dp-priority-pill dp-priority-${form.priority_level}`}>{label(form.priority_level)}</span>
        </div>
        <div className="dp-modal-section-body">
          <PlanStats form={form} />
          <DispatchFormFields
            form={form}
            setForm={setForm}
            assignmentOption={assignmentOption}
            setAssignmentOption={setAssignmentOption}
            teams={teams}
            responders={responders}
            editingDispatch={editingDispatch}
          />
          {formError && <div className="form-error">{formError}</div>}
        </div>
      </section>
    </form>
  )
}

function RiskAreaSelector({ areas, selectedRiskId, onSelect }) {
  const [draftAreaId, setDraftAreaId] = useState(selectedRiskId || '')

  if (areas.length === 0) {
    return <EmptyState title="No dispatch area yet" message="Areas appear after households send disaster status." />
  }

  const selectedArea = areas.find((area) => area.id === draftAreaId)

  return (
    <div className="dp-risk-selector">
      <label>
        <span className="form-label">Select purok</span>
        <select value={draftAreaId} onChange={(event) => setDraftAreaId(event.target.value)}>
          <option value="">Choose affected purok</option>
          {areas.map((area) => (
            <option value={area.id} key={area.id}>
              {area.area_name} - {area.unsafe_households || 0}/{area.total_households || 0} unsafe
            </option>
          ))}
        </select>
      </label>

      {selectedArea ? (
        <article className={`dp-risk-card ${selectedRiskId === selectedArea.id ? 'active' : ''}`}>
          <div className="dp-risk-top">
            <div>
              <div className="dp-risk-name">{selectedArea.area_name}</div>
              <div className="dp-risk-zone">
                {selectedArea.geotagged_households || 0} GPS · {selectedArea.unchecked_households || 0} unchecked
              </div>
            </div>
            <span className={`dp-priority-pill dp-priority-${selectedArea.priority}`}>{selectedArea.priority_label}</span>
          </div>
          <div className="dp-risk-metrics">
            <RiskMetric label="Total" value={selectedArea.total_households} />
            <RiskMetric label="Unsafe" value={selectedArea.unsafe_households} />
            <RiskMetric label="Unchecked" value={selectedArea.unchecked_households} />
            <RiskMetric label="Safe HH" value={selectedArea.safe_households} />
          </div>
          <div className="dp-risk-actions">
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => onSelect(selectedArea)}>
              Select purok
            </button>
          </div>
        </article>
      ) : (
        <div className="dp-household-empty">Choose one purok to prepare a dispatch assignment.</div>
      )}
    </div>
  )
}

function RiskMetric({ label: metricLabel, value }) {
  return (
    <div className="dp-risk-metric">
      <strong>{value || 0}</strong>
      <span>{metricLabel}</span>
    </div>
  )
}

function PlanStats({ form }) {
  return (
    <div className="dp-plan-stats">
      <div className="dp-plan-stat"><strong>{form.unsafe_count || 0}</strong><span>Unsafe HH</span></div>
      <div className="dp-plan-stat"><strong>{form.pending_count || 0}</strong><span>Unchecked HH</span></div>
      <div className="dp-plan-stat"><strong>{form.safe_count || 0}</strong><span>Safe HH</span></div>
    </div>
  )
}

function DispatchFormFields({ form, setForm, assignmentOption, setAssignmentOption, teams, responders, editingDispatch }) {
  const outcomeDisabled = form.status !== 'on_scene'
  const isEditing = Boolean(editingDispatch)
  const selectedTeamId = getSelectedTeamId(assignmentOption)
  const availableTeams = teams.filter((team) => team.team_id && team.is_available)
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
          </label>

          <label>
            <span className="form-label">Field status</span>
            <select value={form.status} onChange={(event) => setFormValue(setForm, 'status', event.target.value)}>
              {dispatchStatuses.map((status) => (
                <option value={status.value} key={status.value}>{status.label}</option>
              ))}
            </select>
          </label>

          <label>
            <span className="form-label">Assigned area</span>
            <input value={form.assigned_area} readOnly />
          </label>

          <ResponderChecklist
            selectedTeamId={selectedTeamId}
            responders={responders}
            selectedIds={form.selected_responder_ids || []}
            setForm={setForm}
            disabled={isEditing}
          />

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

      <div className="dp-form-block">
        <div className="dp-form-block-title">On-scene outcome update</div>
        <div className="dp-outcome-grid">
          <OutcomeInput label="Safe" name="safe_count" value={form.safe_count} disabled={outcomeDisabled} setForm={setForm} className="safe" />
          <OutcomeInput label="Evacuated" name="evacuated_count" value={form.evacuated_count} disabled={outcomeDisabled} setForm={setForm} className="evac" />
          <OutcomeInput label="Unsafe" name="unsafe_count" value={form.unsafe_count} disabled={outcomeDisabled} setForm={setForm} className="unsafe" />
          <OutcomeInput label="Pending" name="pending_count" value={form.pending_count} disabled={outcomeDisabled} setForm={setForm} className="pending" />
        </div>
      </div>

      <div className="dp-form-block">
        <label>
          <span className="form-label">Dispatch remarks</span>
          <textarea value={form.dispatch_notes} placeholder="Resource needs, vulnerable households, blocked routes, or access issues..." onChange={(event) => setFormValue(setForm, 'dispatch_notes', event.target.value)} />
        </label>
      </div>
    </>
  )
}

function handleTeamChange(value, setAssignmentOption, setForm) {
  setAssignmentOption(value)
  setForm((current) => ({
    ...current,
    selected_responder_ids: [],
    responder_count: 0,
  }))
}

function getSelectedTeamId(option) {
  const [type, id] = String(option || '').split(':')
  return type === 'team' ? id : ''
}

function ResponderChecklist({ selectedTeamId, responders, selectedIds, setForm, disabled }) {
  const availableResponders = responders.filter((responder) => (
    responder.is_available && String(responder.team_id || '') === String(selectedTeamId || '')
  ))
  const normalizedIds = selectedIds.map((id) => Number(id)).filter(Boolean)

  function toggleResponder(responderId) {
    setForm((current) => {
      const currentIds = Array.isArray(current.selected_responder_ids) ? current.selected_responder_ids.map(Number) : []
      const nextIds = currentIds.includes(responderId)
        ? currentIds.filter((id) => id !== responderId)
        : [...currentIds, responderId]

      return {
        ...current,
        selected_responder_ids: nextIds,
        responder_count: nextIds.length,
      }
    })
  }

  if (!selectedTeamId) {
    return (
      <label>
        <span className="form-label">Responders to send</span>
        <div className="dp-select-placeholder">Select a team first</div>
      </label>
    )
  }

  if (disabled) {
    return (
      <label>
        <span className="form-label">Responders to send</span>
        <div className="dp-select-placeholder">{normalizedIds.length || 1} assigned responder{(normalizedIds.length || 1) === 1 ? '' : 's'}</div>
      </label>
    )
  }

  if (availableResponders.length === 0) {
    return (
      <label>
        <span className="form-label">Responders to send</span>
        <div className="dp-select-placeholder">No available rescuer in this team</div>
      </label>
    )
  }

  return (
    <div className="dp-checklist-dropdown">
      <span className="form-label">Responders to send</span>
      <details>
        <summary>{normalizedIds.length ? `${normalizedIds.length} selected` : 'Select rescuers'}</summary>
        <div className="dp-checklist-menu">
          {availableResponders.map((responder) => (
            <label className="dp-checklist-item" key={responder.responder_id}>
              <input
                type="checkbox"
                checked={normalizedIds.includes(Number(responder.responder_id))}
                onChange={() => toggleResponder(Number(responder.responder_id))}
              />
              <span>
                <strong>{responder.full_name}</strong>
                <small>{responder.team_name}</small>
              </span>
            </label>
          ))}
        </div>
      </details>
    </div>
  )
}

function OutcomeInput({ label: inputLabel, name, value, disabled, setForm, className }) {
  return (
    <label className={`dp-outcome-input ${className}`}>
      <span className="form-label">{inputLabel}</span>
      <input min="0" type="number" value={value} disabled={disabled} onChange={(event) => setFormNumber(setForm, name, event.target.value)} />
    </label>
  )
}
