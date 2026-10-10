import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import { getFieldReports } from '../api/dispatchApi'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import DataFilterBar from '../components/ui/DataFilterBar'
import PaginationBar from '../components/ui/PaginationBar'
import Modal from '../components/ui/Modal'

export default function FieldReportsPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState(null)
  const loader = useCallback(() => getFieldReports({ search, status, page, per_page: 20 }), [search, status, page])
  const { data, error, loading, refresh } = useModuleData(loader)
  return <main className="ops-page response-operations-page">
    <PageHeader title="Field Reports" subtitle={data?.active_event?.name || 'Saved field reports'} actions={<button className="btn btn-secondary" type="button" onClick={refresh}>Refresh</button>} />
    <DataFilterBar search={search} onSearchChange={(value) => { setSearch(value); setPage(1) }} searchPlaceholder="Search households or report notes..."
      filters={[{ id: 'status', label: 'Status', value: status, onChange: (value) => { setStatus(value); setPage(1) }, options: [{ value: 'all', label: 'All statuses' }, ...(data?.status_options || []).map((item) => ({ value: item.key, label: item.label }))] }]}
      onReset={() => { setSearch(''); setStatus('all'); setPage(1) }} />
    <ModuleDataView title="Field Reports" rows={data?.reports || []} loading={loading} error={error}
      columns={[{ key: 'submitted_at', label: 'Submitted' }, { key: 'household', label: 'Household', render: (row) => <Link to={`/households/${row.household_id}`}>{row.household_head_name || row.household_code || row.household_id}</Link> },
        { key: 'status_label', label: 'Status' }, { key: 'notes', label: 'Report' },
        { key: 'location', label: 'Geotag', render: (row) => row.latitude != null && row.longitude != null ? `${row.latitude}, ${row.longitude}` : 'No geotag' },
        { key: 'details', label: 'Details', render: (row) => <button type="button" className="btn btn-secondary" onClick={() => setSelected(row)}>View Report</button> }]} />
    <PaginationBar meta={data?.reports_meta || {}} onPageChange={setPage} label="field reports" />
    <Modal title="Field Report Details" isOpen={Boolean(selected)} onClose={() => setSelected(null)}>
      {selected && <div className="response-report-details"><p><strong>Household:</strong> {selected.household_head_name} ({selected.household_id})</p>
        <p><strong>Status:</strong> {selected.status_label}</p><p><strong>Submitted:</strong> {selected.submitted_at}</p>
        <p><strong>Battery:</strong> {selected.battery_level != null ? `${selected.battery_level}%` : 'Not reported'}</p>
        <p><strong>Location:</strong> {selected.latitude != null && selected.longitude != null ? `${selected.latitude}, ${selected.longitude}` : 'No geotag'}</p>
        <p className="response-report-notes">{selected.notes || 'No report notes.'}</p>
        <Link className="btn btn-secondary" to={`/households/${selected.household_id}`}>View Household Account</Link></div>}
    </Modal>
  </main>
}
