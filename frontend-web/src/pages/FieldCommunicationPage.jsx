import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import { getArchiveRecords } from '../api/archiveApi'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'

export function FieldCommunicationPanel({ compact = false }) {
  const [page, setPage] = useState(1)
  const loader = useCallback(() => getArchiveRecords('radio-communication-logs', { page, per_page: compact ? 5 : 25 }), [page, compact])
  const { data, error, loading, refresh } = useModuleData(loader)
  return <>
    <ModuleDataView title="Field Communication" rows={data?.records?.data || []} loading={loading} error={error}
      actions={compact ? <Link to="/dispatch/communication">View communication page</Link> : <button className="btn btn-secondary" onClick={refresh}>Refresh</button>}
      columns={[{ key: 'datetime', label: 'Date / time', render: (row) => row.export?.datetime },
        { key: 'team', label: 'Team / responder', render: (row) => row.export?.team_responder },
        { key: 'channel', label: 'Channel', render: (row) => row.export?.channel },
        { key: 'message', label: 'Communication', render: (row) => row.export?.transmission },
        { key: 'status', label: 'Status', render: (row) => row.export?.status }]} />
    {!compact && <PaginationBar meta={data?.records || {}} onPageChange={setPage} label="communications" />}
  </>
}

export default function FieldCommunicationPage() {
  return <main className="ops-page"><PageHeader title="Field Communication" actions={<Link className="btn btn-secondary" to="/dispatch">Back to dashboard</Link>} /><FieldCommunicationPanel /></main>
}
