import { useEffect, useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import './planning-modal.css'

export default function PlanningModal({ title, isOpen, onClose, children }) {
  const dialog = useRef(null)
  const titleId = useId()
  useEffect(() => {
    if (!isOpen) return
    const element = dialog.current
    const previous = document.activeElement
    const overflow = document.body.style.overflow
    element.showModal()
    document.body.style.overflow = 'hidden'
    return () => {
      element.close()
      document.body.style.overflow = overflow
      previous?.focus()
    }
  }, [isOpen])
  if (!isOpen) return null
  return createPortal(<dialog ref={dialog} className="planning-modal" aria-labelledby={titleId} onCancel={event => { event.preventDefault(); onClose() }} onClick={event => {
    if (event.target !== event.currentTarget) return
    const bounds = event.currentTarget.getBoundingClientRect()
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) onClose()
  }}>
    <div className="planning-modal-head"><h2 id={titleId}>{title}</h2><button type="button" autoFocus onClick={onClose} aria-label="Close dialog"><X size={20} /></button></div>
    <div className="planning-modal-body">{children}</div>
  </dialog>, document.body)
}
