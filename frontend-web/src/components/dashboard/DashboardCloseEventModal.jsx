import { Archive } from 'lucide-react'
import Modal from '../ui/Modal'

export default function DashboardCloseEventModal({
  activeEvent,
  isOpen,
  isClosingEvent,
  closeError,
  onClose,
  onConfirm,
}) {
  return (
    <Modal
      title="Close Active Event"
      isOpen={isOpen}
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-secondary btn-sm" type="button" disabled={isClosingEvent} onClick={onClose}>
            Cancel
          </button>
          <button className="btn btn-warning btn-sm" type="button" disabled={isClosingEvent} onClick={onConfirm}>
            <Archive size={14} />
            {isClosingEvent ? 'Closing...' : 'Close and Log Event'}
          </button>
        </>
      }
    >
      <div className="close-event-copy">
        <p>
          This will record the end time for <strong>{activeEvent?.name}</strong> and save a summary to the Disaster Event Log.
        </p>
        <p>
          Use this only when HQ/Admin has finished monitoring and reporting for the active event.
        </p>
        {closeError && <div className="form-error">{closeError}</div>}
      </div>
    </Modal>
  )
}
