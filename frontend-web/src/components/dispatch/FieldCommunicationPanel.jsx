import PersonnelProfileLink from '../PersonnelProfileLink'
import { useCallback, useEffect, useRef, useState } from 'react'
import { ArrowUpRight, Pause, Play, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { getFieldCommunications } from '../../api/dispatchApi'
import { useModuleData } from '../../utils/useModuleData'
import PaginationBar from '../ui/PaginationBar'
import DispatchStatusBadge from './DispatchStatusBadge'

let pollingSubscriptions = 0
let pollingTimer = null

const initials = (name) => String(name || 'Responder').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('')
const durationLabel = (seconds) => `${Math.floor((Number(seconds) || 0) / 60)}:${String(Math.floor((Number(seconds) || 0) % 60)).padStart(2, '0')}`

export default function FieldCommunicationPanel({ compact = false, recordingsOnly = false, onClose }) {
  const [team, setTeam] = useState('all')
  const channel = 'all'
  const search = ''
  const [page, setPage] = useState(1)
  const [player, setPlayer] = useState(null)
  const [playbackError, setPlaybackError] = useState('')
  const audioRef = useRef(null)
  const loader = useCallback(() => getFieldCommunications({ page, per_page: 20, search, channel,
    ...(recordingsOnly ? { recordings_only: 1 } : {}), ...(team === 'all' ? {} : { team_id: team }) }), [page, search, channel, team, recordingsOnly])
  const { data, loading, error, refresh } = useModuleData(loader, ['dispatch', 'communications', 'disasters'])
  useEffect(() => {
    pollingSubscriptions += 1
    if (!pollingTimer) pollingTimer = window.setInterval(() => {
      if (!document.hidden) window.dispatchEvent(new CustomEvent('operations:changed', { detail: { topics: ['communications'] } }))
    }, 5000)
    return () => { pollingSubscriptions -= 1; if (!pollingSubscriptions) { window.clearInterval(pollingTimer); pollingTimer = null } }
  }, [])
  useEffect(() => () => { audioRef.current?.pause() }, [])
  const stop = () => { audioRef.current?.pause(); setPlayer(null); setPlaybackError('') }
  const changeTeam = (value) => { stop(); setTeam(value); setPage(1) }
  async function play(log) {
    setPlaybackError('')
    let audio = audioRef.current
    if (audio?.dataset.recordingId !== String(log.id)) {
      audio?.pause()
      audio = new Audio(log.audio_url)
      audio.dataset.recordingId = String(log.id)
      audioRef.current = audio
      const update = () => {
        if (audioRef.current === audio) setPlayer({ log, playing: !audio.paused, current: audio.currentTime, duration: Number.isFinite(audio.duration) ? audio.duration : Number(log.duration_seconds) || 0, rate: audio.playbackRate })
      }
      audio.ontimeupdate = update
      audio.onloadedmetadata = update
      audio.onplay = update
      audio.onpause = update
      audio.onended = update
      audio.onerror = () => { if (audioRef.current === audio) { update(); setPlaybackError('This recording could not be played. Please try again.') } }
    } else if (!audio.paused) { audio.pause(); return }
    try { await audio.play() } catch { if (audioRef.current === audio) setPlaybackError('This recording could not be played. Please try again.') }
  }
  const logs = Array.isArray(data?.logs) ? data.logs : []
  const teams = Array.isArray(data?.teams) ? data.teams : []
  const voices = (log) => {
    const active = player?.log.id === log.id
    return <div className={`vm${active && player.playing ? ' on' : ''}`}>
      <button type="button" className="vp" disabled={!log.audio_url} aria-label={`${active && player.playing ? 'Pause' : 'Play'} recording from ${log.responder_name}`} onClick={() => play(log)}>{active && player.playing ? <Pause size={12} /> : <Play size={12} />}</button>
      <div className="vw" role="slider" tabIndex={0} aria-label={`Recording position for ${log.responder_name}`} aria-valuemin={0} aria-valuemax={100} aria-valuenow={active ? Math.round(player.current / (player.duration || 1) * 100) : 0} onKeyDown={(event) => { if (active && audioRef.current && ['ArrowLeft','ArrowRight'].includes(event.key)) { event.preventDefault(); audioRef.current.currentTime = Math.max(0, Math.min(player.duration, player.current + (event.key === 'ArrowRight' ? 5 : -5))) } }} onClick={(event) => { if (!active || !Number.isFinite(audioRef.current?.duration)) return; const box = event.currentTarget.getBoundingClientRect(); audioRef.current.currentTime = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width)) * audioRef.current.duration }}>
        {Array.from({ length: 30 }, (_, index) => <i key={index} className={active && index / 30 < player.current / (player.duration || 1) ? 'p' : ''} style={{ height: `${Math.round((Math.sin(index * 1.3 + Number(log.id)) * .5 + .5) * 70 + 22)}%` }} />)}
      </div><span className="vd">{durationLabel(active ? player.current : log.duration_seconds)}</span>
    </div>
  }
  const badge = (log) => {
    const responder = data?.responders?.find((item) => String(item.responder_id) === String(log.responder_id))
    return responder ? <DispatchStatusBadge status={responder.status} /> : null
  }
  const messages = loading ? <div className="empty-reference" role="status">Loading recordings…</div> : !logs.length && !error ? <div className="empty-reference">No recordings</div> : logs.map((log) => compact ?
    <div className="bub" key={log.id}><span className="av" style={{ '--c':['#3b82f6','#8b5cf6','#f59e0b','#0ea5e9'][Math.max(0, teams.findIndex((item) => String(item.team_id) === String(log.team_id))) % 4] }}>{initials(log.responder_name)}</span><div className="bb"><div className="bh"><b><PersonnelProfileLink id={log.responder_id}>{log.responder_name}</PersonnelProfileLink></b><span>{log.team_name} · {log.timestamp}</span><span className="sp" />{badge(log)}</div>{log.audio_url ? voices(log) : <span className="mu">No voice clip</span>}</div></div> :
    <div className="msg" key={log.id}><span className="av" style={{ '--c':['#3b82f6','#8b5cf6','#f59e0b','#0ea5e9'][Math.max(0, teams.findIndex((item) => String(item.team_id) === String(log.team_id))) % 4] }}>{initials(log.responder_name)}</span><div className="mb2"><div className="mt2"><b><PersonnelProfileLink id={log.responder_id}>{log.responder_name}</PersonnelProfileLink></b><span className="tg2">{log.team_name}</span><span className="tm">{log.timestamp}</span></div><div className="bb2">{log.audio_url ? voices(log) : <p>{log.message}</p>}{badge(log)}</div></div></div>)
  const pagination = Number(data?.meta?.last_page) > 1 ? <PaginationBar reference meta={data.meta} onPageChange={(value) => { stop(); setPage(value) }} label="communications" /> : null
  const problems = <>{error && <div className="form-error" role="alert">{error}<button className="bt" type="button" onClick={refresh}>Retry</button></div>}{playbackError && <div className="audio-error" role="alert">{playbackError}</div>}</>
  if (compact) return <div className="cd nv comm"><div className="ch">Field Communication<i className="lv" /><span className="sp" /><Link className="bt" style={{ height:30, padding:'0 12px' }} to="/dispatch/communication">All <ArrowUpRight size={13} /></Link></div><div className="tc"><button type="button" className={team === 'all' ? 'on' : ''} onClick={() => changeTeam('all')}>All</button>{teams.map((item) => <button key={item.team_id} type="button" className={team === String(item.team_id) ? 'on' : ''} onClick={() => changeTeam(String(item.team_id))}>{item.team_name}</button>)}</div><div className="cb">{problems}{messages}</div>{pagination}</div>
  return <><aside className="rl"><div className="rg">Teams</div><button className={`ri${team === 'all' ? ' on' : ''}`} type="button" onClick={() => changeTeam('all')}><span className="av s" style={{ '--c':'#64748b' }}>∗</span>All</button>{teams.map((item) => <button key={item.team_id} className={`ri${team === String(item.team_id) ? ' on' : ''}`} type="button" onClick={() => changeTeam(String(item.team_id))}><span className="av s">{String(item.team_name).slice(0, 1)}</span>{item.team_name}</button>)}</aside><section className="ct"><div className="cth"><span className="live"><i />Live · 5s</span><span className="sp" /><button className="x" type="button" aria-label="Close communications" onClick={onClose}><X size={16} /></button></div><div className="cf">{problems}{messages}</div>{pagination}<div className="npb" style={{ visibility: player ? 'visible' : 'hidden' }}><div><div className="nn">{player?.log.responder_name}</div><div className="nt2">{player?.log.team_name} · {durationLabel(player?.current)}</div></div><div className="pbar"><i className="pf" style={{ width: `${player ? player.current / (player.duration || 1) * 100 : 0}%` }} /></div><button type="button" disabled={!player} onClick={() => play(player.log)}>{player?.playing ? 'Pause' : 'Play'}</button><button type="button" disabled={!player} aria-label="Playback speed" onClick={() => { const rate = player.rate === 1 ? 1.5 : player.rate === 1.5 ? 2 : 1; audioRef.current.playbackRate = rate; setPlayer({ ...player, rate }) }}>{player?.rate || 1}×</button></div></section></>
}
