import { CloudRain, Users, HeartPulse, MapPinHouse, Route, House, Package, ClipboardList, Thermometer, Wind, Droplets } from 'lucide-react'
import { displayValue } from '../../utils/situationReportHelpers'
import { statusColor } from '../../utils/statusPalette'
import { reportSections } from '../../utils/situationSections'

export default function SituationReferenceSections({ summary, included, collapsed, onCollapse, onToggle, actions, onActions, locked }) {
  return reportSections.map(([number, title], index) => {
    const Icon = [CloudRain,Users,HeartPulse,MapPinHouse,Route,House,Package,ClipboardList][index]
    const enabled = included.includes(number), open = !collapsed.includes(number)
    return <section className={`sx ${open ? 'open' : ''} ${enabled ? '' : 'off'}`} key={number}>
      <div className="reference-section-heading"><button type="button" className="sxh" aria-label={title} onClick={() => onCollapse(number)} aria-expanded={open} aria-controls={`sitrep-section-${number}`}><i aria-hidden="true">›</i><span className="section-visual"><Icon size={19} /></span><span className="t">{title}</span></button><label className="include-section"><input type="checkbox" checked={enabled} aria-label={`Include ${title}`} disabled={locked} onChange={() => onToggle(number)} /><span className="sr-only">Include in report</span></label></div>
      <div className="sxb" id={`sitrep-section-${number}`} hidden={!open}><div><SectionContent number={number} data={summary} actions={actions} onActions={onActions} locked={locked} /></div></div>
    </section>
  })
}
function SectionContent({ number, data, actions, onActions, locked }) {
  const weather = data.weather || {}, household = data.household || {}, casualties = data.casualties || {}, dispatch = data.dispatch || {}, damage = data.damage || {}
  switch (number) {
    case 'I': return <><VisualReadings items={[[CloudRain,'Condition',weather.condition],[Thermometer,'Temperature',weather.temperature],[Wind,'Wind',weather.wind],[Droplets,'Rainfall',weather.rainfall]]} /><details className="weather-source"><summary>Source &amp; advisory</summary><div><b>{displayValue(weather.source)}</b>{weather.advisory && <p>{weather.advisory}</p>}</div></details></>
    case 'II': return <><div className="population-totals" aria-label="Affected population summary"><span>Total HH affected <b>{displayValue(household.total)}</b></span><span>Safe total <b>{displayValue(household.safe_total)}</b></span></div><ReportTable headings={['Purok', 'Households in scope', 'Safe', 'Evacuated', 'Unsafe', 'Unchecked']} numeric={[1,2,3,4,5]} rows={(household.puroks || []).map(row => [row.purok, row.total, row.safe, row.evacuated, row.unsafe, row.unchecked])} total={['Total HH affected', household.total, household.safe_only, household.evacuated, household.unsafe, household.unchecked]} /></>
    case 'III': return <ReportTable headings={['Item', 'Count']} numeric={[1]} rows={[[ 'Deaths', casualties.deaths ], [ 'Missing', casualties.missing ], [ 'Injured', casualties.injured ], [ 'Rescued', casualties.rescued ]]} />
    case 'IV': return <ReportTable headings={['Site', 'Type', 'Capacity', 'Status']} rows={(data.evacuation || []).map(row => [row.name, row.type, <Status key="capacity" value={row.capacity_status} tone={row.capacity_tone} />, <Status key="status" value={row.status} />])} empty="No evacuation center records are linked to this event yet." />
    case 'V': return <><ReportTable headings={['Team', 'Deployed at', 'Area', 'HH reached', 'Outcomes']} rows={(dispatch.rows || []).map(row => [row.team, row.deployed_at, row.area, row.households_reached, row.outcomes])} empty="No dispatch rows yet." />{Boolean(dispatch.timeline?.length) && <ReportTable headings={['Time', 'Team dispatch, route, coverage, and field outcome summary.', 'Team', 'Area', 'Status']} rows={dispatch.timeline.map(row => [row.time, row.title, row.actor, row.detail, <Status key="status" value={row.status} tone={row.tone} />])} />}</>
    case 'VI': return <ReportTable headings={['Item', 'Houses']} numeric={[1]} rows={[[ 'Partially damaged houses', damage.partial ], [ 'Totally damaged houses', damage.total ]]} />
    case 'VII': return <ReportTable headings={['Item / request', 'Quantity', 'Status']} rows={(data.resources?.rows || []).map(row => [row.item, row.quantity, <Status key="status" value={row.status} tone={row.status_tone} />])} empty="No resource requests are linked to this event yet." />
    case 'VIII': return <textarea value={actions} onChange={event => onActions(event.target.value)} readOnly={locked} placeholder="Type actions taken and recommendations..." aria-label="Actions taken and recommendations" />
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
