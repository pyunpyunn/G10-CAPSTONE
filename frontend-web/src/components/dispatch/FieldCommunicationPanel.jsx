import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { getFieldCommunications } from '../../api/dispatchApi'
import ModuleDataView from '../ui/ModuleDataView'
import { useModuleData } from '../../utils/useModuleData'
import PaginationBar from '../ui/PaginationBar'
import DataFilterBar from '../ui/DataFilterBar'
import LoadingState from '../ui/LoadingState'
import EmptyState from '../ui/EmptyState'

export default function FieldCommunicationPanel({ compact = false, recordingsOnly = false }) {
  return recordingsOnly ? <TeamRecordingsPanel /> : <CommunicationTable compact={compact} />
}

function CommunicationTable({ compact = false }) {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [channel, setChannel] = useState('all')
  const [team, setTeam] = useState('all')
  const loader = useCallback(() => getFieldCommunications({ page, per_page: compact ? 5 : 20, search, channel,
    ...(team !== 'all' ? { team_id: team } : {}) }), [page, compact, search, channel, team])
  const { data, error, loading, refresh } = useModuleData(loader)
  useEffect(() => {
    const interval = window.setInterval(refresh, 5000)
    return () => window.clearInterval(interval)
  }, [refresh])
  const change = (setter) => (value) => { setter(value); setPage(1) }
  return <section className="response-communication" aria-label="Field Communication Panel">
    {!compact && <DataFilterBar search={search} onSearchChange={change(setSearch)} searchPlaceholder="Search teams or messages..."
      filters={[{ id: 'channel', label: 'Channel', value: channel, onChange: change(setChannel), options: [{ value: 'all', label: 'All channels' }, ...(data?.channels || []).map((item) => ({ value: item.key, label: item.label }))] },
        { id: 'team', label: 'Team', value: team, onChange: change(setTeam), options: [{ value: 'all', label: 'All teams' }, ...(data?.teams || []).map((item) => ({ value: String(item.team_id), label: item.team_name }))] }]}
      onReset={() => { setSearch(''); setChannel('all'); setTeam('all'); setPage(1) }} />}
    <ModuleDataView title="Field Communication" rows={data?.logs || []} loading={loading} error={error}
      actions={<div className="response-panel-actions"><button className="btn btn-secondary btn-sm" type="button" onClick={refresh}>Refresh</button>
        {compact && <Link className="btn btn-secondary btn-sm" to="/dispatch/communication">View all communications</Link>}</div>}
      columns={[{ key: 'timestamp', label: 'Date / time' }, { key: 'responder_name', label: 'Responder' }, { key: 'team_name', label: 'Team' },
        { key: 'channel_label', label: 'Channel' }, { key: 'message', label: 'Communication' }, { key: 'type_label', label: 'Status' },
        { key: 'audio', label: 'Voice clip', render: (row) => row.audio_url ? <audio controls preload="none" src={row.audio_url} aria-label={`Voice clip from ${row.responder_name}`} /> : 'No voice clip' }]} />
    {!compact && <PaginationBar meta={data?.meta || {}} onPageChange={setPage} label="communications" />}
  </section>
}

