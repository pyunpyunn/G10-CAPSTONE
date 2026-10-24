import { Link } from 'react-router-dom'

export default function PersonnelProfileLink({ id, children }) {
  if (id == null || id === '') return children
  return <Link className="personnel-name-link" to={`/rescuers/view?id=${encodeURIComponent(id)}`} onClick={event => event.stopPropagation()}>{children}</Link>
}
