import { useState } from 'react'
import api from '../../api/client'

export default function ArchiveRecording({ recording, recordId }) {
  const [failed, setFailed] = useState(false)
  if (!recording) return null
  const url = recording.available && recording.url?.startsWith('/storage/')
    ? new URL(recording.url, new URL(api.defaults.baseURL, window.location.href)).href
    : null

  return <div className="archive-recording">
    {url && !failed
      ? <audio key={url} controls preload="none" src={url} aria-label={`Recording for communication ${recordId}`} onError={() => setFailed(true)} />
      : <span role={failed ? 'status' : undefined}>{failed ? 'This recording could not be loaded. Its archive details are still available.' : recording.status || 'Recording unavailable'}</span>}
    {recording.duration_seconds != null && <small>{recording.duration_seconds} seconds · Communication {recordId}</small>}
  </div>
}
