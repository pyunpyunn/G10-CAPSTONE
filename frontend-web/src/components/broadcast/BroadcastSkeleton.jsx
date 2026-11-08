function SkeletonBlock({ className = '' }) {
  return <span className={`bc-skeleton-block ${className}`.trim()} />
}

function SkeletonField({ className = '' }) {
  return <div className={`bc-field ${className}`.trim()}>
    <SkeletonBlock className="bc-skeleton-label" />
    <SkeletonBlock className="bc-skeleton-input" />
  </div>
}

export default function BroadcastSkeleton({ includeNavigation = false }) {
  return (
    <div className="bc-skeleton" role="status" aria-label="Loading broadcast page" aria-busy="true">
      {includeNavigation && <div className="bc-console-nav" aria-hidden="true">
        <div className="bc-view-tabs"><SkeletonBlock className="bc-skeleton-tab" /><SkeletonBlock className="bc-skeleton-tab" /></div>
        <SkeletonBlock className="bc-skeleton-back" />
      </div>}

      <div className="bc-compose-layout" aria-hidden="true">
        <main className="bc-editor-wrap">
          <div className="bc-skeleton-editor">
            <section className="bc-step">
              <div className="bc-step-heading"><span className="bc-step-number">1</span><SkeletonBlock className="bc-skeleton-step-title" /></div>
              <div className="bc-fields bc-fields-details">
                <SkeletonField />
                <SkeletonField />
                <SkeletonField />
                <SkeletonField />
              </div>
            </section>

            <section className="bc-step">
              <div className="bc-step-heading"><span className="bc-step-number">2</span><SkeletonBlock className="bc-skeleton-step-title" /><SkeletonBlock className="bc-skeleton-aside" /></div>
              <div className="bc-area-row"><SkeletonField /><SkeletonField /><SkeletonBlock className="bc-skeleton-input" /></div>
            </section>

            <section className="bc-step">
              <div className="bc-step-heading"><span className="bc-step-number">3</span><SkeletonBlock className="bc-skeleton-step-title" /><SkeletonBlock className="bc-skeleton-aside" /></div>
              <div className="bc-status-slots">
                {Array.from({ length: 4 }, (_, index) => <SkeletonField key={index} />)}
                <SkeletonBlock className="bc-skeleton-manage" />
              </div>
              <div className="bc-message-fields"><SkeletonField /><SkeletonField /></div>
            </section>

            <div className="bc-form-footer"><SkeletonBlock className="bc-skeleton-footer-note" /><SkeletonBlock className="bc-skeleton-submit" /></div>
          </div>
        </main>

        <aside className="bc-preview-column">
          <section className="bc-push-preview">
            <SkeletonBlock className="bc-skeleton-preview-label" />
            <div className="bc-notification-preview">
              <SkeletonBlock className="bc-skeleton-notification-icon" />
              <div><SkeletonBlock className="bc-skeleton-notification-title" /><SkeletonBlock className="bc-skeleton-notification-message" /></div>
              <SkeletonBlock className="bc-skeleton-now" />
            </div>
            <div className="bc-preview-statuses">
              {Array.from({ length: 4 }, (_, index) => <SkeletonBlock key={index} className="bc-skeleton-status" />)}
            </div>
          </section>
          <section className="bc-event-meta">
            {Array.from({ length: 4 }, (_, index) => <div key={index}><SkeletonBlock className="bc-skeleton-meta-label" /><SkeletonBlock className="bc-skeleton-meta-value" /></div>)}
          </section>
        </aside>
      </div>
    </div>
  )
}
