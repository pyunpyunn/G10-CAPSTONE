import { FileCheck2, UserPlus } from 'lucide-react'
import { useEffect, useState } from 'react'
import {
  createRescuer,
  deactivateRescuer,
  getRescuer,
  getRescuers,
  updateRescuer,
} from '../api/rescuerApi'
import RescuerAccountModal from '../components/rescuers/RescuerAccountModal'
import RescuerFilters from '../components/rescuers/RescuerFilters'
import RescuerNotice from '../components/rescuers/RescuerNotice'
import RescuerRosterTable from '../components/rescuers/RescuerRosterTable'
import RescuerStats from '../components/rescuers/RescuerStats'
import RescuerTeamGrid from '../components/rescuers/RescuerTeamGrid'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  buildRescuerPayload,
  downloadCsv,
  emptyRescuerForm,
  exportRosterRows,
  accountIdForTeam,
  firstTeam,
  formFromRescuer,
  rescuerErrorMessage,
} from '../utils/rescuerHelpers'

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
  const summary = payload?.summary || {}
  const teams = payload?.teams || []
  const teamOptions = payload?.team_options || []
  const accountIdOptions = payload?.account_id_options || []
  const filters = payload?.filters || {}

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

  function exportRoster() {
    downloadCsv('resqperation-rescuer-roster.csv', exportRosterRows(rescuers))
  }

  return (
    <section className="page active rescuer-page">
      <PageHeader
        title="Rescuer Accounts"
        actions={
          <>
          <button className="btn btn-secondary btn-sm" type="button" onClick={exportRoster}>
            <FileCheck2 size={14} />
            Export roster
          </button>
          <button className="btn btn-primary btn-sm" type="button" onClick={openCreateModal}>
            <UserPlus size={14} />
            Create verified account
          </button>
          </>
        }
      />

      {isLoading && <LoadingState message="Loading verified rescuer roster..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && (
        <>
          <RescuerNotice />
          <RescuerStats summary={summary} />

          <RescuerFilters
            search={search}
            onSearchChange={setSearch}
            purok={purok}
            onPurokChange={setPurok}
            puroks={filters.puroks || []}
            activeChip={activeChip}
            onChipChange={setActiveChip}
          />

          <RescuerRosterTable
            rescuers={rescuers}
            pagination={pagination}
            onView={openViewModal}
            onEdit={openEditModal}
            onDeactivate={handleDeactivate}
          />
          <RescuerTeamGrid teams={teams} />
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
    </section>
  )
}

function filterParams(search, purok, activeChip) {
  const params = {
    search: search.trim(),
    purok,
    per_page: 25,
  }

  if (['SAR', 'Evacuation', 'Medical / First Aid', 'Relief & Transport'].includes(activeChip)) {
    params.team = activeChip
  } else {
    params.duty_status = activeChip
  }

  return params
}
