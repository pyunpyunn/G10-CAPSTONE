import { CloudRain, Users, HeartPulse, Route, House, Package, ClipboardList, Thermometer, Wind, Droplets } from 'lucide-react'
import { displayValue } from '../../utils/situationReportHelpers'
import { statusColor } from '../../utils/statusPalette'
import { reportSections } from '../../utils/situationSections'

export default function SituationReferenceSections({ summary, included, collapsed, onCollapse, onToggle, actions, onActions, locked }) {
  return reportSections.map(([number, title], index) => {
    const Icon = [CloudRain,Users,HeartPulse,Route,House,Package,ClipboardList][index]
    const enabled = included.includes(number), open = !collapsed.includes(number)
    return <section className={`sx ${open ? 'open' : ''} ${enabled ? '' : 'off'}`} key={number}>
      <div className="reference-section-heading"><button type="button" className="sxh" aria-label={title} onClick={() => onCollapse(number)} aria-expanded={open} aria-controls={`sitrep-section-${number}`}><i aria-hidden="true">â€º</i><span className="section-visual"><Icon size={19} /></span><span className="t">{title}</span></button><label className="include-section"><input type="checkbox" checked={enabled} aria-label={`Include ${title}`} disabled={locked} onChange={() => onToggle(number)} /><span className="sr-only">Include in report</span></label></div>
      <div className="sxb" id={`sitrep-section-${number}`} hidden={!open}><div><SectionContent number={number} data={summary} actions={actions} onActions={onActions} locked={locked} /></div></div>
    </section>
  })
}
function SectionContent({ number, data, actions, onActions, locked }) {
  const weather = data.weather || {}, household = data.household || {}, casualties = data.casualties || {}, dispatch = data.dispatch || {}, damage = data.damage || {}
  switch (number) {
    case 'I': return <><VisualReadings items={[[CloudRain,'Condition',weather.condition],[Thermometer,'Temperature',weather.temperature],[Wind,'Wind',weather.wind],[Droplets,'Rainfall',weather.rainfall]]} /><details className="weather-source"><summary>Source &amp; advisory</summary><div><b>{displayValue(weather.source)}</b>{weather.advisory && <p>{weather.advisory}</p>}</div></details></>
    case 'II': return <><div className="population-totals"><span>Households in scope <b>{displayValue(household.total)}</b></span><span>Reported affected HH <b>{displayValue(household.affected)}</b></span><span>Unchecked HH <b>{displayValue(household.unchecked)}</b></span></div><p className="section-note">Affected means reported unsafe or evacuated/relocated. Safe households and unchecked households are not counted as confirmed affected. Person counts describe registered members of these households, not individually verified casualties.</p><ReportTable headings={['Sitio', 'HH in scope', 'Reported', 'Affected HH', 'Unchecked']} rows={(household.sitios || []).map(row => [row.sitio, row.total, row.reported, row.affected, row.unchecked])} /><ReportTable headings={['Sitio', 'Purok', 'HH in scope', 'Persons in scope', 'Affected HH', 'Persons in affected HH', 'Safe HH', 'Evacuated HH', 'Unsafe HH', 'Unchecked HH', 'Coverage']} rows={(household.puroks || []).map(row => [row.sitio, row.purok, row.total, row.persons ?? "Not recorded", row.affected, row.affected_persons ?? "Not recorded", row.safe, row.evacuated, row.unsafe, row.unchecked, row.coverage])} /><p className="section-note">Reporting coverage: {household.progress_text} ({household.progress_sub} households). Missing member counts are shown as not recorded. Prioritize unchecked areas for field verification.</p></>
    case 'III': return <ReportTable note="Dispatch outcome totals may include repeat visits; these are not a deduplicated casualty register. Deaths require separate verification." headings={['Item', 'Count']} numeric={[1]} rows={[[ 'Deaths', casualties.deaths ], [ 'Missing', casualties.missing ], [ 'Injured', casualties.injured ], [ 'Rescued', casualties.rescued ]]} />
    case 'V': return <><ReportTable headings={['Team', 'Deployed at', 'Area', 'HH reached', 'Outcomes']} rows={(dispatch.rows || []).map(row => [row.team, row.deployed_at, row.area, row.households_reached, row.outcomes])} empty="No dispatch rows yet." />{Boolean(dispatch.timeline?.length) && <ReportTable headings={['Time', 'Team dispatch, route, coverage, and field outcome summary.', 'Team', 'Area', 'Status']} rows={dispatch.timeline.map(row => [row.time, row.title, row.actor, row.detail, <Status key="status" value={row.status} tone={row.tone} />])} />}</>
    case 'VI': return <ReportTable headings={['Item', 'Houses']} numeric={[1]} rows={[[ 'Partially damaged houses', damage.partial ], [ 'Totally damaged houses', damage.total ]]} />
    case 'VII': return <ReportTable headings={['Item / request', 'Quantity', 'Status']} rows={(data.resources?.rows || []).map(row => [row.item, row.quantity, <Status key="status" value={row.status} tone={row.status_tone} />])} empty="No resource requests are linked to this event yet." />
    case 'VIII': return <textarea value={actions} onChange={event => onActions(event.target.value)} readOnly={locked} placeholder="Record observed hazards and affected locations; urgent food, water, medical and rescue needs; road access and utility interruptions; verified damage and evidence; actions completed with date/time and responsible team; pending requests, priorities, responsible office and next update." aria-label="Actions taken and recommendations" />
    default: return null
  }
}
function Status({ value, tone }) { return <span className="bg" style={{ '--c': statusColor(tone || value) }}>{displayValue(value)}</span> }
function ReportTable({ headings, rows, numeric = [], total, note, empty = 'No records found' }) {
  return <>{note && <p className="section-note">{note}</p>}<div className="tw"><table><thead><tr>{headings.map((heading, index) => <th key={heading} className={numeric.includes(index) ? 'n' : ''}>{heading}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={index}>{row.map((cell, column) => <td key={column} className={numeric.includes(column) ? 'n' : ''}>{displayValue(cell)}</td>)}</tr>) : <tr><td colSpan={headings.length} className="empty-row">{empty}</td></tr>}{total && <tr className="tot">{total.map((cell, column) => <td key={column} className={numeric.includes(column) ? 'n' : ''}>{displayValue(cell)}</td>)}</tr>}</tbody></table></div></>
}

function VisualReadings({ items }) {
  return <div className="visual-readings">{items.map(([Icon, label, value]) => <div key={label}><Icon size={19} /><span>{label}</span><b>{displayValue(value)}</b></div>)}</div>
}
