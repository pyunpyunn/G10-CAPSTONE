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
  const [page, setPage] = useState(1)
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [refreshVersion, setRefreshVersion] = useState(0)
  const validSitioId = /^\d+$/.test(sitioId || '')
  const validPurokId = /^\d+$/.test(purokId || '')
  const validIds = validSitioId && (!purokId || validPurokId)
  const hasPurokFilter = validPurokId

  useEffect(() => {
    if (!validIds) return
    let active = true
    getHouseholds({
      sitio_id: sitioId,
      ...(hasPurokFilter ? { purok_id: purokId } : {}),
      page,
      per_page: 25,
    })
      .then((data) => { if (active) { setPayload(data); setError('') } })
      .catch(() => { if (active) setError('Households could not be loaded. Refresh to try again.') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [sitioId, purokId, page, validIds, hasPurokFilter, refreshVersion])

  return (
    <main className="ops-page household-page household-purok-page">
      <div className="app-page-controls-row household-list-actions">
        <div />
        <div className="pg-actions">
          <button className="button secondary" type="button" onClick={() => navigate('/households')}>Go back to main page</button>
        </div>
      </div>
      {!validIds ? <div className="form-error" role="alert">Choose a sitio or purok from the ranking.</div>
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
