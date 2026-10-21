import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { annexHTeamCode, annexHTeamName, roleAccountsForCommittee, teamsForCommittee } from '../utils/annexHTeamHierarchy'
import '../styles/annex-chart.css'
const cs=[166,304,442,580,718,856,994],C='Sub-committee';
const layout=[
{id:'root',tag:'Level 1 · Chair',label:'BDRRMC Chairperson',x:360,y:0,w:440,h:64,k:'root'},
{id:'ops',tag:'',label:'Operations / Admin',x:390,y:96,w:170,h:48,k:'ops',p:'root'},
{id:'mit',tag:C,label:'Mitigation and Prevention',x:380,y:170,w:180,h:56,k:'com',p:'root'},
{id:'pre',tag:C,label:'Preparedness',x:600,y:170,w:180,h:56,k:'com',p:'root'},
{id:'rec',tag:C,label:'Recovery and Rehabilitation',x:200,y:301,w:190,h:56,k:'com',p:'root'},
{id:'res',tag:C,label:'Response',x:640,y:301,w:190,h:56,k:'com',p:'root'},
{id:'liv',tag:'Team',label:'Livelihood',x:10,y:249,w:140,h:48,p:'rec'},
{id:'inf',tag:'Team',label:'Infra / Shelter',x:10,y:305,w:140,h:48,p:'rec'},
{id:'pda',tag:'Team',label:'PDANA',x:10,y:361,w:140,h:48,p:'rec'},
{id:'rda',tag:'Team',label:'RDANA',x:900,y:18,w:220,h:48,p:'res'},
{id:'sar',tag:'Team',label:'Search, Rescue and Retrieval',x:900,y:80,w:220,h:48,p:'res'},
{id:'evc',tag:'Team',label:'Evacuation and Camp Management',x:900,y:142,w:220,h:48,p:'res'},
{id:'rel',tag:'Team',label:'Relief Distribution',x:900,y:204,w:220,h:48,p:'res'},
{id:'hea',tag:'Team',label:'Health, First Aid and Psychosocial Support',x:900,y:266,w:220,h:48,p:'res'},
{id:'fir',tag:'Team',label:'Fire Management',x:900,y:328,w:220,h:48,p:'res'}];
['Research and Planning','Transportation','Communication and Warning','Security and Safety','Education','Protection','Damage Control'].forEach(function(l,i){layout.push({id:'u'+i,tag:'Unclassified',label:l,x:cs[i]-62,y:500,w:124,h:64,k:'unc',p:'root'})});
const S=[[580,64,580,470,'root'],[560,120,580,120,'ops'],[560,198,580,198,'mit'],[580,198,600,198,'pre'],[390,329,580,329,'rec'],[580,329,640,329,'res'],[150,329,200,329,'rec'],[170,273,170,385,'liv inf pda'],[150,273,170,273,'liv'],[150,329,170,329,'inf'],[150,385,170,385,'pda'],[830,329,880,329,'res'],[880,42,880,352,'rda sar evc rel hea fir'],[166,470,994,470,'u0 u1 u2 u3 u4 u5 u6']];
['rda','sar','evc','rel','hea','fir'].forEach(function(id,i){S.push([880,42+i*62,900,42+i*62,id])});
cs.forEach(function(c,i){S.push([c,470,c,500,'u'+i])});

