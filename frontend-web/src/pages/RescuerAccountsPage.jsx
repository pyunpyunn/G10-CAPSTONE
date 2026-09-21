import { RefreshCcw, Settings2, UserPlus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  createRescueTeam,
  createRescuer,
  deleteRescueTeam,
  deactivateRescuer,
  getRescueTeamConfig,
  getRescuer,
  getRescuers,
  updateRescueTeam,
  updateRescuer,
} from '../api/rescuerApi'
import RescuerAccountModal from '../components/rescuers/RescuerAccountModal'
import RescuerFilters from '../components/rescuers/RescuerFilters'
import RescuerRosterTable from '../components/rescuers/RescuerRosterTable'
import RescuerTeamGrid from '../components/rescuers/RescuerTeamGrid'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  buildRescuerPayload,
  emptyRescuerForm,
  accountIdForTeam,
  firstTeam,
  formFromRescuer,
  rescuerErrorMessage,
} from '../utils/rescuerHelpers'

export default function RescuerAccountsPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const workflow = location.pathname !== '/rescuers'
  const workflowType = location.pathname.split('/').pop()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [activeChip, setActiveChip] = useState('all')
  const [page, setPage] = useState(1)
  const [modalMode, setModalMode] = useState('create')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [form, setForm] = useState(emptyRescuerForm())
  const [selectedRescuerId, setSelectedRescuerId] = useState(null)
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [teamConfigOpen, setTeamConfigOpen] = useState(false)
  const [teamConfig, setTeamConfig] = useState(null)
  const [teamConfigVersion, setTeamConfigVersion] = useState(0)
  const [teamConfigLoading, setTeamConfigLoading] = useState(false)
  const [teamConfigSaving, setTeamConfigSaving] = useState(false)
  const [teamConfigError, setTeamConfigError] = useState('')

  useEffect(() => {
    if (workflowType === 'new') {
      const defaultTeam = firstTeam(teamOptions)
      setModalMode('create')
      setSelectedRescuerId(null)
      setForm(emptyRescuerForm(accountIdForTeam(accountIdOptions, defaultTeam?.team_name, payload?.next_account_id || ''), defaultTeam))
      setIsModalOpen(true)
    }
  }, [workflowType, workflow, payload])

  useEffect(() => {
    if (!payload || (workflowType !== 'view' && workflowType !== 'edit') || !location.state?.rescuer) {
      return
    }

    openExistingModal(location.state.rescuer, workflowType)
  }, [payload, workflowType, location.state])

  useEffect(() => {
    if (workflowType === 'teams') {
      loadTeamConfig()
      setTeamConfigOpen(true)
    }
  }, [workflowType])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getRescuers(filterParams(search, purok, activeChip, page))

        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Rescuer accounts cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadPage()

    return () => {
      ignore = true
    }
  }, [search, purok, activeChip, page])

  const rescuers = payload?.rescuers?.data || []
  const pagination = payload?.rescuers || {}
  const teams = payload?.teams || []
  const teamOptions = payload?.team_options || []
  const accountIdOptions = payload?.account_id_options || []
  const filters = payload?.filters || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

  async function loadRescuers() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getRescuers(filterParams(search, purok, activeChip, page))
      setPayload(data)
    } catch {
      setError('Rescuer accounts cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function openCreateModal() {
    navigate('/rescuers/new')
  }

  function openTeamConfig() {
    navigate('/rescuers/teams')
  }

  async function loadTeamConfig() {
    setTeamConfigLoading(true)
    setTeamConfigError('')

    try {
      const data = await getRescueTeamConfig()
      setTeamConfig(data)
      setTeamConfigVersion((current) => current + 1)
    } catch (loadError) {
      setTeamConfigError(rescuerErrorMessage(loadError, 'Team configuration cannot be loaded right now.'))
    } finally {
      setTeamConfigLoading(false)
    }
  }

  async function openViewModal(rescuer) {
    navigate('/rescuers/view', { state: { rescuer } })
  }

  async function openEditModal(rescuer) {
    navigate('/rescuers/edit', { state: { rescuer } })
  }

  async function openExistingModal(rescuer, mode) {
    setFormError('')

    try {
      const data = await getRescuer(rescuer.responder_id)
      setModalMode(mode)
      setSelectedRescuerId(data.rescuer.responder_id)
      setForm(formFromRescuer(data.rescuer))
      setIsModalOpen(true)
    } catch {
      setError('Selected rescuer account cannot be loaded right now.')
    }
  }

  function closeModal() {
    if (isSaving) {
      return
    }

    setIsModalOpen(false)
    setFormError('')
    setSelectedRescuerId(null)
  }

  function resetForm() {
    if (modalMode === 'create') {
      const defaultTeam = firstTeam(teamOptions)
      setForm(emptyRescuerForm(accountIdForTeam(accountIdOptions, defaultTeam?.team_name, payload?.next_account_id || ''), defaultTeam))
    }
  }

  async function submitForm(event) {
    event.preventDefault()
    setFormError('')
    setIsSaving(true)

    try {
      const body = buildRescuerPayload(form, modalMode)

      if (modalMode === 'edit') {
        if (!selectedRescuerId) {
          setFormError('Selected rescuer account cannot be updated. Please reopen the record.')
          return
        }

        await updateRescuer(selectedRescuerId, body)
      } else {
        await createRescuer(body)
      }

      setIsModalOpen(false)
      await loadRescuers()
    } catch (saveError) {
      setFormError(rescuerErrorMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDeactivate(rescuer) {
    const confirmed = window.confirm(`Deactivate ${rescuer.full_name}? This disables the rescuer login but keeps the roster record.`)

    if (!confirmed) {
      return
    }

    setError('')

    try {
      await deactivateRescuer(rescuer.responder_id)
      await loadRescuers()
    } catch (deactivateError) {
      setError(rescuerErrorMessage(deactivateError, 'Unable to deactivate rescuer account.'))
    }
  }

  async function saveTeamConfig(body) {
    setTeamConfigSaving(true)
    setTeamConfigError('')

    try {
      const data = body.team_id
        ? await updateRescueTeam(body.team_id, body)
        : await createRescueTeam(body)

      setTeamConfig(data)
      setTeamConfigVersion((current) => current + 1)
      await loadRescuers()
    } catch (saveError) {
      setTeamConfigError(rescuerErrorMessage(saveError, 'Unable to save rescue team.'))
    } finally {
      setTeamConfigSaving(false)
    }
  }

  async function removeTeamConfig(teamId, teamName) {
    const confirmed = window.confirm(`Delete ${teamName}? Members will move to Unassigned. Rescuer accounts will not be deleted.`)

    if (!confirmed) {
      return
    }

    setTeamConfigSaving(true)
    setTeamConfigError('')

    try {
      const data = await deleteRescueTeam(teamId)
      setTeamConfig(data)
      setTeamConfigVersion((current) => current + 1)
      await loadRescuers()
    } catch (deleteError) {
      setTeamConfigError(rescuerErrorMessage(deleteError, 'Unable to delete rescue team.'))
    } finally {
      setTeamConfigSaving(false)
    }
  }

  return (
    <main className="ops-page rescuer-page">
      <header className="household-status-page-header">
        <div className="household-status-header-copy">
          <h1>{workflow ? (workflowType === 'teams' ? 'Configure rescue teams' : modalMode === 'view' ? 'View rescuer account' : modalMode === 'edit' ? 'Update rescuer account' : 'New rescuer account') : 'Rescuer Accounts'}</h1>
          <p>Barangay Mambaling, Cebu City</p>
        </div>
        <div className="weather-page-actions household-status-page-actions">
          <div className="weather-live-status"><strong>{workflow ? 'Roster' : 'Live'}</strong><span>{workflow ? 'Verified responder administration' : 'Verified roster management'}</span></div>
          {workflow ? <button className="button secondary" type="button" onClick={() => navigate('/rescuers')}><RefreshCcw size={16} />Back to roster</button> : (
            <>
              <button className="button secondary" type="button" onClick={loadRescuers}><RefreshCcw size={16} />Refresh</button>
              <button className="button secondary" type="button" onClick={openTeamConfig}><Settings2 size={16} />Configure rescue teams</button>
              <button className="button review" type="button" onClick={openCreateModal}><UserPlus size={16} />Create verified account</button>
            </>
          )}
        </div>
      </header>

      {workflow && workflowType === 'teams' && <RescueTeamConfigModal embedded isOpen workspace={teamConfig} isLoading={teamConfigLoading} isSaving={teamConfigSaving} error={teamConfigError} onRetry={loadTeamConfig} onClose={() => navigate('/rescuers')} onSave={saveTeamConfig} onDelete={removeTeamConfig} />}
      {workflow && workflowType !== 'teams' && <RescuerAccountModal embedded mode={modalMode} isOpen form={form} setForm={setForm} formError={formError} isSaving={isSaving} teamOptions={teamOptions} accountIdOptions={accountIdOptions} fallbackAccountId={payload?.next_account_id || ''} roles={filters.roles || ['Responder']} bloodTypes={filters.blood_types || ['Unknown']} onClose={() => navigate('/rescuers')} onReset={resetForm} onSubmit={submitForm} />}

      {!workflow && <>
          {isInitialLoading && <LoadingState />}
          {error && <div className="form-error">{error}</div>}
          {!isInitialLoading && !hasBlockingError && payload && (
            <div className="ra-workspace">
              <div className="ra-main-panel">
                <div className="ra-panel roster-filter-panel"><RescuerFilters search={search} onSearchChange={(value) => { setSearch(value); setPage(1) }} purok={purok} onPurokChange={(value) => { setPurok(value); setPage(1) }} puroks={filters.puroks || []} teamOptions={teamOptions} activeChip={activeChip} onChipChange={(value) => { setActiveChip(value); setPage(1) }} /><RefreshOverlay active={isRefreshing}><RescuerRosterTable rescuers={rescuers} pagination={pagination} onPageChange={setPage} onView={openViewModal} onEdit={openEditModal} onDeactivate={handleDeactivate} /></RefreshOverlay></div>
              </div>
              <aside className="ra-side-panel"><div className="ra-side-head"><span className="ra-title">Team cards</span></div><RescuerTeamGrid teams={teams} /></aside>
            </div>
          )}
        </>}

    </main>
  )
}

function filterParams(search, purok, activeChip, page) {
  const params = {
    search: search.trim(),
    purok,
    page,
    per_page: 10,
  }

  if (activeChip.startsWith('team:')) {
    params.team = activeChip.replace('team:', '')
  } else {
    params.duty_status = activeChip
  }

  return params
}
