import { situationReportTitle } from '../../utils/situationReportHelpers'
import { situationPopulation } from '../../utils/situationPopulation'
import { reportSections, reportSectionNumbers, operatorSectionFields } from '../../utils/situationSections'

const value = v => v == null || v === '' ? 'Not recorded' : typeof v === 'boolean' ? (v ? 'Yes' : 'No') : String(v)
function Table({ headings, rows = [] }) {
  return <div className="tw"><table><thead><tr>{headings.map(h => <th key={h}>{h}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row,i) => <tr key={i}>{row.map((v,j) => <td key={j}>{value(v)}</td>)}</tr>) : <tr><td colSpan={headings.length}>Not recorded</td></tr>}</tbody></table></div>
}
export default function SituationReferenceSections({summary,included,collapsed,onCollapse,onToggle,locked,form,onField}) {
  return <><header style={{textAlign:'center',padding:16}}>Republic of the Philippines<br />City of Cebu · Barangay Mambaling<br /><b>Barangay Disaster Risk Reduction and Management Committee</b><br /><b>{situationReportTitle(summary,form)}</b></header>{reportSections.map(([key,title]) => {
    const open = !collapsed.includes(key)
    return <section key={key} id={`sitrep-section-${key}`} className={`sec ${open ? 'o' : ''} ${included.includes(key) ? '' : 'x'}`}><div className="sh"><button type="button" className="tg" onClick={() => onCollapse(key)} aria-expanded={open}><span className="num">{reportSectionNumbers[key]}</span>{title}<span className="chev" /></button><label className="inc"><input type="checkbox" checked={included.includes(key)} disabled={locked} onChange={() => onToggle(key)} aria-label={`Include ${title}`} /> Include</label></div>{open && <div className="body"><Content section={key} summary={summary} />{(operatorSectionFields[key] || []).map(([field,label]) => { const text=form?.[field] ?? summary.operator_inputs?.[field] ?? ''; return <label key={field}>{label}{locked ? <p style={{whiteSpace:'pre-wrap'}}>{value(text)}</p> : <textarea aria-label={label} maxLength={20000} value={text} placeholder="Not recorded" onChange={e => onField?.(field,e.target.value)} />}</label> })}</div>}</section>
  })}</>
}
function Content({section,summary:s}) {
  const population=situationPopulation(s), h=s.household || {}, w=s.weather || {}
  const damageDetails=(h.details || []).filter(r => ['minor_damage','major_damage','completely_destroyed','no_damage'].includes(r.damage))
  const damageRows=[['minor_damage','Minor damage'],['major_damage','Major damage'],['completely_destroyed','Completely destroyed']].map(([code,label]) => [label,damageDetails.length ? damageDetails.filter(r => r.damage === code).length : null])
  const areaRows=items => items.map(p => [p.sitio,p.purok,p.families,p.persons])
  switch(section) {
    case 'I': return <Table headings={['Finding','Recorded value']} rows={[["Event",s.event?.name],["Hazard",s.event?.type],["Alert level",s.event?.severity],["Area coverage",s.event?.coverage],["Weather",w.condition],["Advisory",w.advisory]]} />
    case 'II': return <Table headings={['Sitio','Purok','Families','Persons']} rows={areaRows(population.affected)} />
    case 'IV': return <><Table headings={['Recorded category','Recorded assessments']} rows={damageRows} /><Table headings={['Family reference','Sitio','Purok','Recorded damage']} rows={(h.details || []).filter(r => r.damage && !['no_damage','not_yet_assessed'].includes(r.damage)).map(r => [r.household,r.sitio,r.purok,r.damage.replaceAll('_',' ')])} /></>
    case 'V': return <><Table headings={['Team','Assigned area','Deployed at','Results']} rows={(s.dispatch?.rows || []).map(r => [r.team,r.area,r.deployed_at,r.outcomes])} /><Table headings={['Date/time','Team / responder','Assigned area','Status']} rows={(s.dispatch?.timeline || []).filter(r => r.title === 'Dispatch assignment').map(r => [r.time,r.actor,r.detail,r.status])} /></>
    case 'VI': return <Table headings={['Center','Status','Capacity (persons)','Occupancy (persons)','Vacancy (persons)']} rows={(s.evacuation?.sites || []).map(r => [r.name,r.status,r.capacity,r.occupancy,r.vacancy])} />
    case 'VII': return <><Table headings={['Resource','Available quantity','Unit','Status']} rows={(s.inventory?.rows || []).map(r => [r.item,r.quantity,r.unit,r.status])} /><h3>Resource requests</h3><Table headings={['Item','Quantity','Source','Status']} rows={(s.resources?.rows || []).map(r => [r.item,r.quantity,r.source,r.status])} /></>
    default: return null
  }
}
