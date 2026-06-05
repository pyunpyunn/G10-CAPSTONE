import {
  makeProgress,
  percent,
} from '../../utils/householdStatusHelpers'

export default function HouseholdSummary({ summary }) {
  const progress = makeProgress(summary)

  return (
    <>
      <SummaryCards summary={summary} />
      <ProgressBar progress={progress} />
    </>
  )
}

function SummaryCards({ summary }) {
  const cards = [
    { label: 'Unchecked', value: summary.unchecked, percent: percent(summary.unchecked, summary.total), className: 'c-unch' },
    { label: 'Safe total', value: summary.safe_total, percent: percent(summary.safe_total, summary.total), className: 'c-safeT' },
    { label: 'Safe only', value: summary.safe_only, percent: percent(summary.safe_only, summary.total), className: 'c-safe' },
    { label: 'Evacuated', value: summary.evacuated, percent: percent(summary.evacuated, summary.total), className: 'c-evac' },
    { label: 'Unsafe', value: summary.unsafe, percent: percent(summary.unsafe, summary.total), className: 'c-unsafe' },
  ]

  return (
    <div className="hh-summary-bar">
      {cards.map((card) => (
        <div className={`hh-sum-card ${card.className}`} key={card.label}>
          <div className="sl">{card.label}</div>
          <div className="sv">{card.value || 0}</div>
          <div className="ss">{card.percent}%</div>
        </div>
      ))}
    </div>
  )
}

function ProgressBar({ progress }) {
  return (
    <div className="hh-progress">
      <div className="hh-prog-bar" aria-label="Household status progress">
        {progress.map((item) => (
          <div className={`hh-prog-seg ${item.className}`} style={{ width: `${item.percent}%` }} key={item.label} />
        ))}
      </div>
      <div className="hh-prog-legend">
        {progress.map((item) => (
          <div className="hh-prog-leg" key={item.label}>
            <span className={`hh-prog-leg-sq ${item.className}`} />
            {item.label} {item.percent}%
          </div>
        ))}
      </div>
    </div>
  )
}
