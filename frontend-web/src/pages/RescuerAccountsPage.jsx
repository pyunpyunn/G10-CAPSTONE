import { FileCheck2, Settings2, UserPlus } from 'lucide-react'
import { useEffect, useState } from 'react'
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
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  buildRescuerPayload,
  emptyRescuerForm,
  exportRosterRows,
  accountIdForTeam,
  firstTeam,
  formFromRescuer,
  rescuerErrorMessage,
} from '../utils/rescuerHelpers'
import {
  downloadExcelWorkbook,
  downloadPdfReport,
} from '../utils/exportFileHelpers'

export default function RescuerAccountsPage() {
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [activeChip, setActiveChip] = useState('all')
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
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getRescuers(filterParams(search, purok, activeChip))

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
  }, [search, purok, activeChip])

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
      const data = await getRescuers(filterParams(search, purok, activeChip))
      setPayload(data)
    } catch {
      setError('Rescuer accounts cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function openCreateModal() {
    const defaultTeam = firstTeam(teamOptions)

    setModalMode('create')
    setSelectedRescuerId(null)
    setForm(emptyRescuerForm(accountIdForTeam(accountIdOptions, defaultTeam?.team_name, payload?.next_account_id || ''), defaultTeam))
    setFormError('')
    setIsModalOpen(true)
  }

  async function openTeamConfig() {
    await loadTeamConfig()
    setTeamConfigOpen(true)
  }

  async function loadTeamConfig() {
    setTeamConfigLoading(true)
    setTeamConfigError('')

    try {
      const data = await getRescueTeamConfig()
      setTeamConfig(data)
      setTeamConfigVersion((current) => current + 1)
    } catch {
      setTeamConfigError('Team configuration cannot be loaded right now.')
    } finally {
      setTeamConfigLoading(false)
    }
  }

  async function openViewModal(rescuer) {
    await openExistingModal(rescuer, 'view')
  }

  async function openEditModal(rescuer) {
    await openExistingModal(rescuer, 'edit')
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

  function exportRoster(type) {
    const rows = exportRosterRows(rescuers)

    if (type === 'pdf') {
      downloadPdfReport('resqperation-rescuer-roster.pdf', 'ResQperation Rescuer Roster', rows)
      return
    }

    downloadExcelWorkbook('resqperation-rescuer-roster.xls', 'ResQperation Rescuer Roster', rows)
  }

  return (
    <section className="page active rescuer-page">
      <PageHeader
        title="Rescuer Accounts"
        actions={
          <>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => exportRoster('excel')}>
            <FileCheck2 size={14} />
            Export Excel
          </button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => exportRoster('pdf')}>
            <FileCheck2 size={14} />
            Export PDF
          </button>
          <button className="btn btn-primary btn-sm" type="button" onClick={openTeamConfig}>
            <Settings2 size={14} />
            Configure rescue teams
          </button>
          <button className="btn btn-primary btn-sm" type="button" onClick={openCreateModal}>
            <UserPlus size={14} />
            Create verified account
          </button>
          </>
        }
      />

<<<<<<< HEAD
      {isLoading && <LoadingState />}
=======
      {isInitialLoading && <LoadingState />}
>>>>>>> 4748515fd9da7c3d41af7e11c0951e50f424cd0c
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <>
          <RescuerFilters
            search={search}
            onSearchChange={setSearch}
            purok={purok}
            onPurokChange={setPurok}
            puroks={filters.puroks || []}
            teamOptions={teamOptions}
            activeChip={activeChip}
            onChipChange={setActiveChip}
          />

          <div className="ra-workspace">
            <div className="ra-main-panel">
              <RefreshOverlay active={isRefreshing}>
                <RescuerRosterTable
                  rescuers={rescuers}
                  pagination={pagination}
                  onView={openViewModal}
                  onEdit={openEditModal}
                  onDeactivate={handleDeactivate}
                />
              </RefreshOverlay>
            </div>
            <aside className="ra-side-panel">
              <div className="ra-side-head">
                <span className="ra-title">Team cards</span>
              </div>
              <RescuerTeamGrid teams={teams} />
            </aside>
          </div>
        </>
      )}

      <RescuerAccountModal
        mode={modalMode}
        isOpen={isModalOpen}
        form={form}
        setForm={setForm}
        formError={formError}
        isSaving={isSaving}
        teamOptions={teamOptions}
        accountIdOptions={accountIdOptions}
        fallbackAccountId={payload?.next_account_id || ''}
        roles={filters.roles || ['Responder']}
        bloodTypes={filters.blood_types || ['Unknown']}
        onClose={closeModal}
        onReset={resetForm}
        onSubmit={submitForm}
      />

      <RescueTeamConfigModal
        key={teamConfigVersion}
        isOpen={teamConfigOpen}
        workspace={teamConfig}
        isLoading={teamConfigLoading}
        isSaving={teamConfigSaving}
        error={teamConfigError}
        onClose={() => !teamConfigSaving && setTeamConfigOpen(false)}
        onSave={saveTeamConfig}
        onDelete={removeTeamConfig}
      />
    </section>
  )
}

function filterParams(search, purok, activeChip) {
  const params = {
    search: search.trim(),
    purok,
    per_page: 25,
  }

  if (activeChip.startsWith('team:')) {
    params.team = activeChip.replace('team:', '')
  } else {
    params.duty_status = activeChip
  }

  return params
}
