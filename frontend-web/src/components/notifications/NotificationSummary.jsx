export default function NotificationSummary({ summary = {} }) {
  const cards = [
    {
      label: 'All notifications',
      value: summary.total || 0,
      note: 'Current command-center notices',
    },
    {
      label: 'Unread',
      value: summary.unread || 0,
      note: 'Needs HQ review',
    },
    {
      label: 'Critical',
      value: summary.critical || 0,
      note: 'Dispatch, weather, and household alerts',
    },
    {
      label: 'Selected',
      value: summary.selected || 0,
      note: 'Ready for delete action',
    },
  ]

  return (
    <div className="notification-summary-strip">
      {cards.map((card) => (
        <div className="notification-summary-card" key={card.label}>
          <div className="k">{card.label}</div>
          <div className="v">{card.value}</div>
          <div className="n">{card.note}</div>
        </div>
      ))}
    </div>
  )
}
