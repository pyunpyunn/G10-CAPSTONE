import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  createSituationReport,
  getSituationReport,
  getSituationSummary,
  getSituationWorkspace,
} from '../api/situationReportApi'
import SavedSitrepPanel from '../components/situation/SavedSitrepPanel'
import SituationActionMenu from '../components/situation/SituationActionMenu'
import SituationEventPanel from '../components/situation/SituationEventPanel'
import SitrepGenerateModal from '../components/situation/SitrepGenerateModal'
import SitrepPreview from '../components/situation/SitrepPreview'
import LoadingState from '../components/ui/LoadingState'
import {
  buildGeneratePayload,
  downloadSituationExcel,
  downloadSituationPdf,
  emptyGenerateForm,
  situationErrorMessage,
} from '../utils/situationReportHelpers'

export default function SituationReportPage() {
  const [workspace, setWorkspace] = useState(null)
  const [selectedEventId, setSelectedEventId] = useState('')
  const [summary, setSummary] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSummaryLoading, setIsSummaryLoading] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [isGenerateOpen, setIsGenerateOpen] = useState(false)
  const [generateForm, setGenerateForm] = useState(emptyGenerateForm())
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const navigate = useNavigate()

  useEffect(() => {
    let ignore = false

    async function loadWorkspace() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getSituationWorkspace()

        if (!ignore) {
          setWorkspace(data)
        }
      } catch {
        if (!ignore) {
          setError('Situation Reporting cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadWorkspace()

    return () => {
      ignore = true
    }
  }, [])

  async function reloadWorkspace(showMessage = '') {
    try {
      const data = await getSituationWorkspace()
      setWorkspace(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch {
      setError('Situation Reporting cannot be refreshed right now.')
    }
  }

  async function handleSelectEvent(eventId) {
    setSelectedEventId(eventId)
    setSummary(null)
    setMessage('')
    setError('')

    if (!eventId) {
      return
    }

    setIsSummaryLoading(true)

    try {
      const data = await getSituationSummary(eventId)
      setSummary(data.summary)
      setGenerateForm(emptyGenerateForm(data.summary))
    } catch {
      setError('Selected event summary cannot be loaded right now.')
    } finally {
      setIsSummaryLoading(false)
    }
  }

  function openGenerateModal() {
    if (!summary || !selectedEventId) {
      setMessage('Select a disaster event first.')
      return
    }

    setGenerateForm(emptyGenerateForm(summary))
    setFormError('')
    setIsGenerateOpen(true)
  }

  async function submitGenerate(event) {
    event.preventDefault()
    setFormError('')
    setIsSaving(true)

    try {
      const data = await createSituationReport(buildGeneratePayload(selectedEventId, generateForm))
      setSummary(data.report.summary)
      setIsGenerateOpen(false)
      await reloadWorkspace(`${data.report.report_number} generated and locked.`)
    } catch (saveError) {
      setFormError(situationErrorMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  async function openSavedReport(report) {
    setError('')
    setMessage('')

    try {
      const data = await getSituationReport(report.sit_rep_id)
      setSelectedEventId(data.report.event_id || '')
      setSummary(data.report.summary)
      setGenerateForm(emptyGenerateForm(data.report.summary))
      setMessage(`${data.report.report_number} loaded from saved snapshots.`)
    } catch {
      setError('Saved SitRep cannot be loaded right now.')
    }
  }

  function handleArchiveCurrent() {
    if (!summary) {
      setMessage('Select a disaster event first.')
      return
    }

    setMessage('The current SitRep snapshot is available for Archive after it is generated and locked.')
  }

  async function handlePdfPreview() {
    if (!summary) {
      setMessage('Select a disaster event first.')
      return
    }

    await downloadSituationPdf(summary, generateForm.included_sections, generateForm.actions_text)
    setMessage('SitRep PDF downloaded.')
  }

  function handleExcelExport() {
    if (!summary) {
      setMessage('Select a disaster event first.')
      return
    }

    downloadSituationExcel(summary, generateForm.included_sections, generateForm.actions_text)
    setMessage('SitRep Excel downloaded.')
  }

  const events = workspace?.events || []
  const reports = workspace?.reports || []
  const selectedEvent = summary?.event
    ? {
        type: summary.event.type,
        declared_at: summary.event.declared_at,
        finished_at: summary.event.finished_at,
        scope: summary.event.scope,
      }
    : events.find((event) => event.event_id === selectedEventId)

  function toggleSection(section) {
    setGenerateForm((current) => {
      const includedSections = current.included_sections || []
      return {
        ...current,
        included_sections: includedSections.includes(section)
          ? includedSections.filter((item) => item !== section)
          : [...includedSections, section],
      }
    })
  }

  return (
    <main className="ops-page situation-page">
      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && workspace && (
        <>
          <SituationEventPanel
            events={events}
            selectedEventId={selectedEventId}
            selectedEvent={selectedEvent}
            onSelect={handleSelectEvent}
            actions={(
              <SituationActionMenu
                hasSummary={Boolean(summary)}
                onGenerate={openGenerateModal}
                onArchive={handleArchiveCurrent}
                onViewArchive={() => navigate('/archive')}
                onExportExcel={handleExcelExport}
                onExportPdf={handlePdfPreview}
              />
            )}
          />

          {message && <div className="rr-message sr-message">{message}</div>}
          {isSummaryLoading && <LoadingState inline />}

          {!isSummaryLoading && (
            <div id="sitrepSummaryContent">
              <SitrepPreview
                summary={summary}
                includedSections={generateForm.included_sections}
                actionsText={generateForm.actions_text}
                onToggleSection={toggleSection}
                onActionsChange={(value) => setGenerateForm((current) => ({ ...current, actions_text: value }))}
              />
            </div>
          )}

          <SavedSitrepPanel reports={reports} onOpen={openSavedReport} />
        </>
      )}

      <SitrepGenerateModal
        isOpen={isGenerateOpen}
        summary={summary}
        form={generateForm}
        setForm={setGenerateForm}
        formError={formError}
        isSaving={isSaving}
        onClose={() => setIsGenerateOpen(false)}
        onSubmit={submitGenerate}
      />
    </main>
  )
}
