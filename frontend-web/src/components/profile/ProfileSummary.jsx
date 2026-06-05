export default function ProfileSummary({ summary = [] }) {
  return (
    <div className="profile-summary-strip">
      {summary.map((card) => (
        <div className="profile-summary-card" key={card.label}>
          <div className="k">{card.label}</div>
          <div className="v">{card.value}</div>
          <div className="n">{card.note}</div>
        </div>
      ))}
    </div>
  )
}
