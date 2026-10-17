import { Forward, Loader2, X, XCircle } from 'lucide-react'

export default function ResourceRequestBatchBar({
  selectedCount = 0,
  onClearSelection,
  onBatchReject,
  onBatchForward,
  isProcessing = false,
}) {
  if (selectedCount <= 0) return null

  return (
    <div className="rr-batch-bar-wrap">
      <div className="rr-batch-bar">
        {/* Selection Count Badge */}
        <div className="rr-batch-count">
          <span className="rr-batch-badge">{selectedCount}</span>
          <span className="rr-batch-label">
            {selectedCount === 1 ? 'request selected' : 'requests selected'}
          </span>
        </div>

        <div className="rr-batch-divider" />

        {/* Action Buttons */}
        <div className="rr-batch-actions">
          <button
            className="rr-batch-btn primary"
            type="button"
            disabled={isProcessing}
            onClick={onBatchForward}
          >
            {isProcessing ? <Loader2 size={15} className="spin" /> : <Forward size={15} />}
            Batch Forward (TrackingAid)
          </button>

          <button
            className="rr-batch-btn danger"
            type="button"
            disabled={isProcessing}
            onClick={onBatchReject}
          >
            {isProcessing ? <Loader2 size={15} className="spin" /> : <XCircle size={15} />}
            Batch Reject
          </button>
        </div>

        <button
          className="rr-batch-close"
          type="button"
          disabled={isProcessing}
          onClick={onClearSelection}
          title="Clear selection"
        >
          <X size={16} />
        </button>
      </div>
    </div>
  )
}
