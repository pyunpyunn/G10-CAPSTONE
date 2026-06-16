import { ArchiveRestore, FolderOpen, Trash2 } from 'lucide-react'

export default function ArchiveSelectionTools({
  selectedCount,
  savedGroups = [],
  onSaveGroup,
  onDeleteSelected,
  onClearSelected,
  onOpenGroups,
}) {
  return (
    <section className="archive-selection-panel">
      <div className="archive-selection-row">
        <div>
          <span className="archive-selection-title">Selected logs</span>
          <span className="archive-selection-meta">{selectedCount} selected on the current archive tab</span>
        </div>
        <div className="archive-selection-actions">
          <button className="btn btn-secondary btn-sm" type="button" disabled={selectedCount === 0} onClick={onClearSelected}>
            Clear
          </button>
          <button className="btn btn-secondary btn-sm" type="button" disabled={selectedCount === 0} onClick={onSaveGroup}>
            <ArchiveRestore size={14} />
            Save group
          </button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={onOpenGroups} title={`${savedGroups.length} saved group${savedGroups.length === 1 ? '' : 's'}`}>
            <FolderOpen size={14} />
            Go to saved groups
          </button>
          <button className="btn btn-danger btn-sm" type="button" disabled={selectedCount === 0} onClick={onDeleteSelected}>
            <Trash2 size={14} />
            Delete forever
          </button>
        </div>
      </div>
    </section>
  )
}
