import PersonnelProfileLink from '../PersonnelProfileLink'
import { statusColor } from '../../utils/statusPalette'
import { useState } from 'react'
import Modal from '../ui/Modal'

function teamLabel(team) {
  const name = String(team.team_name || '').trim()
  const code = String(team.team_code || '').trim()
  if (!name) return code || 'Unnamed team'
  return code && code.toLowerCase() !== name.toLowerCase() ? `${code} · ${name}` : name
}

export default function DispatchSidePanel({ teams = [], responders = [] }) {
  const [open, setOpen] = useState(false)
  const [selected, setSelected] = useState(null)
  return <>
    <button className="cvt2" type="button" onClick={() => setOpen(true)}>Team Coverage</button>
    <Modal title="Team Coverage" isOpen={open} onClose={() => setOpen(false)} className="reference-drawer">
      <div className="cvl">
        {!teams.length && <div className="empty-reference">No team coverage yet</div>}
        {teams.map((team, index) => <div className={`cvr${selected === team.team_id ? ' on' : ''}`} key={team.team_id}>
          <button className="cvh" type="button" aria-expanded={selected === team.team_id} onClick={() => setSelected(selected === team.team_id ? null : team.team_id)}>
            <span className="rk" aria-hidden="true" style={{ background:['#3b82f6','#8b5cf6','#f59e0b','#0ea5e9'][index % 4], color:'#fff' }}>{(team.team_code || team.team_name || 'T').slice(0, 1)}</span><span className="coverage-team-name">{teamLabel(team)}</span>
            <span className="sc"><i style={{ width: `${Math.max(0, Math.min(100, Number(team.coverage_percent) || 0))}%`, background:'var(--nv)' }} /></span>
            <em>{team.assigned_households ? `${team.coverage_percent || 0}%` : '—'}</em><em className="mu">{team.reported_households || 0}/{team.assigned_households || 0}</em>
          </button>
          <div className="px"><div>{responders.filter((member) => String(member.team_id) === String(team.team_id)).map((member) => <span className="mc2" key={member.responder_id}><i style={{ background:statusColor(member.status?.key || (member.is_available ? 'available' : 'unavailable')) }} /><PersonnelProfileLink id={member.responder_id}>{member.full_name}</PersonnelProfileLink><em>{member.status?.label || 'Not recorded'}</em></span>)}</div></div>
        </div>)}
      </div>
    </Modal>
  </>
}
