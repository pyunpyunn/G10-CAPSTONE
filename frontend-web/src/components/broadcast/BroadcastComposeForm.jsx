import { Bell, CheckCircle2, MapPin, Plus, Send, Trash2 } from 'lucide-react'
import Badge from '../ui/Badge'
import {
  durationOptions,
  eventTone,
  labelFromValue,
  priorityOptions,
  priorityTone,
} from '../../utils/broadcastHelpers'

export default function BroadcastComposeForm({
  activeEvent,
  form,
  disasterTypes,
  severityLevels,
  puroks,
  statusOptions,
  selectedPurok,
  selectedPriority,
  selectedStatuses,
  directPuroks,
  recipientNote,
  formError,
  isSaving,
  onChange,
  onSelectPurok,
  onSelectPriority,
  onAddPurok,
  onRemovePurok,
  onToggleStatus,
  onSubmit,
  onCancel,
}) {
  return (
    <form className="broadcast-compose" onSubmit={onSubmit}>
      <BroadcastEventFields
        activeEvent={activeEvent}
        form={form}
        disasterTypes={disasterTypes}
        severityLevels={severityLevels}
        onChange={onChange}
      />

      <BroadcastAreaFields
        form={form}
        puroks={puroks}
        selectedPurok={selectedPurok}
        selectedPriority={selectedPriority}
        directPuroks={directPuroks}
        recipientNote={recipientNote}
        onChange={onChange}
        onSelectPurok={onSelectPurok}
        onSelectPriority={onSelectPriority}
        onAddPurok={onAddPurok}
        onRemovePurok={onRemovePurok}
      />

      <BroadcastStatusFields
        statusOptions={statusOptions}
        selectedStatuses={selectedStatuses}
        onToggleStatus={onToggleStatus}
      />

      <BroadcastMessageFields form={form} onChange={onChange} />

      {formError && <div className="form-error">{formError}</div>}

      <div className="broadcast-form-actions">
        <button className="btn btn-secondary" type="button" disabled={isSaving} onClick={onCancel}>
          Cancel
        </button>
        <button className="btn btn-primary" type="submit" disabled={isSaving}>
          <Send size={15} />
          {isSaving ? 'Saving...' : 'Save Broadcast'}
        </button>
      </div>
    </form>
  )
}

function BroadcastEventFields({ activeEvent, form, disasterTypes, severityLevels, onChange }) {
  return (
    <section className="broadcast-section">
      <div className="broadcast-section-head">
        <span>Declaring Disaster</span>
        {activeEvent && <Badge tone={eventTone(activeEvent.severity_key)}>Active</Badge>}
      </div>
      <div className="broadcast-field-grid">
        <label>
          <span>Event name</span>
          <input
            type="text"
            value={form.event_name}
            disabled={Boolean(activeEvent)}
            maxLength={100}
            onChange={(event) => onChange('event_name', event.target.value)}
            placeholder="Example: Flood Monitoring"
            required
          />
        </label>
        <label>
          <span>Disaster type</span>
          <select
            value={form.type_id}
            disabled={Boolean(activeEvent)}
            onChange={(event) => onChange('type_id', event.target.value)}
            required
          >
            {disasterTypes.map((type) => (
              <option value={type.type_id} key={type.type_id}>{type.type_name}</option>
            ))}
          </select>
        </label>
        <label>
          <span>Severity</span>
          <select value={form.severity_id} onChange={(event) => onChange('severity_id', event.target.value)} required>
            {severityLevels.map((severity) => (
              <option value={severity.severity_id} key={severity.severity_id}>{severity.severity_label}</option>
            ))}
          </select>
        </label>
        <label>
          <span>Estimated duration</span>
          <select value={form.estimated_duration} onChange={(event) => onChange('estimated_duration', event.target.value)}>
            {durationOptions.map((option) => (
              <option value={option.value} key={option.value}>{option.label}</option>
            ))}
          </select>
        </label>
        {!activeEvent && (
          <>
            <label>
              <span>Date started</span>
              <input type="date" value={form.started_date} onChange={(event) => onChange('started_date', event.target.value)} required />
            </label>
            <label>
              <span>Time started</span>
              <input type="time" value={form.started_time} onChange={(event) => onChange('started_time', event.target.value)} required />
            </label>
          </>
        )}
      </div>
    </section>
  )
}

