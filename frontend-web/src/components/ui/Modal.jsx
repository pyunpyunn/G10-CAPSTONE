import { X } from 'lucide-react'
import { createPortal } from 'react-dom'

export default function Modal({ title, isOpen, onClose, children, footer, className = '' }) {
  if (!isOpen) {
    return null
  }

  return createPortal(
    <div className="modal-backdrop" role="presentation">
      <section className={`modal-panel ${className}`.trim()} role="dialog" aria-modal="true" aria-label={title}>
        <div className="modal-head">
          <h2>{title}</h2>
          <button className="modal-close" type="button" aria-label="Close modal" onClick={onClose}>
            <X size={18} />
          </button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-footer">{footer}</div>}
      </section>
    </div>,
    document.body,
  )
}
