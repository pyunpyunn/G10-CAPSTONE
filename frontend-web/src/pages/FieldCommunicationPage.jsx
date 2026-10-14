import { useNavigate } from 'react-router-dom'
import RescueDispatchPage from './RescueDispatchPage'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'
import Modal from '../components/ui/Modal'

export default function FieldCommunicationPage() {
  const navigate = useNavigate()
  return <><RescueDispatchPage /><Modal title="Field Communication" className="reference-communication" isOpen onClose={() => navigate('/dispatch')}><FieldCommunicationPanel recordingsOnly onClose={() => navigate('/dispatch')} /></Modal></>
}
