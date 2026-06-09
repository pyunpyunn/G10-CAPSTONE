export default function StatCard({ label, value, sub, tone }) {
  return (
    <div className={`stat-card ${tone ? `c-${tone}` : ''}`}>
      <div className="lbl">{label}</div>
      <div className="val">{value}</div>
      {sub && <div className="sub">{sub}</div>}
    </div>
  )
}
