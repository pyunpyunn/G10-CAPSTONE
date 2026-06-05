import Badge from '../ui/Badge'
import { displayValue, percentLabel } from '../../utils/situationReportHelpers'

export default function SitrepPreview({ summary }) {
  if (!summary) {
    return null
  }

  const household = summary.household
  const event = summary.event
  const casualties = summary.casualties

  return (
    <section className="sr-panel active" id="sr-tab-live">
      <div className="sr-print-note">
        <span>Official BDRRMC SitRep preview generated from the selected disaster event log.</span>
        <span>Draft status: <strong>{event.status}</strong> - Event: <strong>{event.name}</strong></span>
      </div>

      <article className="sr-doc" aria-label="BDRRMC situation report document preview">
        <header className="sr-doc-header">
          <div className="sr-doc-rp-label">Republic of the Philippines</div>
          <div className="sr-doc-office">Barangay Disaster Risk Reduction and Management Committee<br />Emergency Operations Center</div>
          <div className="sr-doc-title">Situation Report<br /><input className="sr-title-input" type="text" value={event.name} readOnly /></div>
          <div className="sr-doc-subtitle">Prepared for incident command, MDRRMO coordination, household monitoring, and resource validation.</div>
        </header>

        <div className="sr-doc-meta">
          <Meta label="Disaster type" value={event.type} />
          <Meta label="Date declared" value={event.declared_at} />
          <Meta label="Date finished" value={event.finished_at} />
          <Meta label="Alert level" value={event.severity} />
          <Meta label="Reporting period" value={`${summary.report.period_start} - ${summary.report.period_end}`} />
          <Meta label="Area coverage" value={event.coverage} />
          <Meta label="Situation status" value={event.situation_status} />
          <Meta label="Prepared by" value={summary.report.prepared_by} />
        </div>

        <SituationOverview summary={summary} />
        <AffectedPopulation household={household} casualties={casualties} />
        <Casualties casualties={casualties} />
        <EvacuationCenters rows={summary.evacuation} />
        <RescueTimeline dispatch={summary.dispatch} />
        <DamageAssessment damage={summary.damage} />
        <ResourcesRequests resources={summary.resources} />
        <ActionsTaken actions={summary.actions} />
        <NextActions summary={summary} />
      </article>
    </section>
  )
}

function SituationOverview({ summary }) {
  const weather = summary.weather

  return (
    <DocSection number="I" title="Situation Overview" subtitle="Incident status, weather trigger, and source of official advisories.">
      <div className="sr-wx-grid">
        <MiniCard label="Condition" value={weather.condition} />
        <MiniCard label="Wind" value={weather.wind} />
        <MiniCard label="Rainfall" value={weather.rainfall} />
        <MiniCard label="Temperature" value={weather.temperature} />
        <MiniCard label="Source" value={weather.source} />
      </div>
      <div className="sr-note">
        The BDRRMC EOC is monitoring household status updates, evacuation center capacity, response team movement, and resource requests linked to the declared disaster event.
        <br />
        {weather.advisory}
      </div>
    </DocSection>
  )
}

