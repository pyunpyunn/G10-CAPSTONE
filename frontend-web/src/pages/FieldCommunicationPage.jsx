import { Link } from 'react-router-dom'
import PageHeader from '../components/ui/PageHeader'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'

export default function FieldCommunicationPage() {
  return <main className="ops-page response-operations-page"><PageHeader title="Field Communication"
    actions={<Link className="btn btn-secondary" to="/dispatch">Back to Dispatch Dashboard</Link>} /><FieldCommunicationPanel /></main>
}
