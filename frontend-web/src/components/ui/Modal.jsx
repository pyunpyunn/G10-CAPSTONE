import { X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { useEffect, useRef } from 'react'

export default function Modal({ title, isOpen, onClose, children, footer, className = '' }) {
  const panelRef = useRef(null)
  const closeRef = useRef(onClose)
  useEffect(() => { closeRef.current = onClose }, [onClose])
  useEffect(() => {
    if (!isOpen || (!className.startsWith('ro-') && !className.startsWith('reference-'))) return
    const previous = document.activeElement
    const panel = panelRef.current
    panel?.querySelector('button')?.focus()
    const keyboard = (event) => {
      if (event.key === 'Escape') closeRef.current()
      if (event.key !== 'Tab' || !panel) return
      const items = [...panel.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')]
      const first = items[0]
      const last = items.at(-1)
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
    }
    document.addEventListener('keydown', keyboard)
    return () => { document.removeEventListener('keydown', keyboard); previous?.focus() }
  }, [isOpen, className])
  if (!isOpen) {
    return null
  }
  if (className.startsWith('reference-')) return createPortal(
    <div className="ro-reference ro-overlay detail-overlay" onClick={(event) => { if (event.currentTarget === event.target) onClose() }}>
      <div ref={panelRef} className={className === 'reference-communication' ? 'md big cmp' : 'so'} role="dialog" aria-modal="true" aria-label={title}>
        {className !== 'reference-communication' && <div className="mh2"><h3>{title}</h3><span className="sp" /><button className="x" type="button" aria-label="Close dialog" onClick={onClose}><X size={16} /></button></div>}
        {children}{footer}
      </div>
    </div>, document.body)

  return createPortal(
    <div className="modal-backdrop" role="presentation" onClick={(event) => { if (className.startsWith('ro-') && event.target === event.currentTarget) onClose() }}>
      <section ref={panelRef} className={`modal-panel ${className}`.trim()} role="dialog" aria-modal="true" aria-label={title}>
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
