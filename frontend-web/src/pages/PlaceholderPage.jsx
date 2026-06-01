import { Construction } from 'lucide-react'

export default function PlaceholderPage({ page }) {
  return (
    <section className="module-page">
      <div className="module-header">
        <div>
          <p className="eyebrow">{page.kicker}</p>
          <h2>{page.title}</h2>
        </div>
        <span className="status-pill">Next module build</span>
      </div>

      <div className="module-placeholder">
        <Construction size={28} />
        <div>
          <strong>{page.title}</strong>
          <p>{page.summary}</p>
        </div>
      </div>
    </section>
  )
}
