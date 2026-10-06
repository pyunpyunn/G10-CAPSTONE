import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { getHouseholds } from '../api/householdApi'
import HouseholdTable from '../components/households/HouseholdTable'
import LoadingState from '../components/ui/LoadingState'

export default function HouseholdPurokListPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const sitioId = searchParams.get('sitio_id')
  const purokId = searchParams.get('purok_id')
  const sitioName = searchParams.get('sitio') || 'Selected sitio'
  const purokName = searchParams.get('purok') || 'Selected purok'
  const [page, setPage] = useState(1)
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [refreshVersion, setRefreshVersion] = useState(0)
  const validIds = /^\d+$/.test(sitioId || '') && /^\d+$/.test(purokId || '')

  useEffect(() => {
    if (!validIds) return
    let active = true
    getHouseholds({ sitio_id: sitioId, purok_id: purokId, page, per_page: 25 })
      .then((data) => { if (active) { setPayload(data); setError('') } })
      .catch(() => { if (active) setError('Households could not be loaded. Refresh to try again.') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [sitioId, purokId, page, validIds, refreshVersion])

  return (
    <main className="ops-page household-page household-purok-page">
      <div className="household-list-heading">
        <div><h1>{purokName} household list</h1><p>{sitioName}</p></div>
        <button className="button secondary" type="button" onClick={() => navigate('/households')}>Go back to main page</button>
      </div>
      {!validIds ? <div className="form-error" role="alert">Choose a purok from the sitio ranking.</div>
        : loading && !payload ? <LoadingState />
          : <>
            {error && <div className="form-error" role="alert">{error}</div>}
            {payload && <HouseholdTable
              households={payload.households?.data || []}
              meta={payload.households?.meta || {}}
              selectedPurok="all"
              onOpen={(householdId) => navigate(`/households/${householdId}`)}
              onPageChange={(nextPage) => { setLoading(true); setPage(nextPage) }}
              onDispatchPurok={() => navigate('/dispatch')}
              actions={<button className="button secondary" type="button" onClick={() => { setLoading(true); setRefreshVersion((version) => version + 1) }}>Refresh</button>}
            />}
          </>}
    </main>
  )
}
