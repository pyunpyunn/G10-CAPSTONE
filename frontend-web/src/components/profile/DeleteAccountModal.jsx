import { ShieldAlert, X } from 'lucide-react'
import Modal from '../ui/Modal'

export default function DeleteAccountModal({ isOpen, identity = {}, onClose }) {
  return (
    <Modal
      title="Delete account forever"
      isOpen={isOpen}
      onClose={onClose}
      className="profile-modal profile-danger-modal"
      footer={
        <>
          <button className="btn btn-secondary btn-sm" type="button" onClick={onClose}>
            <X size={14} />
            Close
          </button>
          <button className="btn btn-danger btn-sm" type="button" disabled title="Permanent deletion is blocked for audit safety.">
            <ShieldAlert size={14} />
            Delete disabled
          </button>
        </>
      }
    >
      <div className="profile-danger-copy">
        <div className="profile-danger-icon">
          <ShieldAlert size={22} />
        </div>
        <div>
          <strong>Permanent deletion is intentionally blocked for HQ/Admin accounts.</strong>
          <p>
            This account is linked to disaster broadcasts, situation reports, resource validations, and audit logs.
            Removing it forever can break historical records needed for accountability.
          </p>
          <div className="profile-danger-account">
            <span>Account</span>
            <strong>{identity.account_id || identity.user_id || 'Current HQ/Admin account'}</strong>
          </div>
          <p>
            For real deployment, ask the super admin or DB administrator to deactivate or transfer this account after
            all active disaster events are closed.
          </p>
        </div>
      </div>
    </Modal>
  )
}
