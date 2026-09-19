import { useEffect, useState } from 'react'
import { Save } from 'lucide-react'
import Modal from '../ui/Modal'

export default function UpdateActiveEventModal({
  activeEvent,
  disasterTypes = [],
  severityLevels = [],
  isOpen,
  isUpdating,
  updateError,
  onClose,
  onConfirm,
}) {
  const [form, setForm] = useState({
    name: '',
    type_id: '',
    severity_level_id: '',
  })

  useEffect(() => {
    if (activeEvent) {
      setForm({
        name: activeEvent.name || '',
        type_id: activeEvent.type_id || (disasterTypes[0]?.type_id || ''),
        severity_level_id: activeEvent.severity_level_id || (severityLevels[0]?.severity_id || ''),
      })
    }
  }, [activeEvent, disasterTypes, severityLevels])

  function handleSubmit(e) {
    e.preventDefault()
    onConfirm(form)
  }

  return (
    <Modal
      title="Update Active Event"
      isOpen={isOpen}
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-secondary btn-sm" type="button" disabled={isUpdating} onClick={onClose}>
            Cancel
          </button>
          <button className="btn btn-primary btn-sm" type="button" disabled={isUpdating} onClick={handleSubmit}>
            <Save size={14} />
            {isUpdating ? 'Updating...' : 'Save Changes'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="update-event-form" style={{ display: 'grid', gap: '1rem' }}>
        <label>
          <span style={{ fontSize: '0.85rem', fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>Event Name</span>
          <input
            type="text"
            className="input"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
        </label>

        <label>
          <span style={{ fontSize: '0.85rem', fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>Disaster Type</span>
          <select
            className="select"
            value={form.type_id}
            onChange={(e) => setForm({ ...form, type_id: e.target.value })}
            required
          >
            {disasterTypes.map((type) => (
              <option value={type.type_id} key={type.type_id}>{type.type_name}</option>
            ))}
          </select>
        </label>

        <label>
          <span style={{ fontSize: '0.85rem', fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>Severity Level</span>
          <select
            className="select"
            value={form.severity_level_id}
            onChange={(e) => setForm({ ...form, severity_level_id: e.target.value })}
            required
          >
            {severityLevels.map((sev) => (
              <option value={sev.severity_id} key={sev.severity_id}>{sev.severity_label}</option>
            ))}
          </select>
        </label>

        {updateError && <div className="form-error">{updateError}</div>}
      </form>
    </Modal>
  )
}
