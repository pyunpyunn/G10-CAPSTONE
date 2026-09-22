import { useEffect, useState } from 'react'
import {
  createSavedArchiveGroup,
  deleteArchiveRecords,
  deleteSavedArchiveGroup,
  deleteSavedArchiveGroupRecord,
  exportArchive,
  getArchiveRecords,
  getSavedArchiveGroups,
} from '../api/archiveApi'
import ArchiveDownloadMenu from '../components/archive/ArchiveDownloadMenu'
import ArchiveRecordModal from '../components/archive/ArchiveRecordModal'
import ArchiveSavedLogsModal from '../components/archive/ArchiveSavedLogsModal'
import ArchiveSelectionTools from '../components/archive/ArchiveSelectionTools'
import ArchiveTable from '../components/archive/ArchiveTable'
import ArchiveTabs from '../components/archive/ArchiveTabs'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
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
  const [page, setPage] = useState(1)
  const [selectedRecord, setSelectedRecord] = useState(null)
  const [selectedRecordCategory, setSelectedRecordCategory] = useState('disaster-events')
  const [selectedByCategory, setSelectedByCategory] = useState({})
  const [selectedRecordsByCategory, setSelectedRecordsByCategory] = useState({})
  const [savedGroups, setSavedGroups] = useState([])
  const [openGroupId, setOpenGroupId] = useState('')
  const [isSavedGroupsOpen, setIsSavedGroupsOpen] = useState(false)
  const [refreshKey, setRefreshKey] = useState(0)
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [eventId, setEventId] = useState('all')
  const [status, setStatus] = useState('all')

  useEffect(() => {
    let ignore = false

    async function loadRecords() {
      setIsLoading(true)
      setError('')

      try {
        const params = archiveParams({ search, purok, eventId, status, page })
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
  }, [activeCategory, eventId, page, purok, refreshKey, search, status])

  useEffect(() => {
    let ignore = false

    async function loadGroups() {
      try {
        const data = await getSavedArchiveGroups()

        if (!ignore) {
          setSavedGroups(data.groups || [])
        }
      } catch (groupError) {
        if (!ignore) {
          setMessage(archiveErrorMessage(groupError, 'Saved archive groups cannot be loaded right now.'))
        }
      }
    }

    loadGroups()

    return () => {
      ignore = true
    }
  }, [])

  function currentParams() {
    return archiveParams({ search, purok, eventId, status, page })
  }

  function changeCategory(category) {
    setActiveCategory(category)
    setSelectedRecord(null)
    setSelectedRecordCategory(category)
    setMessage('')
    setPage(1)
    setSearch('')
    setPurok('all')
    setEventId('all')
    setStatus('all')
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

  function selectedIds() {
    return selectedByCategory[activeCategory] || []
  }

  function selectedRecords() {
    return Object.values(selectedRecordsByCategory[activeCategory] || {})
  }

  function setActiveSelectedIds(nextIds) {
    setSelectedByCategory((current) => ({
      ...current,
      [activeCategory]: [...new Set(nextIds.map(String))],
    }))
  }

  function setActiveSelectedRecords(updater) {
    setSelectedRecordsByCategory((current) => ({
      ...current,
      [activeCategory]: updater(current[activeCategory] || {}),
    }))
  }

  function toggleSelectedRecord(recordId, checked, record = null) {
    const current = selectedIds()
    const id = String(recordId)

    if (checked) {
      setActiveSelectedIds([...current, id])
      setActiveSelectedRecords((recordsById) => ({
        ...recordsById,
        [id]: record,
      }))
      return
    }

    setActiveSelectedIds(current.filter((item) => item !== id))
    setActiveSelectedRecords((recordsById) => {
      const nextRecords = { ...recordsById }
      delete nextRecords[id]
      return nextRecords
    })
  }

  function toggleSelectedMany(recordIds, checked) {
    const current = selectedIds()
    const ids = recordIds.map(String)

    if (checked) {
      setActiveSelectedIds([...current, ...ids])
      setActiveSelectedRecords((recordsById) => {
        const nextRecords = { ...recordsById }

        records.forEach((record) => {
          const id = String(record.id)

          if (ids.includes(id)) {
            nextRecords[id] = record
          }
        })

        return nextRecords
      })
      return
    }

    setActiveSelectedIds(current.filter((item) => !ids.includes(item)))
    setActiveSelectedRecords((recordsById) => {
      const nextRecords = { ...recordsById }

      ids.forEach((id) => {
        delete nextRecords[id]
      })

      return nextRecords
    })
  }

  function clearSelected() {
    setActiveSelectedIds([])
    setActiveSelectedRecords(() => ({}))
  }

  async function saveSelectedGroup() {
    const recordsToSave = selectedRecords()

    if (recordsToSave.length === 0) {
      return
    }

    try {
      const result = await createSavedArchiveGroup(activeCategory, recordsToSave)
      const group = result.group

      setSavedGroups((current) => [group, ...current.filter((item) => item.id !== group.id)])
      setOpenGroupId(group.id)
      setIsSavedGroupsOpen(true)
      setMessage('')
      clearSelected()
      setRefreshKey((value) => value + 1)
    } catch (saveError) {
      setMessage(archiveErrorMessage(saveError, 'Selected logs cannot be saved as a database group right now.'))
    }
  }

  async function deleteSavedGroup(groupId) {
    const confirmed = window.confirm('Delete this saved group? This will not delete the original archive records.')

    if (!confirmed) {
      return
    }

    try {
      await deleteSavedArchiveGroup(groupId)
      setSavedGroups((current) => current.filter((group) => group.id !== groupId))
      setOpenGroupId('')
      setRefreshKey((value) => value + 1)
    } catch (deleteError) {
      setMessage(archiveErrorMessage(deleteError, 'Saved archive group cannot be deleted right now.'))
    }
  }

  async function deleteSavedLog(groupId, recordId) {
    const confirmed = window.confirm('Delete this log from the saved group? This will not delete the original archive record.')

    if (!confirmed) {
      return
    }

    try {
      await deleteSavedArchiveGroupRecord(groupId, recordId)

      setSavedGroups((current) => {
        const nextGroups = current
          .map((group) => {
            if (group.id !== groupId) {
              return group
            }

            return {
              ...group,
              records: group.records.filter((record) => String(record.id) !== String(recordId)),
            }
          })
          .filter((group) => group.records.length > 0)

        if (!nextGroups.some((group) => group.id === groupId)) {
          setOpenGroupId('')
        }

        return nextGroups
      })
      setRefreshKey((value) => value + 1)
    } catch (deleteError) {
      setMessage(archiveErrorMessage(deleteError, 'Saved archive log cannot be deleted right now.'))
    }
  }

  function viewSavedGroupRecord(record) {
    const group = savedGroups.find((item) => item.id === openGroupId)

    setSelectedRecord(record)
    setSelectedRecordCategory(group?.category || activeCategory)
    setOpenGroupId('')
    setIsSavedGroupsOpen(false)
  }

  function viewTableRecord(record) {
    setSelectedRecord(record)
    setSelectedRecordCategory(activeCategory)
  }

  async function deleteSelectedForever() {
    const ids = selectedIds()

    if (ids.length === 0) {
      return
    }

    const confirmedText = window.prompt(`Type DELETE to permanently delete ${ids.length} selected archive log(s).`)

    if (confirmedText !== 'DELETE') {
      setMessage('Delete cancelled. Type DELETE exactly to confirm permanent deletion.')
      return
    }

    try {
      const result = await deleteArchiveRecords(activeCategory, ids)
      setMessage(`${result.deleted_count || ids.length} selected log(s) deleted forever.`)
      clearSelected()
      setRefreshKey((value) => value + 1)
    } catch (deleteError) {
      setMessage(archiveErrorMessage(deleteError, 'Selected logs cannot be deleted right now.'))
    }
  }

  const categoryLabel = ARCHIVE_TABS.find((tab) => tab.key === activeCategory)?.label || 'Archive record'
  const records = payload?.records?.data || []
  const pagination = payload?.records || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload
  const filters = payload?.filters || {}
  const counts = { [activeCategory]: pagination.total || 0 }

  return (
    <section className="page active archive-page">
      <div className="archive-workspace-body">
        <ArchiveTabs
          activeCategory={activeCategory}
          onChange={changeCategory}
          isOpen={sidebarOpen}
          onToggle={() => setSidebarOpen((value) => !value)}
          counts={counts}
        />
        <div className="archive-workspace-main">
          <div className="archive-section-head">
            <div className="archive-section-title">
              <h1>{categoryLabel}</h1>
              <span>{pagination.total || 0} records</span>
            </div>
            <div className="archive-inline-filters">
              <label className="archive-filter-search">
                <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search records..." aria-label="Search records" />
              </label>
              <select aria-label="Purok" value={purok} onChange={(event) => { setPurok(event.target.value); setPage(1) }}>
                <option value="all">All puroks</option>
                {(filters.puroks || []).map((item) => <option value={item} key={item}>{item}</option>)}
              </select>
              <select aria-label="Event" value={eventId} onChange={(event) => { setEventId(event.target.value); setPage(1) }}>
                <option value="all">All events</option>
                {(filters.events || []).map((item) => <option value={item.event_id} key={item.event_id}>{item.label || item.name}</option>)}
              </select>
              <select aria-label="Status" value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }}>
                <option value="all">All statuses</option>
                {(filters.statuses || []).map((item) => <option value={item.key} key={item.key}>{item.label}</option>)}
              </select>
            </div>
          </div>

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <>
          {message && <div className="rr-message archive-message">{message}</div>}

          <ArchiveSelectionTools
            selectedCount={selectedIds().length}
            savedGroups={savedGroups}
            onSaveGroup={saveSelectedGroup}
            onDeleteSelected={deleteSelectedForever}
            onClearSelected={clearSelected}
            onOpenGroups={() => {
              setOpenGroupId('')
              setIsSavedGroupsOpen(true)
            }}
          />

          <RefreshOverlay active={isRefreshing}>
            <ArchiveTable
              category={activeCategory}
              records={records}
              pagination={pagination}
              selectedIds={selectedIds()}
              onToggleRecord={toggleSelectedRecord}
              onToggleDate={toggleSelectedMany}
              onTogglePage={toggleSelectedMany}
              onView={viewTableRecord}
              onPageChange={setPage}
            />
          </RefreshOverlay>
        </>
      )}

      <ArchiveSavedLogsModal
        isOpen={isSavedGroupsOpen}
        groups={savedGroups}
        group={savedGroups.find((group) => group.id === openGroupId)}
        onClose={() => {
          setOpenGroupId('')
          setIsSavedGroupsOpen(false)
        }}
        onOpenGroup={setOpenGroupId}
        onViewRecord={viewSavedGroupRecord}
        onDeleteRecord={deleteSavedLog}
        onDeleteGroup={deleteSavedGroup}
      />

      <ArchiveRecordModal
        record={selectedRecord}
        categoryLabel={ARCHIVE_TABS.find((tab) => tab.key === selectedRecordCategory)?.label || categoryLabel}
        onClose={() => setSelectedRecord(null)}
        onDownload={downloadSelectedRecord}
      />
        </div>
      </div>
    </section>
  )
}

function recordExportTitle(record, categoryLabel) {
  return `${categoryLabel} - ${record?.id || 'Record details'}`
}
