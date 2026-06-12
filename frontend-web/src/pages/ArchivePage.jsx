import { useEffect, useState } from 'react'
import { exportArchive, getArchiveRecords } from '../api/archiveApi'
import ArchiveDownloadMenu from '../components/archive/ArchiveDownloadMenu'
import ArchiveFilters from '../components/archive/ArchiveFilters'
import ArchiveRecordModal from '../components/archive/ArchiveRecordModal'
import ArchiveTable from '../components/archive/ArchiveTable'
import ArchiveTabs from '../components/archive/ArchiveTabs'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  ARCHIVE_TABS,
  archiveErrorMessage,
  archiveFileName,
  archiveParams,
} from '../utils/archiveHelpers'
import {
  detailsToRows,
  downloadExcelWorkbook,
  downloadPdfReport,
  parseCsvText,
} from '../utils/exportFileHelpers'

export default function ArchivePage() {
  const [activeCategory, setActiveCategory] = useState('disaster-events')
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [eventId, setEventId] = useState('all')
  const [status, setStatus] = useState('all')
  const [selectedRecord, setSelectedRecord] = useState(null)

  useEffect(() => {
    let ignore = false

    async function loadRecords() {
      setIsLoading(true)
      setError('')

      try {
        const params = archiveParams({ search, purok, eventId, status })
        const data = await getArchiveRecords(activeCategory, params)

        if (!ignore) {
          setPayload(data)
        }
      } catch (loadError) {
        if (!ignore) {
          setError(archiveErrorMessage(loadError))
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadRecords()

    return () => {
      ignore = true
    }
  }, [activeCategory, search, purok, eventId, status])

  function currentParams() {
    return archiveParams({ search, purok, eventId, status })
  }

  function changeCategory(category) {
    setActiveCategory(category)
    setSelectedRecord(null)
    setMessage('')
  }

  async function downloadCategory(type) {
    setMessage('')

    try {
      const blob = await exportArchive(activeCategory, 'csv', currentParams())
      const rows = parseCsvText(await blob.text())

      if (type === 'pdf') {
        downloadPdfReport(archiveFileName(activeCategory, 'pdf'), `${categoryLabel} archive`, rows)
        setMessage('Archive PDF downloaded.')
        return
      }

      downloadExcelWorkbook(archiveFileName(activeCategory, 'xls'), `${categoryLabel} archive`, rows)
      setMessage('Archive Excel downloaded.')
    } catch (downloadError) {
      setMessage(archiveErrorMessage(downloadError, 'Archive export cannot be downloaded right now.'))
    }
  }

  function downloadSelectedRecord(type) {
    const rows = detailsToRows(selectedRecord?.details || [])
    const fileBase = `resqperation-archive-record-${selectedRecord?.id || 'details'}`

    if (type === 'pdf') {
      downloadPdfReport(`${fileBase}.pdf`, recordExportTitle(selectedRecord, categoryLabel), rows)
      setMessage('Archive record PDF downloaded.')
      return
    }

    downloadExcelWorkbook(`${fileBase}.xls`, recordExportTitle(selectedRecord, categoryLabel), rows)
    setMessage('Archive record Excel downloaded.')
  }

  const categoryLabel = ARCHIVE_TABS.find((tab) => tab.key === activeCategory)?.label || 'Archive record'
  const records = payload?.records?.data || []
  const pagination = payload?.records || {}
  const filters = payload?.filters || {}

  return (
    <section className="page active archive-page">
      <PageHeader
        title="Archive"
        actions={
          <ArchiveDownloadMenu disabled={!payload || isLoading} onDownload={downloadCategory} />
        }
      />

      <ArchiveTabs activeCategory={activeCategory} onChange={changeCategory} />

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && (
        <>
          <ArchiveFilters
            search={search}
            onSearchChange={setSearch}
            purok={purok}
            onPurokChange={setPurok}
            eventId={eventId}
            onEventChange={setEventId}
            status={status}
            onStatusChange={setStatus}
            filters={filters}
          />

          {message && <div className="rr-message archive-message">{message}</div>}

          <ArchiveTable
            category={activeCategory}
            records={records}
            pagination={pagination}
            onView={setSelectedRecord}
          />
        </>
      )}

      <ArchiveRecordModal
        record={selectedRecord}
        categoryLabel={categoryLabel}
        onClose={() => setSelectedRecord(null)}
        onDownload={downloadSelectedRecord}
      />
    </section>
  )
}

function recordExportTitle(record, categoryLabel) {
  return `${categoryLabel} - ${record?.id || 'Record details'}`
}
