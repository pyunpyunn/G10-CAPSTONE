import { MoreVertical } from 'lucide-react'
import { useRef, useState } from 'react'
import { createPortal } from 'react-dom'

export default function ActionMenu({ label = 'Actions', actions = [], buttonClassName = 'icon-button', icon = null }) {
  const [isOpen, setIsOpen] = useState(false)
  const [position, setPosition] = useState({ top: 0, left: 0 })
  const buttonRef = useRef(null)

  function toggleMenu() {
    if (!isOpen && buttonRef.current) {
      const rect = buttonRef.current.getBoundingClientRect()
      const menuWidth = 168
      const menuHeight = Math.max(126, actions.length * 42 + 16)
      const left = Math.min(Math.max(12, rect.right - menuWidth), window.innerWidth - menuWidth - 12)
      const hasRoomBelow = window.innerHeight - rect.bottom > menuHeight + 12
      const top = hasRoomBelow ? rect.bottom + 6 : Math.max(12, rect.top - menuHeight - 6)

      setPosition({ top, left })
    }

    setIsOpen(!isOpen)
  }

  return (
    <div className="action-menu">
      <button ref={buttonRef} className={buttonClassName} type="button" aria-label={label} title={label} onClick={toggleMenu}>
        {icon || <MoreVertical size={16} />}
      </button>
      {isOpen && createPortal(
        <div className="action-menu-list action-menu-floating" style={{ top: position.top, left: position.left }}>
          {actions.map((action) => {
            const Icon = action.icon

            return (
            <button
              type="button"
              key={action.label}
              className={action.danger ? 'is-danger' : ''}
              disabled={action.disabled}
              onClick={() => {
                if (action.disabled) {
                  return
                }

                setIsOpen(false)
                action.onClick()
              }}
            >
              {Icon && <Icon size={14} />}
              {action.label}
            </button>
            )
          })}
        </div>,
        document.body,
      )}
    </div>
  )
}
