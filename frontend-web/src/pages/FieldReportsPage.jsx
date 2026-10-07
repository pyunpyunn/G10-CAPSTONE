import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'

export default function FieldReportsPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('all')
  const loader = useCallback(async () => (await api.get('/field-reports', { params: { status } })).data.data, [status])
  const { data, error, loading, refresh } = useModuleData(loader)
  return <main className="ops-page">
    <PageHeader title="Field Reports" actions={<button className="btn btn-secondary" onClick={refresh}>Refresh</button>} />
    <div className="dp-side-head"><label>Search households <input value={search} onChange={(event) => setSearch(event.target.value)} /></label>
      <label>Status <select value={status} onChange={(event) => setStatus(event.target.value)}><option value="all">All statuses</option>
        {(data?.status_options || []).map((item) => <option key={item.status_id || item.key} value={item.key || item.status_key}>{item.label || item.status_label}</option>)}
      </select></label></div>
    <ModuleDataView title="Field reports" rows={(data?.reports?.data || data?.reports || []).filter((row) => `${row.household_head_name} ${row.household_code} ${row.notes}`.toLowerCase().includes(search.toLowerCase()))} loading={loading} error={error}
      columns={[{ key: 'submitted_at', label: 'Submitted' }, { key: 'household', label: 'Household', render: (row) => <Link to={`/households/${row.household_id}`}>{row.household_head_name || row.household_code}</Link> },
        { key: 'status_label', label: 'Status' }, { key: 'notes', label: 'Report' }, { key: 'location', label: 'Geotag', render: (row) => row.latitude != null && row.longitude != null ? `${row.latitude}, ${row.longitude}` : 'No geotag' }]} />
  </main>
}
