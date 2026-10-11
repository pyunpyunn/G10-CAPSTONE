import { Plus, Send, Trash2 } from 'lucide-react'

export default function BroadcastComposeForm({
  activeEvent,
  form,
  disasterTypes,
  severityLevels,
  puroks,
  statusOptions,
  selectedPurok,
  selectedStatuses,
  directPuroks,
  householdCount,
  isSaving,
  hasChanges,
  onChange,
  onSelectPurok,
  onAddPurok,
  onRemovePurok,
  onStatusChange,
  onAddStatus,
  onManageDisasterTypes,
  onSubmit,
}) {
  const isUpdateMode = Boolean(activeEvent)
  const durationMatch = String(form.estimated_duration || '').match(/^(\d+)\s*(hours?|days?)$/i)
  const durationValue = durationMatch?.[1] || '12'
  const durationUnit = durationMatch?.[2]?.toLowerCase().startsWith('day') ? 'Days' : 'Hours'
  const severity = severityLevels.find((item) => String(item.severity_id) === String(form.severity_id))

  return (
    <form className="broadcast-compose" noValidate onSubmit={onSubmit} style={{ '--broadcast-severity': severityColor(severity?.severity_key) }}>
      <section className="bc-step">
        <StepHeading number="1" title="Disaster Details" />
        <div className="bc-fields bc-fields-details">
          <div className="bc-field">
            <span className="bc-field-label">Disaster type</span>
            <div className="bc-catalog-control">
              <select value={form.type_id} onChange={(event) => onChange('type_id', event.target.value)} required>
                <option value="">Select type</option>
                {disasterTypes.map((type) => <option value={type.type_id} key={type.type_id}>{type.type_name}</option>)}
              </select>
              <button className="bc-catalog-trigger" type="button" onClick={onManageDisasterTypes}>Manage types</button>
            </div>
          </div>
          <Field label="Disaster name" counter={`${form.event_name.length}/20`} limitReached={form.event_name.length >= 20}>
            <input value={form.event_name} maxLength={20} onChange={(event) => onChange('event_name', event.target.value)} required />
          </Field>
          <Field label="Severity">
            <select value={form.severity_id} onChange={(event) => onChange('severity_id', event.target.value)} required>
              {severityLevels.map((item) => <option value={item.severity_id} key={item.severity_id}>{item.severity_label}</option>)}
            </select>
          </Field>
          <Field label="Est. duration">
            <div className="bc-duration-input">
              <input
                type="number"
                min="1"
                max="999"
                value={durationValue}
                onChange={(event) => onChange('estimated_duration', `${event.target.value} ${durationUnit.toLowerCase()}`)}
                required
              />
              <select
                aria-label="Duration unit"
                value={durationUnit}
                onChange={(event) => onChange('estimated_duration', `${durationValue} ${event.target.value.toLowerCase()}`)}
              >
                <option>Hours</option><option>Days</option>
              </select>
            </div>
          </Field>
        </div>
      </section>

      <section className="bc-step">
        <StepHeading number="2" title="Affected Areas" aside={`${targetHouseholds(form.scope_type, directPuroks, puroks, householdCount).toLocaleString()} households`} />
        <div className="bc-area-row">
          <Field label="Coverage">
            <select value={form.scope_type} onChange={(event) => onChange('scope_type', event.target.value)}>
              <option value="barangay_wide">Barangay-wide</option>
              <option value="selected_puroks">Selected sitios</option>
            </select>
          </Field>
          {form.scope_type === 'selected_puroks' && (
            <Field label="Add sitio">
              <select
                value={selectedPurok}
                onChange={(event) => {
                  onSelectPurok(event.target.value)
                  if (event.target.value) onAddPurok(event.target.value)
                }}
              >
                <option value="">Choose a sitio</option>
                {puroks.filter((purok) => !directPuroks.some((item) => item.name === purok.name)).map((purok) => (
                  <option value={purok.name} key={purok.name}>{purok.name}</option>
                ))}
              </select>
            </Field>
          )}
          {form.scope_type === 'selected_puroks' && (
            <div className="bc-selected-areas" aria-live="polite">
              {directPuroks.map((purok) => (
                <span className="bc-area-chip" key={purok.name}>
                  {purok.name}
                  <button type="button" aria-label={`Remove ${purok.name}`} onClick={() => onRemovePurok(purok.name)}><Trash2 size={12} /></button>
                </span>
              ))}
              {directPuroks.length === 0 && <span className="bc-empty-chip">Select one or more sitios</span>}
            </div>
          )}
        </div>
      </section>

      <section className="bc-step">
        <StepHeading number="3" title="Mobile Push" aside={`${selectedStatuses.filter(Boolean).length} / 4 pushed`} />
        <div className="bc-status-slots">
          {[0, 1, 2, 3].map((slot) => (
            <Field label={`Status ${slot + 1}`} key={slot}>
              <select
                value={selectedStatuses[slot] || ''}
                onChange={(event) => onStatusChange(slot, event.target.value)}
                required
              >
                <option value="">Choose Status</option>
                {statusOptions.map((option) => (
                  <option value={option.key} key={option.key} disabled={selectedStatuses.includes(option.key) && selectedStatuses[slot] !== option.key}>
                    {option.label}
                  </option>
                ))}
              </select>
            </Field>
          ))}
          <button className="bc-add-status" type="button" onClick={onAddStatus} aria-label="Manage status types"><Plus size={15} /> Manage statuses</button>
        </div>
        <div className="bc-message-fields">
          <Field label="Broadcast title" counter={`${form.broadcast_title.length}/50`} limitReached={form.broadcast_title.length >= 50}>
            <input
              value={form.broadcast_title}
              maxLength={50}
              onChange={(event) => onChange('broadcast_title', event.target.value)}
              required
            />
          </Field>
          <Field label="Official instruction" counter={`${form.message.length}/50`} limitReached={form.message.length >= 50}>
            <input
              value={form.message}
              maxLength={50}
              onChange={(event) => onChange('message', event.target.value)}
              required
            />
          </Field>
        </div>
      </section>

      <div className="bc-form-footer">
        <span>{isUpdateMode ? 'Editing active disaster' : 'Event start time is recorded when posted.'}</span>
        <button className="bc-post-button" type="submit" disabled={isSaving || (isUpdateMode && !hasChanges)}>
          {isSaving ? 'Saving...' : isUpdateMode ? 'Update disaster' : <><Send size={15} /> Post broadcast</>}
        </button>
      </div>
    </form>
  )
}

function StepHeading({ number, title, aside }) {
  return <div className="bc-step-heading"><span className="bc-step-number">{number}</span><h2>{title}</h2>{aside && <span className="bc-step-aside">{aside}</span>}</div>
}

function Field({ label, counter, limitReached, children }) {
  return <label className="bc-field"><span className="bc-field-label">{label}{counter && <small className={limitReached ? 'at-limit' : ''}>{counter}</small>}</span>{children}</label>
}

function targetHouseholds(scope, directPuroks, puroks, householdCount) {
  if (scope !== 'selected_puroks') return Number(householdCount) || puroks.reduce((total, purok) => total + Number(purok.household_count || 0), 0)
  return directPuroks.reduce((total, selected) => total + Number(puroks.find((purok) => purok.name === selected.name)?.household_count || 0), 0)
}

function severityColor(key) {
  return ({ low: '#16a34a', medium: '#d97706', moderate: '#d97706', high: '#ea580c', critical: '#dc2626' })[key] || '#d97706'
}