function TeamRecordingsPanel() {
  const [team, setTeam] = useState('all')
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  const [playingId, setPlayingId] = useState(null)
  const [playbackError, setPlaybackError] = useState('')
  const audioRef = useRef(null)
  const requestKey = `${team}:${page}`
  const current = result?.key === requestKey
  const data = current ? result.data : null
  const error = current ? result.error : ''

  useEffect(() => {
    let disposed = false
    let pending = false
    const update = async () => {
      if (pending || document.hidden) return
      pending = true
      try {
        const value = await getFieldCommunications({ recordings_only: 1, per_page: 20, page,
          ...(team === 'all' ? {} : { team_id: team }) })
        if (!disposed) setResult({ key: requestKey, data: value, error: '' })
      } catch {
        if (!disposed) setResult((previous) => ({ key: requestKey,
          data: previous?.key === requestKey ? previous.data : null,
          error: 'Recordings could not update. Retrying automatically.' }))
      } finally { pending = false }
    }
    update()
    const interval = window.setInterval(update, 5000)
    document.addEventListener('visibilitychange', update)
    return () => { disposed = true; window.clearInterval(interval); document.removeEventListener('visibilitychange', update) }
  }, [team, page, requestKey])

  useEffect(() => () => { audioRef.current?.pause() }, [])

  const play = async (event) => {
    const log = { id: event.currentTarget.dataset.recordingId, audio_url: event.currentTarget.dataset.audioUrl }
    setPlaybackError('')
    if (audioRef.current?.dataset.recordingId === String(log.id)) {
      if (!audioRef.current.paused) { audioRef.current.pause(); setPlayingId(null); return }
    } else {
      audioRef.current?.pause()
      const audio = new Audio(log.audio_url)
      audio.dataset.recordingId = String(log.id)
      audio.onended = () => setPlayingId(null)
      audio.onerror = () => { setPlayingId(null); setPlaybackError('This recording could not be played. Please try again.') }
      audioRef.current = audio
    }
    const audio = audioRef.current
    setPlayingId(String(log.id))
    try { await audio.play() } catch {
      if (audioRef.current === audio) { setPlayingId(null); setPlaybackError('This recording could not be played. Please try again.') }
    }
  }
  const changeTeam = (value) => {
    audioRef.current?.pause(); setPlayingId(null); setPlaybackError(''); setTeam(value); setPage(1)
  }
  const groups = new Map()
  for (const log of data?.logs || []) {
    if (!log.audio_url) continue
    const key = log.team_id ?? log.team_name ?? 'unassigned'
    if (!groups.has(key)) groups.set(key, { name: log.team_name || 'Unassigned team', logs: [] })
    groups.get(key).logs.push(log)
  }
  const shortName = (name = 'Responder') => {
    const parts = name.trim().split(/\s+/)
    return parts.length > 1 ? `${parts[0]} ${parts.at(-1)[0]}.` : parts[0]
  }
  const initials = (name = 'Responder') => name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('')
  return <section className="dp-side-card response-communication response-recordings" aria-label="Field Communication Panel">
    <div className="dp-side-head"><div><strong className="dp-side-title">Field Communication</strong>
      <span className="recordings-subtitle">Team radio · PTT recordings</span></div>
      <span className={`recordings-live${error ? ' is-delayed' : ''}`}><i />{error ? 'Reconnecting' : 'Auto-updates'}</span></div>
    <div className="recordings-workspace">
      <nav className="recordings-channels" aria-label="Recording teams">
        <span className="recordings-channel-heading">TEAMS</span>
        <button type="button" className={team === 'all' ? 'is-active' : ''} aria-pressed={team === 'all'} onClick={() => changeTeam('all')}># All teams</button>
        {(data?.teams || result?.data?.teams || []).map((item) => <button key={item.team_id} type="button"
          className={team === String(item.team_id) ? 'is-active' : ''} aria-pressed={team === String(item.team_id)}
          onClick={() => changeTeam(String(item.team_id))}># {item.team_name}</button>)}
      </nav>
      <div className="recordings-feed">
        <p className="recordings-hint">Select a rescuer avatar to listen. Select it again to pause.</p>
        {error && <div className="form-error" role="status">{error}</div>}
        {playbackError && <div className="form-error" role="alert">{playbackError}</div>}
        {!current ? <LoadingState /> : !groups.size && !error ? <EmptyState title="No recordings yet" message="Rescuer voice recordings will appear automatically here." /> :
          [...groups].map(([key, group]) => <section className="dispatch-recording-team" key={key}>
            <h3><span>#</span> {group.name} <small>{group.logs.length} recordings</small></h3>
            <div className="recordings-avatar-grid">{group.logs.map((log) => <button type="button"
              className={`recording-profile${playingId === String(log.id) ? ' is-playing' : ''}`} key={log.id}
              aria-pressed={playingId === String(log.id)} aria-label={`${playingId === String(log.id) ? 'Pause' : 'Play'} recording from ${log.responder_name}, ${group.name}, ${log.timestamp}`}
              data-recording-id={log.id} data-audio-url={log.audio_url} onClick={play}>
              <span className="recording-avatar" aria-hidden="true">{initials(log.responder_name)}<span className="recording-play-icon">{playingId === String(log.id) ? 'Ⅱ' : '▶'}</span></span>
              <strong>{shortName(log.responder_name)} <span>({group.name})</span></strong>
              <small>{log.timestamp}</small>
              <span className="recording-duration">{playingId === String(log.id) ? 'Playing' : `${log.duration_seconds || 0}s voice clip`}</span>
            </button>)}</div>
          </section>)}
        {Number(data?.meta?.last_page) > 1 && <PaginationBar meta={data.meta} onPageChange={(value) => {
          audioRef.current?.pause(); setPlayingId(null); setPage(value)
        }} label="recordings" />}
      </div>
    </div>
  </section>
}
