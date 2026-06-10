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
}) {
  return (
    <form id="dispatchForm" className="dp-dispatch-form" onSubmit={onSubmit}>
      <section className="dp-modal-section">
        <div className="dp-modal-section-head">
          <span className="dp-modal-section-title">Affected / unsafe areas</span>
          <span className="dp-modal-section-sub">From Household Status</span>
        </div>
        <div className="dp-modal-section-body">
          <RiskAreaList
            areas={riskAreas}
            selectedRiskId={selectedRiskId}
            selectedHouseholdId={form.household_id}
            onSelect={onSelectRiskArea}
            onSelectHousehold={onSelectRiskHousehold}
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

function RiskAreaList({ areas, selectedRiskId, selectedHouseholdId, onSelect, onSelectHousehold }) {
  if (areas.length === 0) {
    return <EmptyState title="No high-risk areas yet" message="Unsafe or unchecked household areas will appear here from Household Status." />
  }

  return (
    <div className="dp-risk-list">
      {areas.map((area) => (
        <article className={`dp-risk-card ${selectedRiskId === area.id ? 'active' : ''}`} key={area.id}>
          <div className="dp-risk-top">
            <div>
              <div className="dp-risk-name">{area.area_name}</div>
              <div className="dp-risk-zone">{area.zone} · {area.geotagged_households || 0} with GPS</div>
            </div>
            <span className={`dp-priority-pill dp-priority-${area.priority}`}>{area.priority_label}</span>
          </div>
          <div className="dp-risk-context">{area.context}</div>
          <div className="dp-risk-metrics">
            <RiskMetric label="Total" value={area.total_households} />
            <RiskMetric label="GPS" value={area.geotagged_households} />
            <RiskMetric label="Unsafe" value={area.unsafe_households} />
            <RiskMetric label="To cover" value={area.to_cover} />
          </div>
          <div className="dp-risk-actions">
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => onSelect(area)}>
              Use this purok
            </button>
          </div>
          <HouseholdTargetList
            area={area}
            selectedHouseholdId={selectedHouseholdId}
            onSelectHousehold={onSelectHousehold}
          />
        </article>
      ))}
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
      <div className="dp-plan-stat"><strong>{form.pending_count || 0}</strong><span>Pending HH</span></div>
      <div className="dp-plan-stat"><strong>{form.households_to_cover || 0}</strong><span>To cover</span></div>
      <div className="dp-plan-stat"><strong>{form.household_id || '-'}</strong><span>Route target</span></div>
    </div>
  )
}

function DispatchFormFields({ form, setForm, assignmentOption, setAssignmentOption, teams, responders, editingDispatch }) {
  const outcomeDisabled = form.status !== 'on_scene'
  const isEditing = Boolean(editingDispatch)

  return (
    <>
      <div className="dp-form-block">
        <div className="dp-form-block-title">Responder assignment</div>
        <div className="dp-field-grid">
          <label>
            <span className="form-label">Team / responder</span>
            <select value={assignmentOption} disabled={isEditing} onChange={(event) => setAssignmentOption(event.target.value)}>
              <option value="">Select assignment</option>
              {teams.filter((team) => team.team_id).map((team) => (
                <option
                  value={`team:${team.team_id}`}
                  key={`team-${team.team_id}`}
                  disabled={!team.is_available}
                >
                  {team.team_name}{team.is_available ? '' : ' - busy'}
                </option>
              ))}
              {responders.map((responder) => (
                <option
                  value={`responder:${responder.responder_id}`}
                  key={`responder-${responder.responder_id}`}
                  disabled={!responder.is_available}
                >
                  {responder.full_name} ({responder.team_name}){responder.is_available ? '' : ` - ${responder.status?.label || 'busy'}`}
                </option>
              ))}
            </select>
            <span className="dp-field-help">
              {isEditing ? 'Responder cannot be changed after dispatch is created.' : 'Busy responders are locked until their active assignment is completed.'}
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

          <label>
            <span className="form-label">Assigned area</span>
            <input value={form.assigned_area} onChange={(event) => setFormValue(setForm, 'assigned_area', event.target.value)} />
          </label>

          <label>
            <span className="form-label">Households to cover</span>
            <input min="0" type="number" value={form.households_to_cover} onChange={(event) => setFormNumber(setForm, 'households_to_cover', event.target.value)} />
          </label>

          <label>
            <span className="form-label">Responders to send</span>
            <input min="1" type="number" value={form.responder_count} onChange={(event) => setFormNumber(setForm, 'responder_count', event.target.value)} />
          </label>

          <label>
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
        <div className="dp-outcome-note">
          Outcome fields are enabled only when field status is On-scene.
        </div>
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

function HouseholdTargetList({ area, selectedHouseholdId, onSelectHousehold }) {
  const households = area.households || area.recommended_households || []

  if (households.length === 0) {
    return <div className="dp-household-empty">No household rows found for this purok.</div>
  }

  return (
    <div className="dp-household-targets">
      <div className="dp-household-target-head">
        <span>Households in this purok</span>
        <small>Select a GPS household for routing</small>
      </div>
      {households.map((household) => {
        const cannotSelect = !household.is_available_for_dispatch || !household.has_geotag
        const isSelected = selectedHouseholdId === household.household_id

        return (
          <button
            className={`dp-household-target ${isSelected ? 'active' : ''}`}
            type="button"
            key={household.household_id}
            disabled={cannotSelect}
            onClick={() => onSelectHousehold(area, household)}
          >
            <div>
              <strong>{household.household_name}</strong>
              <span>{household.household_id}{household.address ? ` · ${household.address}` : ''}</span>
            </div>
            <div className="dp-household-badges">
              <span className={`badge b-${statusTone(household.status_key)}`}>{household.status_label || 'Unchecked'}</span>
              <span className={`dp-mini-pill ${household.has_geotag ? 'ok' : 'muted'}`}>{household.has_geotag ? 'GPS' : 'No GPS'}</span>
              {!household.is_available_for_dispatch && <span className="dp-mini-pill busy">Assigned</span>}
            </div>
          </button>
        )
      })}
    </div>
  )
}

function statusTone(statusKey) {
  if (['safe', 'evacuated', 'checked'].includes(statusKey)) {
    return 'green'
  }

  if (['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'].includes(statusKey)) {
    return 'red'
  }

  return 'gray'
}

function OutcomeInput({ label: inputLabel, name, value, disabled, setForm, className }) {
  return (
    <label className={`dp-outcome-input ${className}`}>
      <span className="form-label">{inputLabel}</span>
      <input min="0" type="number" value={value} disabled={disabled} onChange={(event) => setFormNumber(setForm, name, event.target.value)} />
    </label>
  )
}