function BroadcastAreaFields({
  form,
  puroks,
  selectedPurok,
  selectedPriority,
  directPuroks,
  recipientNote,
  onChange,
  onSelectPurok,
  onSelectPriority,
  onAddPurok,
  onRemovePurok,
}) {
  const needsPuroks = ['selected_puroks', 'local_direct_impact'].includes(form.scope_type)

  return (
    <section className="broadcast-section">
      <div className="broadcast-section-head">
        <span>Affected Areas</span>
        <MapPin size={15} />
      </div>
      <div className="broadcast-field-grid">
        <label>
          <span>Recipients</span>
          <select value={form.scope_type} onChange={(event) => onChange('scope_type', event.target.value)}>
            <option value="barangay_wide">Barangay-wide households + responders</option>
            <option value="selected_puroks">Selected puroks + responders</option>
            <option value="local_direct_impact">Direct-impact puroks</option>
            <option value="rescuers_only">Responders only</option>
          </select>
        </label>
        <label>
          <span>Base priority</span>
          <select value={form.base_priority} onChange={(event) => onChange('base_priority', event.target.value)}>
            {priorityOptions.map((option) => (
              <option value={option.value} key={option.value}>{option.label}</option>
            ))}
          </select>
        </label>
      </div>
      <div className={`bc-recipient-note ${needsPuroks ? 'local' : 'wide'}`}>
        {recipientNote}
      </div>
      {needsPuroks && (
        <div className="bc-purok-tools">
          <div className="bc-purok-add">
            <select value={selectedPurok} onChange={(event) => onSelectPurok(event.target.value)}>
              {puroks.map((purok) => (
                <option value={purok.name} key={`${purok.name}-${purok.source}`}>{purok.name}</option>
              ))}
            </select>
            <select value={selectedPriority} onChange={(event) => onSelectPriority(event.target.value)}>
              {priorityOptions.map((option) => (
                <option value={option.value} key={option.value}>{option.label}</option>
              ))}
            </select>
            <button className="btn btn-secondary btn-sm" type="button" onClick={onAddPurok}>
              <Plus size={14} />
              Add
            </button>
          </div>
          <div className="bc-direct-list">
            {directPuroks.length === 0 ? (
              <span className="bc-direct-empty">No direct-impact purok selected.</span>
            ) : (
              directPuroks.map((purok) => (
                <span className="bc-direct-chip" key={purok.name}>
                  {purok.name}
                  <Badge tone={priorityTone(purok.priority)}>{labelFromValue(priorityOptions, purok.priority)}</Badge>
                  <button type="button" aria-label={`Remove ${purok.name}`} onClick={() => onRemovePurok(purok.name)}>
                    <Trash2 size={12} />
                  </button>
                </span>
              ))
            )}
          </div>
        </div>
      )}
    </section>
  )
}

function BroadcastStatusFields({ statusOptions, selectedStatuses, onToggleStatus }) {
  return (
    <section className="broadcast-section">
      <div className="broadcast-section-head">
        <span>Household Mobile Status Buttons</span>
        <small>{selectedStatuses.length}/4 selected</small>
      </div>
      <div className="bc-status-grid">
        {statusOptions.map((option) => {
          const isSelected = selectedStatuses.includes(option.key)
          const isDisabled = !isSelected && selectedStatuses.length >= 4

          return (
            <button
              className={`bc-status-toggle ${isSelected ? 'selected' : ''}`}
              type="button"
              disabled={isDisabled}
              key={option.key}
              onClick={() => onToggleStatus(option.key)}
            >
              <CheckCircle2 size={15} />
              {option.label}
            </button>
          )
        })}
      </div>
    </section>
  )
}

function BroadcastMessageFields({ form, onChange }) {
  return (
    <section className="broadcast-section">
      <div className="broadcast-section-head">
        <span>Message</span>
        <Bell size={15} />
      </div>
      <div className="broadcast-field-grid one">
        <label>
          <span>Broadcast title</span>
          <input
            type="text"
            value={form.broadcast_title}
            maxLength={150}
            onChange={(event) => onChange('broadcast_title', event.target.value)}
            required
          />
        </label>
        <label>
          <span>Official instruction</span>
          <textarea
            value={form.message}
            maxLength={2000}
            onChange={(event) => onChange('message', event.target.value)}
            required
          />
        </label>
      </div>
      <label className="bc-check-row">
        <input
          type="checkbox"
          checked={form.attach_route}
          onChange={(event) => onChange('attach_route', event.target.checked)}
        />
        <span>Attach evacuation route when map routing is available</span>
      </label>
    </section>
  )
}