function AffectedPopulation({ household, casualties }) {
  return (
    <DocSection number="II" title="Affected Population" subtitle="Household status summary based on geotagged updates and responder validation.">
      <div className="sr-pop-grid">
        <MiniCard label="Total HH affected" value={household.total} />
        <MiniCard label="Safe total" value={household.safe_total} sub={percentLabel(household.safe_percent)} tone="safe" />
        <MiniCard label="Evacuated" value={household.evacuated} sub={percentLabel(household.evacuated_percent)} />
        <MiniCard label="Unsafe / At risk" value={household.unsafe} sub={percentLabel(household.unsafe_percent)} tone="danger" />
        <MiniCard label="Unchecked" value={household.unchecked} sub={percentLabel(household.unchecked_percent)} />
        <MiniCard label="Deaths" value={casualties.deaths} />
        <MiniCard label="Missing" value={casualties.missing} tone="warn" />
        <MiniCard label="Injured" value={casualties.injured} tone="warn" />
      </div>
      <div className="sr-note">
        Reporting progress: <strong>{household.progress_text}</strong> checked - <span>{household.progress_sub}</span>
        <div className="sr-progress" aria-hidden="true">
          <span style={{ width: household.progress_text }} />
        </div>
      </div>
      <div className="sr-table-scroll sr-section-table">
        <table>
          <thead>
            <tr>
              <th>Purok</th>
              <th>Households in scope</th>
              <th>Safe</th>
              <th>Evacuated</th>
              <th>Unsafe</th>
              <th>Unchecked</th>
            </tr>
          </thead>
          <tbody>
            {household.puroks.map((row) => (
              <tr key={row.purok}>
                <td>{row.purok}</td>
                <td>{row.total}</td>
                <td>{row.safe}</td>
                <td>{row.evacuated}</td>
                <td>{row.unsafe}</td>
                <td>{row.unchecked}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </DocSection>
  )
}

function Casualties({ casualties }) {
  return (
    <DocSection number="III" title="Casualties and Immediate Needs" subtitle="Confirmed casualties and priority protection notes for vulnerable households.">
      <div className="sr-cas-grid">
        <MiniCard label="Deaths" value={`${casualties.deaths} confirmed`} tone="danger" />
        <MiniCard label="Missing" value={`${casualties.missing} persons`} tone="warn" />
        <MiniCard label="Injured" value={`${casualties.injured} persons`} tone="warn" />
        <MiniCard label="Rescued" value={`${casualties.rescued} persons assisted`} tone="safe" />
      </div>
      <div className="sr-law">Priority validation is required for children, senior citizens, persons with disability, pregnant women, and households with missing or injured members.</div>
    </DocSection>
  )
}

function EvacuationCenters({ rows }) {
  return (
    <DocSection number="IV" title="Evacuation Centers" subtitle="Open sites, occupancy, remaining capacity, and route condition.">
      {rows.length === 0 ? (
        <div className="sr-note">No evacuation center records are linked to this event yet.</div>
      ) : rows.map((row) => (
        <div className="sr-evac-row" key={row.name}>
          <div className="sr-row-label">{row.name}<small>{row.type} - {row.status}</small></div>
          <div>{row.families}</div>
          <div>{row.persons}</div>
          <div><Badge tone={row.capacity_tone}>{row.capacity_status}</Badge></div>
        </div>
      ))}
    </DocSection>
  )
}

function RescueTimeline({ dispatch }) {
  return (
    <DocSection number="V" title="Rescue Operations Timeline" subtitle="Team dispatch, route, coverage, and field outcome summary.">
      {dispatch.timeline.length === 0 ? (
        <div className="sr-note">No broadcast or dispatch timeline records are linked to this event yet.</div>
      ) : dispatch.timeline.map((row, index) => (
        <div className="sr-ops-row" key={`${row.time}-${index}`}>
          <div className="sr-row-label">{displayValue(row.time, 'Time pending')}<small>{row.title}</small></div>
          <div>{row.actor}</div>
          <div>{row.detail}</div>
          <div><Badge tone={row.tone}>{row.status}</Badge></div>
        </div>
      ))}
      <div className="sr-table-scroll sr-section-table">
        <table>
          <thead>
            <tr>
              <th>Team</th>
              <th>Deployed at</th>
              <th>Area</th>
              <th>HH reached</th>
              <th>Outcomes</th>
            </tr>
          </thead>
          <tbody>
            {dispatch.rows.length === 0 ? (
              <tr><td colSpan="5">No dispatch rows yet.</td></tr>
            ) : dispatch.rows.map((row, index) => (
              <tr key={`${row.team}-${index}`}>
                <td>{row.team}</td>
                <td>{displayValue(row.deployed_at)}</td>
                <td>{row.area}</td>
                <td>{row.households_reached}</td>
                <td>{row.outcomes}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </DocSection>
  )
}

function DamageAssessment({ damage }) {
  return (
    <DocSection number="VI" title="Initial Damage Assessment" subtitle="Rapid DANA entries to be updated after field validation.">
      <div className="sr-dmg-row">
        <MiniCard label="Partially damaged houses" value={damage.partial} tone="warn" />
        <MiniCard label="Totally damaged houses" value={damage.total} tone="danger" />
        <MiniCard label="Estimated cost" value={damage.cost} />
      </div>
    </DocSection>
  )
}

function ResourcesRequests({ resources }) {
  return (
    <DocSection number="VII" title="Resources Deployed and Requests" subtitle="Validated requests, current handoff status, and TrackingAid coordination.">
      <div className="sr-table-scroll">
        <table>
          <thead>
            <tr>
              <th>Item / request</th>
              <th>Quantity</th>
              <th>Source</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {resources.rows.length === 0 ? (
              <tr><td colSpan="4">No resource requests are linked to this event yet.</td></tr>
            ) : resources.rows.map((row, index) => (
              <tr key={`${row.item}-${index}`}>
                <td>{row.item}</td>
                <td>{row.quantity}</td>
                <td>{row.source}</td>
                <td><Badge tone={row.status_tone}>{row.status}</Badge></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </DocSection>
  )
}

function ActionsTaken({ actions }) {
  return (
    <DocSection number="VIII" title="Actions Taken and Recommendations" subtitle="Command actions and required next decisions.">
      <div className="sr-action-grid">
        {actions.map((action) => (
          <MiniCard label={action.unit} value={action.action} key={action.unit} />
        ))}
      </div>
      <div className="sr-note">Recommendations: continue evacuation advisories in high-risk zones, validate all injured/missing household entries, and prepare relief pack release after request approval.</div>
    </DocSection>
  )
}

function NextActions({ summary }) {
  return (
    <DocSection number="IX" title="Next Actions and Report Schedule" subtitle="Report custody, next reporting time, and sign-off trail.">
      <div className="sr-signature-grid">
        <Meta label="Next SitRep" value={summary.report.next_report} />
        <Meta label="Submitted by" value={summary.report.prepared_by} />
        <Meta label="Reviewed by" value={summary.report.reviewed_by} />
      </div>
      <div className="sr-law">Archive rule: once generated and approved, the SitRep is stored with the disaster event log and remains downloadable as PDF or CSV for audit and post-disaster review.</div>
    </DocSection>
  )
}

function DocSection({ number, title, subtitle, children }) {
  return (
    <section className="sr-section">
      <div className="sr-section-header">
        <span className="sr-section-num">{number}</span>
        <div>
          <div className="sr-section-title">{title}</div>
          <div className="sr-section-subtitle">{subtitle}</div>
        </div>
      </div>
      {children}
    </section>
  )
}

function Meta({ label, value }) {
  return (
    <div className="sr-meta-item">
      <span>{label}</span>
      <input className="sr-doc-input" type="text" value={displayValue(value)} readOnly />
    </div>
  )
}

function MiniCard({ label, value, sub, tone = '' }) {
  return (
    <div className={`sr-mini-card ${tone}`.trim()}>
      <span>{label}</span>
      <strong>{displayValue(value)}</strong>
      {sub && <small>{sub}</small>}
    </div>
  )
}
