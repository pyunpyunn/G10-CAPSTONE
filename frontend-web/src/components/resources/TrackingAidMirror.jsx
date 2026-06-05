export default function TrackingAidMirror({ items = [] }) {
  return (
    <div className="rr-panel rr-mirror">
      <div className="rr-panel-head">
        <span className="rr-title">TrackingAid resource mirror</span>
        <span className="rr-subtle">availability only - not delivery control</span>
      </div>
      <div className="rr-mirror-grid">
        {items.map((item) => (
          <div className="rr-item" key={item.label}>
            <strong>{item.label}</strong>
            <span>{item.source} - {item.status} - {item.detail}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