const codes={liv:'LHT',inf:'ISH',pda:'PDANA',rda:'RDANA',sar:'SRR',evc:'ECM',rel:'RDT',hea:'HFAPS',fir:'FMT',u0:'RPT',u1:'TRT',u2:'CWT',u3:'SST'}
const committeeKeys={mit:'mitigation_prevention',pre:'preparedness',rec:'recovery_rehabilitation',res:'response'}
const initials=name=>name.split(/\s+/).map(s=>s[0]).join('').slice(0,2)
export default function AnnexHierarchyChart({workspace,onAccounts,onTeams}) {
 const [params]=useSearchParams()
 const [selection,setSelection]=useState(()=>params.get('team_id')?'record:'+params.get('team_id'):'sar'),[query,setQuery]=useState(''),[closed,setClosed]=useState(false),[wide,setWide]=useState(false)
 const teams=workspace.teams||[], responders=workspace.responders||[]
 const nodes=layout.map(n=>({...n,teams:teams.filter(t=>annexHTeamCode(t)===codes[n.id])}))
 const used=new Set(nodes.flatMap(n=>n.teams.map(t=>String(t.team_id))))
 const extra=teams.filter(t=>t.team_code!=='HQCC'&&!used.has(String(t.team_id)))
 extra.forEach((t,i)=>nodes.push({id:'team-'+t.team_id,label:annexHTeamName(t),tag:'Team',k:'unc',p:'root',x:10+(i%5)*226,y:610+Math.floor(i/5)*76,w:210,h:60,teams:[t]}))
 const normalize=value=>String(value||'').toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim()
 const tokens=normalize(query).split(/\s+/).filter(Boolean)
 const matches=values=>tokens.every(token=>normalize(values.join(' ')).includes(token))
 const matchingPeople=tokens.length?responders.filter(r=>matches([r.full_name,r.username,r.responder_code,r.role_name,r.title])):[]
 const matchingNodes=tokens.length?nodes.filter(n=>matches([n.label,n.tag,codes[n.id],...n.teams.flatMap(t=>[t.team_name,t.team_code,t.team_type,annexHTeamName(t)])])):[]
 const matchingNodeIds=new Set([...matchingNodes.map(n=>n.id),...nodes.filter(n=>n.teams.some(t=>matchingPeople.some(r=>String(r.team_id)===String(t.team_id)))||matchingPeople.some(r=>r.role_key===(committeeKeys[n.id]||({root:'chair',ops:'operations_admin'})[n.id]))).map(n=>n.id)])
 const selected=nodes.find(n=>n.id===selection||n.teams.some(t=>'record:'+t.team_id===selection))||nodes[0]
 const chain=[]; let current=selected
 while(current){chain.unshift(current);current=nodes.find(n=>n.id===current.p)}
 const branch=committeeKeys[selected.id]
 const roleKeys=selected.id==='root'?['chair']:selected.id==='ops'?['operations_admin']:branch?[branch]:[]
 const accounts=roleAccountsForCommittee(responders,roleKeys)
 const branchTeams=branch?teamsForCommittee(teams,branch):selected.teams
 const children=nodes.filter(n=>n.p===selected.id)
 const height=extra.length?610+Math.ceil(extra.length/5)*76:580
 const choose=id=>{setSelection(id);setClosed(false)}
 const person=(name,role,key)=> <div className="person" key={key||name}><div className={'av'+(!name?' none':'')}>{name?initials(name):'–'}</div><div>{name||'Not assigned'}</div>{role&&<span className={'tag'+(role==='Leader'?' lead':'')}>{role}</span>}</div>
 const fact=(label,value)=><div className="row" key={label}><span>{label}</span><span>{value}</span></div>
 return <div className="annex-chart-page"><div className="wrap">
 <header className="top"><h1>BDRRMC Organizational Structure (Annex H)</h1><div style={{display:'flex',gap:8,flexWrap:'wrap'}}><div className="seg" role="group" aria-label="View"><button type="button" onClick={onTeams}>Team management</button><button type="button" aria-current="page">Annex H hierarchy</button></div><button className="btn" onClick={onAccounts}>Back to accounts</button></div></header>
 <div className="bar"><input className="search" type="search" placeholder="Find a person, team or committee…" aria-label="Find a person, team or committee" value={query} onChange={e=>setQuery(e.target.value)}/></div>
 {tokens.length>0&&<section className="hierarchy-search-results" aria-label="Search results"><div role="status">{matchingPeople.length+matchingNodes.length} result{matchingPeople.length+matchingNodes.length===1?'':'s'} for “{query.trim()}”</div>{matchingPeople.map(r=><button type="button" className="link" key={'person-'+r.responder_id} onClick={()=>onAccounts(r)}><span><b>{r.full_name}</b><small>{r.role_name||r.title||'Personnel'}</small></span><span>View profile ›</span></button>)}{matchingNodes.map(n=><button type="button" className="link" key={n.id} onClick={()=>choose(n.id)}><span><b>{n.label}</b><small>{n.teams.map(t=>t.team_code).join(', ')||n.tag}</small></span><span>View details ›</span></button>)}{!matchingPeople.length&&!matchingNodes.length&&<p className="note">No matching person, team or committee.</p>}</section>}
 <div className={'work'+(closed?' closed':'')+(wide?' wide':'')}>
 <div className="canvaswrap"><div className="chead"><strong style={{fontSize:13}}>Organizational chart</strong><div className="legend"><span><i style={{background:'var(--navy)'}}/>Chairperson</span><span><i style={{background:'var(--blue)'}}/>Sub-committee</span><span><i style={{border:'1px solid var(--border2)',background:'var(--surface)'}}/>Team</span><span><i style={{border:'1px dashed var(--amber)'}}/>Technical role or unclassified</span></div></div>
 <div className="notice">{extra.length?`${extra.length} additional team${extra.length===1?'':'s'} shown below the reference structure. `:''}Select a team or committee to view its recorded leaders and roster.</div>
 <div className="scroll" tabIndex={0} aria-label="Scrollable chart area"><div className="chart" style={{height}}><svg width="1140" height={height} aria-hidden="true">{S.map((s,i)=><line key={i} x1={s[0]} y1={s[1]} x2={s[2]} y2={s[3]} stroke={s[4].split(' ').some(id=>chain.some(n=>n.id===id))?'var(--ring)':'var(--line)'} strokeWidth={s[4].split(' ').some(id=>chain.some(n=>n.id===id))?3:2}/>)}{extra.map((t,i)=><path key={t.team_id} d={`M580 470 V590 H${115+(i%5)*226} V${610+Math.floor(i/5)*76}`} fill="none" stroke="var(--line)" strokeWidth="2"/>)}</svg>
 {nodes.map(n=><button type="button" key={n.id} className={'nd '+(n.k||'')+(selected.id===n.id?' on':'')+(!tokens.length||matchingNodeIds.has(n.id)?'':' dim')} style={{left:n.x,top:n.y,width:n.w,height:n.h}} onClick={()=>choose(n.id)} aria-pressed={selected.id===n.id}><small>{n.tag}{selected.id===n.id?' · Selected':''}</small><span>{n.label}</span></button>)}</div></div></div>
 {closed && <button type="button" className="panel-tab" aria-label="Expand details panel" aria-expanded={false} aria-controls="annex-details" onClick={()=>setClosed(false)}>Panel</button>}
 <aside className="panel" hidden={closed} aria-label="Selected details" aria-live="polite"><div className="phead"><div className="ptitle"><div className="t">{selected.tag}</div><h2>{selected.label}</h2></div><div style={{display:'flex',gap:6}}><button className="ib" id="wide" aria-label="Widen panel" onClick={()=>{setWide(!wide);setClosed(false)}}>↔</button><button className="ib" id="close" aria-label={closed?'Expand details panel':'Collapse details panel'} aria-expanded={!closed} aria-controls="annex-details" onClick={()=>setClosed(!closed)}><span className="ch">»</span></button></div></div>
 <div className="pbody" id="annex-details" inert={closed}><div className="crumb">{chain.map(n=>n.label).join(' › ')}</div>
 {roleKeys.length>0&&<><div>{fact('Level',selected.id==='root'?'1 · Root':selected.id==='ops'?'Technical / administrative':'2 · Sub-committee')}{branch&&fact('Teams',branchTeams.length)}</div><div><h3>Role accounts ({accounts.length})</h3>{accounts.length?accounts.map(a=>person(a.full_name,a.role_name||'Role account',a.responder_id)):person(null)}</div></>}
 {!roleKeys.length&&selected.teams.length===0&&<div className="note">No team record or individual assignment is configured for this function.</div>}
 {branchTeams.map(t=>{const members=responders.filter(r=>String(r.team_id)===String(t.team_id));return <section key={t.team_id}><h3>{annexHTeamName(t)}</h3><div>{fact('Code',t.team_code)}{fact('Committee',t.team_type||'Not classified')}{fact('Duty status',t.duty_status_display?.label||t.duty_status||'Not recorded')}{fact('Assigned area',t.assigned_purok||'Not assigned')}{fact('Assignments',t.active_dispatch_count??0)}</div><h3>Leader</h3>{person(t.leader_name,'Leader')}<h3>Members ({members.length})</h3>{members.length?members.map(m=>person(m.full_name,m.membership_status?.label||m.title||'Member',m.responder_id)):<div className="note">No members assigned.</div>}</section>})}
 {children.length>0&&<div><h3>Teams in this branch</h3>{children.map(n=><button className="link" key={n.id} onClick={()=>choose(n.id)}><span>{n.label}</span><span aria-hidden="true">›</span></button>)}</div>}
 {roleKeys.length>0&&<button className="btn pri" onClick={onAccounts}>Manage role accounts</button>}
 </div></aside></div></div></div>
}
