import { useCallback, useState } from 'react'
import api from '../api/client'
import PaginationBar from '../components/ui/PaginationBar'
import PageHeader from '../components/ui/PageHeader'
import ModuleDataView from '../components/ui/ModuleDataView'
import { useModuleData } from '../utils/useModuleData'

export default function RequestTypesPage() {
  const [page, setPage] = useState(1)
  const loader = useCallback(async () => (await api.get('/resource-request-types', { params: { page, per_page: 20 } })).data.data, [page])
  const { data, error, loading, refresh } = useModuleData(loader)
  return <main className="ops-page resources-page">
    <PageHeader title="Request Types" actions={<button className="btn btn-secondary" onClick={refresh}>Refresh</button>} />
    <ModuleDataView title="List of request types" rows={data?.types || []} loading={loading} error={error}
      columns={[{ key: 'key', label: 'Type code' }, { key: 'label', label: 'Request type' }]} />
    <ModuleDataView title="Request type inventory with status" rows={data?.inventory?.data || []} loading={loading} error={error || data?.inventory_error}
      columns={[{ key: 'label', label: 'Resource' }, { key: 'type', label: 'Type' }, { key: 'sku', label: 'SKU' }, { key: 'quantity', label: 'Available quantity' },
        { key: 'status', label: 'Status' }, { key: 'detail', label: 'Storage / details' }]} />
    <PaginationBar meta={data?.inventory || {}} onPageChange={setPage} label="inventory items" />
  </main>
}
