import { useEffect, useRef, useState } from 'react'
import { StatusDot } from './RescuerAccountModal'

export default function RescuerTeamGrid({ teams = [], selectedId, onSelect }) {
  const track = useRef(null)
  const [position, setPosition] = useState({ start: true, end: false })
  function measure() { const el = track.current; if (el) setPosition({ start: el.scrollLeft < 4, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 }) }
  useEffect(() => {
    const el = track.current
    if (!el) return
    const observer = new ResizeObserver(() => {
      setPosition({ start: el.scrollLeft < 4, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 })
    })
    observer.observe(el)
    return () => observer.disconnect()
  }, [teams.length])
  if (!teams.length) return <div className="cd emp">No teams yet. Add a team to organize your responders.</div>
  return <div className="car" aria-label="Team Status Cards"><button className="cb2 l" aria-label="Previous team cards" disabled={position.start} onClick={() => track.current?.scrollBy({ left: -track.current.clientWidth * .8, behavior: 'smooth' })}>‹</button><div className="tcards" ref={track} onScroll={measure}>{teams.map(team => <button type="button" className={`tcd${String(selectedId) === String(team.team_id) ? ' on' : ''}`} key={team.team_id || team.team_code} aria-pressed={String(selectedId) === String(team.team_id)} onClick={() => onSelect?.(team)}><span className="rg" style={{ '--p': team.member_count ? Math.min(100, Math.round((team.deployed_count || 0) / team.member_count * 100)) : 0 }}><b>{team.deployed_count || 0}</b></span><div className="t1"><span>{team.team_name}</span><em>{team.team_code}</em></div><StatusDot muted={/off|disabled/i.test(team.duty_status || '')}>{team.duty_status_display?.label || team.duty_status || 'Not recorded'}</StatusDot><div className="t2"><span>Members<b>{team.member_count || 0}</b></span><span>Assignments<b>{team.active_dispatch_count || 0}</b></span><span>Deployed<b>{team.deployed_count || 0}</b></span></div></button>)}</div><button className="cb2 r" aria-label="Next team cards" disabled={position.end} onClick={() => track.current?.scrollBy({ left: track.current.clientWidth * .8, behavior: 'smooth' })}>›</button></div>
}
