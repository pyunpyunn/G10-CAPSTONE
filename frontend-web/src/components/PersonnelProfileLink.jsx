import { Link } from 'react-router-dom'
import { personnelDestination } from '../utils/personnelNavigation'

export default function PersonnelProfileLink({ id, children }) {
  if (id == null || id === '') return children
  return <Link className="personnel-name-link" {...personnelDestination(id)} onClick={event => event.stopPropagation()}>{children}</Link>
}
