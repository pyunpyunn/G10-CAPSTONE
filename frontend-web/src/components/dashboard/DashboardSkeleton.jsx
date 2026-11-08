function SkeletonBlock({ className = '' }) {
  return <span className={`dashboard-skeleton-block ${className}`.trim()} />
}

function SkeletonStatusCard() {
  return (
    <div className="dashboard-status-card dashboard-skeleton-status-card">
      <div className="dispatch-chart-head"><SkeletonBlock className="dashboard-skeleton-status-title" /><SkeletonBlock className="dashboard-skeleton-icon" /></div>
      <div className="dashboard-skeleton-number-content">
        <div className="dashboard-skeleton-reporting">
          <SkeletonBlock className="dashboard-skeleton-label" />
          <SkeletonBlock className="dashboard-skeleton-meter" />
        </div>
        <div className="dashboard-skeleton-numbers">
          {Array.from({ length: 4 }, (_, index) => <div key={index}><SkeletonBlock /><SkeletonBlock /></div>)}
        </div>
      </div>
    </div>
  )
}

export default function DashboardSkeleton() {
  return (
    <div className="dashboard-layout dashboard-skeleton" role="status" aria-label="Loading dashboard" aria-busy="true">
      <div className="dashboard-layout-mobile-actions" aria-hidden="true"><SkeletonBlock className="dashboard-skeleton-action" /></div>
      <div className="dashboard-main" aria-hidden="true">
        <div className="dashboard-skeleton-banner">
          <div><SkeletonBlock className="dashboard-skeleton-banner-kicker" /><SkeletonBlock className="dashboard-skeleton-banner-title" /></div>
          <SkeletonBlock className="dashboard-skeleton-banner-meta" />
        </div>

        <section className="panel dashboard-operation-views dashboard-skeleton-operation">
          <div className="panel-head">
            <SkeletonBlock className="dashboard-skeleton-heading" />
            <SkeletonBlock className="dashboard-skeleton-select" />
          </div>
          <div className="dashboard-dispatch-graphs">
            <SkeletonStatusCard />
            <SkeletonStatusCard />
          </div>
        </section>

        <div className="dashboard-impact-views">
          <SkeletonBlock className="dashboard-skeleton-select" />
          <section className="panel rescue-criteria-panel">
            <div className="panel-head"><SkeletonBlock className="dashboard-skeleton-heading" /><SkeletonBlock className="dashboard-skeleton-label" /></div>
            <div className="dashboard-skeleton-impact-chart">
              <div className="dashboard-skeleton-chart-grid">
                {Array.from({ length: 5 }, (_, index) => <i key={index} />)}
              </div>
              <div className="dashboard-skeleton-chart-line" />
            </div>
          </section>
        </div>
      </div>

      <aside className="dashboard-overview" aria-label="Dashboard side information" aria-hidden="true">
        <div className="dashboard-layout-actions"><SkeletonBlock className="dashboard-skeleton-action" /></div>
        <section className="overview-card dashboard-weather-card">
          <div className="panel-head"><SkeletonBlock className="dashboard-skeleton-heading" /><SkeletonBlock className="dashboard-skeleton-link" /></div>
          <div className="dashboard-weather-compact">
            <SkeletonBlock className="dashboard-skeleton-weather-icon" />
            <div className="dashboard-weather-main">
              <SkeletonBlock className="dashboard-skeleton-label" />
              <SkeletonBlock className="dashboard-skeleton-temperature" />
            </div>
            <div className="dashboard-weather-metrics">
              <SkeletonBlock className="dashboard-skeleton-label" /><SkeletonBlock className="dashboard-skeleton-label dashboard-skeleton-value" />
              <SkeletonBlock className="dashboard-skeleton-label" /><SkeletonBlock className="dashboard-skeleton-label dashboard-skeleton-value" />
            </div>
          </div>
        </section>

        <section className="overview-card">
          <div className="panel-head"><SkeletonBlock className="dashboard-skeleton-heading" /><SkeletonBlock className="dashboard-skeleton-link" /></div>
          <div className="dashboard-map-preview dashboard-skeleton-map"><SkeletonBlock className="dashboard-skeleton-map-shape" /></div>
          <div className="dashboard-side-metrics">
            {Array.from({ length: 3 }, (_, index) => <div key={index}><SkeletonBlock className="dashboard-skeleton-metric-value" /><SkeletonBlock className="dashboard-skeleton-metric-label" /></div>)}
          </div>
        </section>

        <section className="overview-card">
          <div className="panel-head"><SkeletonBlock className="dashboard-skeleton-heading" /><SkeletonBlock className="dashboard-skeleton-link" /></div>
          <div className="dashboard-side-metrics">
            {Array.from({ length: 3 }, (_, index) => <div key={index}><SkeletonBlock className="dashboard-skeleton-metric-value" /><SkeletonBlock className="dashboard-skeleton-metric-label" /></div>)}
          </div>
          <div className="dashboard-skeleton-request-rows">
            {Array.from({ length: 2 }, (_, index) => <div key={index}><SkeletonBlock /><SkeletonBlock /><SkeletonBlock /></div>)}
          </div>
        </section>
      </aside>
    </div>
  )
}
