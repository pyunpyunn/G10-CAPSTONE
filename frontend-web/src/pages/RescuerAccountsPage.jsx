import { ArrowLeft, Settings2, UserPlus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  createRescueTeam,
  createRescuer,
  deleteRescueTeam,
  deactivateRescuer,
  deleteRescuer,
  getRescueTeamConfig,
  getRescuer,
  getRescuers,
  updateRescueTeam,
  updateRescuer,
} from '../api/rescuerApi'
import RescuerAccountModal from '../components/rescuers/RescuerAccountModal'
import HeadquartersAccountsPanel from '../components/rescuers/HeadquartersAccountsPanel'
import RescuerRosterTable from '../components/rescuers/RescuerRosterTable'
import RescuerTeamGrid from '../components/rescuers/RescuerTeamGrid'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import PageHeader from '../components/ui/PageHeader'
import {
  buildRescuerPayload,
  emptyRescuerForm,
  formFromRescuer,
  rescuerErrorMessage,
} from '../utils/rescuerHelpers'

export default function RescuerAccountsPage() {
  const location = useLocation()
  return <RescuerAccountsWorkspace key={`${location.pathname}${location.search}`} />
}

function RescuerAccountsWorkspace() {
  const location = useLocation()
  const navigate = useNavigate()
  const workflow = location.pathname !== '/rescuers'
  const workflowType = location.pathname.split('/').pop()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [modalMode, setModalMode] = useState(workflowType === 'new' ? 'create' : workflowType)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [originalForm, setOriginalForm] = useState(null)
  const notice = location.state?.notice || ''
  const [form, setForm] = useState(emptyRescuerForm())
  const [selectedRescuerId, setSelectedRescuerId] = useState(null)
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [teamConfig, setTeamConfig] = useState(null)
  const [teamConfigVersion, setTeamConfigVersion] = useState(0)
  const [teamConfigLoading, setTeamConfigLoading] = useState(false)
  const [teamConfigSaving, setTeamConfigSaving] = useState(false)
  const [teamConfigError, setTeamConfigError] = useState('')
  const [activeAccountTab, setActiveAccountTab] = useState('responders')

  useEffect(() => {
    if (workflowType !== 'view' && workflowType !== 'edit') return
    let ignore = false
    async function loadDetail() {
    const id = new URLSearchParams(location.search).get('id') || location.state?.rescuer?.responder_id
    if (!id) {
      setError('Select a rescuer from the roster to open their profile.')
      return
    }
    setIsModalOpen(false)
    setDetailLoading(true)
    setFormError('')
    getRescuer(id).then((data) => {
      if (ignore) return
      const nextForm = formFromRescuer(data.rescuer)
      setModalMode(workflowType)
      setSelectedRescuerId(data.rescuer.responder_id)
      setForm(nextForm)
      setOriginalForm(nextForm)
      setIsModalOpen(true)
    }).catch((loadError) => {
      if (!ignore) setError(rescuerErrorMessage(loadError, 'Selected rescuer account cannot be loaded.'))
    }).finally(() => { if (!ignore) setDetailLoading(false) })
    }
    loadDetail()
    return () => { ignore = true }
  }, [workflowType, location.search, location.state])

  useEffect(() => {
    if (workflowType === 'teams') loadTeamConfig()
  }, [workflowType])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getRescuers({ page })

        if (!ignore) {
          setPayload(data)
          if (workflowType === 'new') {
            setForm(emptyRescuerForm(data.form_options?.defaults))
            setIsModalOpen(true)
          }
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
  }, [page, workflowType])

  const rescuers = payload?.rescuers?.data || []
  const pagination = payload?.rescuers || {}
  const teams = payload?.teams || []
  const teamOptions = payload?.team_options || []
  const accountIdOptions = payload?.account_id_options || []
  const formOptions = payload?.form_options
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

  async function loadRescuers() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getRescuers({ page })
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
    navigate(`/rescuers/view?id=${rescuer.responder_id}`)
  }

  async function openEditModal(rescuer) {
    navigate(`/rescuers/edit?id=${rescuer.responder_id}`)
  }

  function resetForm() {
    if (modalMode === 'edit' && originalForm) setForm({ ...originalForm })
    if (modalMode === 'create') {
      setForm(emptyRescuerForm(formOptions?.defaults))
    }
  }

  async function submitForm(event) {
    event.preventDefault()
    setFormError('')
    setIsSaving(true)

    try {
      const body = buildRescuerPayload(form, modalMode)

      let result
      if (modalMode === 'edit') {
        if (!selectedRescuerId) {
          setFormError('Selected rescuer account cannot be updated. Please reopen the record.')
          return
        }

        result = await updateRescuer(selectedRescuerId, body)
      } else {
        result = await createRescuer(body)
      }

      navigate('/rescuers', { state: { notice: result.message } })
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

  async function handleDelete(rescuer) {
    if (!window.confirm(`Delete ${rescuer.full_name} from the roster? This disables login and retains dispatch history.`)) return
    setError('')
    try {
      await deleteRescuer(rescuer.responder_id)
      await loadRescuers()
    } catch (deleteError) {
      setError(rescuerErrorMessage(deleteError, 'Unable to delete rescuer account.'))
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
      <PageHeader
        title={workflow ? (workflowType === 'teams' ? 'Team Management' : workflowType === 'view' ? 'Rescuer profile' : workflowType === 'edit' ? 'Update profile' : 'New rescuer account') : 'Account Management'}
        subtitle={payload?.area_label || 'Shared database records'}
        actions={workflow ? (
          <button className="button secondary" type="button" onClick={() => navigate('/rescuers')}><ArrowLeft size={15} />Back to roster</button>
        ) : activeAccountTab === 'responders' ? (
          <>
            <button className="button secondary" type="button" onClick={openTeamConfig}><Settings2 size={15} />Manage teams</button>
            <button className="button review" type="button" onClick={openCreateModal}><UserPlus size={15} />Create account</button>
          </>
        ) : null}
      />

      {error && <div className="form-error" role="alert">{error}</div>}
      {notice && !workflow && <div className="ra-success" role="status">{notice}</div>}
      {workflow && workflowType === 'teams' && <section className="ra-team-status-panel"><div className="ra-side-head"><span className="ra-title">Team Status Cards</span></div><RescuerTeamGrid teams={teamConfig?.teams || teams} showStatus /></section>}
      {workflow && workflowType === 'teams' && <RescueTeamConfigModal key={teamConfigVersion} embedded isOpen workspace={teamConfig} isLoading={teamConfigLoading} isSaving={teamConfigSaving} error={teamConfigError} onRetry={loadTeamConfig} onClose={() => navigate('/rescuers')} onSave={saveTeamConfig} onDelete={removeTeamConfig} />}
      {workflow && workflowType !== 'teams' && !isInitialLoading && !detailLoading && isModalOpen && (modalMode === 'view' || formOptions) && <RescuerAccountModal embedded mode={modalMode} isOpen form={form} setForm={setForm} formError={formError} isSaving={isSaving} teamOptions={teamOptions} accountIdOptions={accountIdOptions} fallbackAccountId={payload?.next_account_id || ''} formOptions={formOptions} onClose={() => navigate('/rescuers')} onReset={resetForm} onSubmit={submitForm} onEdit={() => navigate(`/rescuers/edit?id=${selectedRescuerId}`)} />}

      {workflow && workflowType !== 'teams' && (isInitialLoading || detailLoading) && <LoadingState label="Loading rescuer profile..." />}
      {!workflow && <>
          <div className="account-management-tabs" role="tablist" aria-label="Account categories">
            <button type="button" role="tab" aria-selected={activeAccountTab === 'responders'} className={activeAccountTab === 'responders' ? 'active' : ''} onClick={() => setActiveAccountTab('responders')}>Rescuers & teams</button>
            <button type="button" role="tab" aria-selected={activeAccountTab === 'headquarters'} className={activeAccountTab === 'headquarters' ? 'active' : ''} onClick={() => setActiveAccountTab('headquarters')}>Headquarters accounts</button>
          </div>
          {activeAccountTab === 'headquarters' && <HeadquartersAccountsPanel />}
          {activeAccountTab === 'responders' && <>
          {isInitialLoading && <LoadingState />}
          {!isInitialLoading && !hasBlockingError && payload && (
            <div className="ra-workspace">
              <div className="ra-main-panel">
                <div className="ra-roster-region"><RefreshOverlay active={isRefreshing}><RescuerRosterTable rescuers={rescuers} pagination={pagination} onPageChange={setPage} onView={openViewModal} onEdit={openEditModal} onDeactivate={handleDeactivate} onDelete={handleDelete} /></RefreshOverlay></div>
              </div>
              <aside className="ra-side-panel"><div className="ra-side-head"><span className="ra-title">Team cards</span></div><RescuerTeamGrid teams={teams} showStatus /><button className="button secondary" type="button" onClick={openTeamConfig}>View all teams</button></aside>
            </div>
          )}
          </>}
        </>}

    </main>
  )
}
