import { ArrowLeft, Eye, FolderOpen, Trash2, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { ARCHIVE_TABS, archiveRecordTitle } from '../../utils/archiveHelpers'

export default function ArchiveSavedLogsModal({
  isOpen,
  groups = [],
  group,
  onClose,
  onOpenGroup,
  onViewRecord,
  onDeleteRecord,
  onDeleteGroup,
}) {
  if (!isOpen) {
    return null
  }

  const categoryLabel = group ? groupCategoryLabel(group.category) : 'Saved groups'

  return createPortal(
    <div
      className="archive-view-modal-overlay open"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose()
        }
      }}
    >
      <section className="archive-saved-modal" role="dialog" aria-modal="true" aria-labelledby="savedLogsTitle">
        <div className="archive-view-head">
          <div>
            <div className="archive-view-kicker">{group ? 'Saved group' : 'Saved groups'}</div>
            <div className="archive-view-title" id="savedLogsTitle">
              {group ? `${categoryLabel} - ${group.records.length} saved` : `${groups.length} saved group${groups.length === 1 ? '' : 's'}`}
            </div>
            {group && <div className="archive-saved-meta">{group.savedAt}</div>}
          </div>
          <button className="archive-view-close" type="button" onClick={onClose} aria-label="Close saved group">
            <X size={16} />
          </button>
        </div>

        <div className="archive-view-body">
          {group ? (
            <div className="archive-saved-list">
              {group.records.map((record) => (
                <article className="archive-saved-item" key={`${group.id}-${record.id}`}>
                  <div>
                    <strong>{archiveRecordTitle(record)}</strong>
                    <span>{record.id}</span>
                    <p className="archive-saved-summary">{recordSummary(record)}</p>
                  </div>
                  <div className="archive-saved-actions">
                    <button className="btn btn-secondary btn-sm" type="button" onClick={() => onViewRecord(record)}>
                      <Eye size={14} />
                      View
                    </button>
                    <button className="btn btn-danger btn-sm" type="button" onClick={() => onDeleteRecord(group.id, record.id)}>
                      <Trash2 size={14} />
                      Delete
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="archive-saved-list">
              {groups.length === 0 ? (
                <article className="archive-saved-item">
                  <div>
                    <strong>No saved groups yet</strong>
                    <span>Select archive records, then click Save group.</span>
                  </div>
                </article>
              ) : (
                groups.map((savedGroup) => (
                  <article className="archive-saved-item" key={savedGroup.id}>
                    <div>
                      <strong>{groupCategoryLabel(savedGroup.category)} - {savedGroup.records.length} saved</strong>
                      <span>{savedGroup.savedAt}</span>
                    </div>
                    <div className="archive-saved-actions">
                      <button className="btn btn-secondary btn-sm" type="button" onClick={() => onOpenGroup(savedGroup.id)}>
                        <FolderOpen size={14} />
                        Open
                      </button>
                      <button className="btn btn-danger btn-sm" type="button" onClick={() => onDeleteGroup(savedGroup.id)}>
                        <Trash2 size={14} />
                        Delete
                      </button>
                    </div>
                  </article>
                ))
              )}
            </div>
          )}
        </div>

        <div className="archive-view-actions">
          {group && (
            <>
              <button className="btn btn-secondary btn-sm" type="button" onClick={() => onOpenGroup('')}>
                <ArrowLeft size={14} />
                Back to saved groups
              </button>
              <button className="btn btn-danger btn-sm" type="button" onClick={() => onDeleteGroup(group.id)}>
                <Trash2 size={14} />
                Delete saved group
              </button>
            </>
          )}
          <button className="btn btn-primary btn-sm" type="button" onClick={onClose}>
            Done
          </button>
        </div>
      </section>
    </div>,
    document.body,
  )
}

function groupCategoryLabel(category) {
  return ARCHIVE_TABS.find((tab) => tab.key === category)?.label || category
}

function recordSummary(record) {
  const details = Array.isArray(record.details) ? record.details : []
  const summary = details
    .slice(0, 5)
    .map((item) => `${item.label}: ${item.value}`)
    .join(' | ')

  return summary || 'Open this record to view complete archive details.'
}
