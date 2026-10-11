import { useCallback, useState } from 'react'
import api from '../api/client'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'

export default function InquiryLogsView() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const loader = useCallback(async () => (await api.get('/archive/inquiry-logs', { params: { page, per_page: 20, search } })).data.data, [page, search])
  const { data, loading, error, refresh } = useModuleData(loader)
  return <div className="archive-workspace-main"><PageHeader title="Inquiry Logs" actions={<button className="btn btn-secondary" onClick={refresh}>Refresh</button>} />
    <label>Search inquiries <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} /></label>
    <ModuleDataView title="Inquiry logs" rows={data?.records?.data || []} loading={loading} error={error}
      columns={[{ key: 'created_at', label: 'Date' }, { key: 'name', label: 'Name' }, { key: 'organization', label: 'Organization' }, { key: 'email', label: 'Email' }, { key: 'message', label: 'Inquiry' }, { key: 'status', label: 'Status' }]} />
    <PaginationBar meta={data?.records || {}} onPageChange={setPage} label="inquiries" /></div>
}
