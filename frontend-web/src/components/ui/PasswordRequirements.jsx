import { Check, Circle } from 'lucide-react'
import { checkPasswordRequirements } from '../../utils/passwordPolicy'

export default function PasswordRequirements({
  password = '',
  confirmation,
  showConfirmation = false,
  className = '',
}) {
  const requirements = checkPasswordRequirements(password)
  const isMatch = Boolean(password && confirmation && password === confirmation)
  const hasConfirmationField = showConfirmation || confirmation !== undefined

  return (
    <div className={`password-requirements-card ${className}`} role="status" aria-label="Password requirements">
      <span className="password-requirements-title">Password requirements:</span>
      <ul className="password-requirements-list">
        {requirements.map((req) => (
          <li
            key={req.id}
            className={`password-requirement-item ${req.met ? 'met' : 'unmet'}`}
          >
            <span className="password-requirement-icon" aria-hidden="true">
              {req.met ? <Check size={13} strokeWidth={2.5} /> : <Circle size={6} fill="currentColor" />}
            </span>
            <span>{req.label}</span>
          </li>
        ))}
        {hasConfirmationField && (
          <li
            key="match"
            className={`password-requirement-item ${isMatch ? 'met' : 'unmet'}`}
          >
            <span className="password-requirement-icon" aria-hidden="true">
              {isMatch ? <Check size={13} strokeWidth={2.5} /> : <Circle size={6} fill="currentColor" />}
            </span>
            <span>Passwords match</span>
          </li>
        )}
      </ul>
    </div>
  )
}

