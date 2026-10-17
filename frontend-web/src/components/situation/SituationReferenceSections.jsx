import { useState } from 'react'
import { displayValue } from '../../utils/situationReportHelpers'
import { reportSections } from '../../utils/situationSections'

export default function SituationReferenceSections({summary,included,collapsed,onCollapse,onToggle,actions,onActions,locked}) {
  return reportSections.map(([number,title]) => {
    const open=!collapsed.includes(number),enabled=included.includes(number)
    return <section key={number} id={`sitrep-section-${number}`} className={`sec ${open ? 'o' : ''} ${enabled ? '' : 'x'}`}><div className="sh"><button type="button" className="tg" onClick={() => onCollapse(number)} aria-expanded={open} aria-controls={`sitrep-body-${number}`}><span className="num">{number}</span>{title}{!enabled && <Chip tone="gray">Excluded</Chip>}<span className="chev" /></button><label className="inc"><input type="checkbox" checked={enabled} disabled={locked} onChange={() => onToggle(number)} aria-label={`Include ${title}`} /> Include</label></div>{open && <div className="body" id={`sitrep-body-${number}`}><Content number={number} data={summary} actions={actions} onActions={onActions} locked={locked} /></div>}</section>
  })
}
function Chip({tone,children}) { return <span className="chip"><i style={{background:`var(--${tone})`}} />{children}</span> }
function Metrics({items}) { return <div className="kp">{items.map(([label,value]) => <div className={`k ${value == null ? 'nr' : ''}`} key={label}><small>{label}</small><b>{displayValue(value,'Not recorded')}</b></div>)}</div> }
function Table({headings,rows,empty='No records found.'}) { return <div className="tw"><table><thead><tr>{headings.map(h => <th key={h}>{h}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row,i) => <tr key={i}>{row.map((value,j) => <td key={j}>{displayValue(value,'Not recorded')}</td>)}</tr>) : <tr><td colSpan={headings.length}><div className="empty">{empty}</div></td></tr>}</tbody></table></div> }
function Content({number,data,actions,onActions,locked}) {
  const w=data.weather || {},h=data.household || {},c=data.casualties || {},d=data.dispatch || {},damage=data.damage || {}
  switch(number) {
    case 'I': return <><Metrics items={[["Condition",w.condition],["Temperature",w.temperature],["Wind",w.wind],["Rainfall",w.rainfall]]} /><p className="note"><b>Source and advisory:</b> {displayValue(w.source)} {w.advisory}</p></>
    case 'II': return <><Population data={h} /><h3>Affected households</h3><HouseholdDetails rows={h.details || []} section="II" /></>
    case 'III': return <><Metrics items={[["Deaths",c.deaths],["Missing",c.missing],["Injured",c.injured],["Rescued",c.rescued]]} /><p className="note">Deaths require verification. Dispatch outcomes may include repeat visits.</p><h3>Affected households</h3><HouseholdDetails rows={h.details || []} section="III" /></>
    case 'V': return <><Table headings={['Team','Deployed','Area','Households reached','Outcome']} rows={(d.rows || []).map(r => [<b>{r.team}</b>,r.deployed_at,r.area,r.households_reached,<><Chip tone={r.status_tone || 'blue'}>{r.status}</Chip> {r.outcomes}</>])} empty="No dispatch rows for this event" />{!!d.timeline?.length && <ul className="tl">{d.timeline.map((r,i) => <li key={i}><b>{r.time}</b> {r.actor} · {r.detail} <Chip tone={r.tone || 'blue'}>{r.status}</Chip></li>)}</ul>}</>
    case 'VI': return <><Metrics items={[["Partially damaged houses",damage.partial],["Totally damaged houses",damage.total],["Households with damage",damage.partial != null && damage.total != null ? Number(damage.partial)+Number(damage.total) : null],["Not yet assessed",damage.unassessed]]} /><h3>Households with initial damage</h3><HouseholdDetails rows={h.details || []} section="VI" /></>
    case 'VII': return <Table headings={['Resource / request','Quantity','Status']} rows={(data.resources?.rows || []).map(r => [<b>{r.item}</b>,r.quantity,<Chip tone={r.status_tone || 'amber'}>{r.status}</Chip>])} empty="No event-linked requests yet" />
    case 'VIII': return <><div className="pr">{['Hazards and locations','Urgent needs','Access / utility interruptions','Verified damage + evidence','Actions: time + team','Pending: priority, office, next update'].map(text => <span key={text}>{text}</span>)}</div><textarea aria-label="Actions taken and recommendations" placeholder="Write observations, completed actions and recommendations…" value={actions} readOnly={locked} onChange={event => onActions(event.target.value)} /></>
    default: return null
  }
}
function Population({data:h}) {
  const [expanded,setExpanded]=useState({})
  const coverage=r => r.total ? `${Math.round((r.total-r.unchecked)/r.total*100)}%` : '0%'
  const cells=r => [r.total,r.affected,r.safe ?? r.safe_only,r.evacuated,r.unsafe,r.unchecked]
  return <><Metrics items={[["Total households",h.total],["Affected households",h.affected],["Unchecked households",h.unchecked],["Reporting coverage",h.progress_text]]} /><p style={{margin:'12px 0 0',display:'flex',gap:8,flexWrap:'wrap'}}>{[['green','Safe'],['blue','Evacuated'],['red','Unsafe'],['gray','Unchecked']].map(([tone,text]) => <Chip key={text} tone={tone}>{text}</Chip>)}</p><div className="tw"><table><thead><tr>{['Sitio','Households','Affected','Safe','Evacuated','Unsafe','Unchecked','Distribution','Coverage'].map((text,i) => <th key={text} className={i > 0 && i !== 7 ? 'n' : ''}>{text}</th>)}</tr></thead><tbody>{(h.sitios || []).map((r,i) => <PopulationRows key={r.sitio || i} row={r} expanded={expanded[i]} onToggle={() => setExpanded(current => ({...current,[i]:!current[i]}))} puroks={(h.puroks || []).filter(p => p.sitio === r.sitio)} cells={cells} coverage={coverage} />)}<tr className="tot"><td>Total</td>{cells(h).map((v,i) => <td className="n" key={i}>{displayValue(v)}</td>)}<td /><td className="n">{h.progress_text}</td></tr></tbody></table></div></>
}
function PopulationRows({row:r,expanded,onToggle,puroks,cells,coverage}) {
  return <><tr><td><button type="button" className="pb" aria-expanded={Boolean(expanded)} onClick={onToggle}>{expanded ? '▾' : '▸'} {r.sitio}</button></td>{cells(r).map((v,i) => <td className="n" key={i}>{displayValue(v)}</td>)}<td><div className="bar">{[['safe','green'],['evacuated','blue'],['unsafe','red'],['unchecked','gray']].map(([key,color]) => <span key={key} style={{width:`${r.total ? Number(r[key] || 0)/r.total*100 : 0}%`,background:`var(--${color})`}} />)}</div></td><td className="n">{coverage(r)}</td></tr>{expanded && puroks.map((p,i) => <tr className="sub" key={p.purok_id || i}><td>↳ {p.purok}</td>{cells(p).map((v,j) => <td className="n" key={j}>{displayValue(v)}</td>)}<td /><td className="n">{coverage(p)}</td></tr>)}</>
}
function HouseholdDetails({rows,section}) {
  const [expanded,setExpanded] = useState({})
  const location = h => `${h.sitio} · ${h.purok}`
  if(section === 'III') return <Table headings={['Household','Location','Affected members','Missing','Injured','Rescued','Immediate needs']} rows={rows.map(h => [h.household,location(h),h.affected_members,h.missing,h.injured,h.rescued,Array.isArray(h.immediate_needs) ? h.immediate_needs.join(', ') : h.immediate_needs])} empty="No affected households in this scope." />
  if(section === 'VI') return <Table headings={['Household','Location','Initial damage','Affected members']} rows={rows.filter(h => h.damage && !['no_damage','not_yet_assessed'].includes(h.damage)).map(h => [h.household,location(h),h.damage.replaceAll('_',' '),h.affected_members])} empty="No households with initial damage in this scope." />
  return <><p className="note">Unsafe households only. Affected member counts use recorded member assessments.</p><div className="tw"><table><thead><tr>{['Household','Location','Registered members','Affected members','Status'].map(t => <th key={t}>{t}</th>)}</tr></thead><tbody>{rows.length ? rows.map(h => <HouseholdRows key={h.household_id} household={h} expanded={expanded[h.household_id]} onToggle={() => setExpanded(current => ({...current,[h.household_id]:!current[h.household_id]}))} />) : <tr><td colSpan={5}><div className="empty">No affected households in this scope.</div></td></tr>}</tbody></table></div></>
}
function HouseholdRows({household:h,expanded,onToggle}) {
  return <><tr><td><button type="button" className="pb" aria-expanded={Boolean(expanded)} onClick={onToggle}>{expanded ? '▾' : '▸'} {h.household}</button></td><td>{h.sitio} · {h.purok}</td><td className="n">{h.registered_members}</td><td className="n">{displayValue(h.affected_members,'Not recorded')}</td><td><Chip tone="red">{h.status}</Chip></td></tr>{expanded && <tr className="sub"><td colSpan={5}>{h.members?.length ? h.members.map(m => `${m.name} — ${displayValue(m.age,'Age not recorded')}, ${displayValue(m.sex,'Sex not recorded')}, ${m.status}`).join(' · ') : 'No affected member assessments recorded.'}</td></tr>}</>
}
