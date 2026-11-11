import { useEffect, useState } from 'react'
import api from '../api/client'

export default function MyAssignmentsPage() {
  const [assignments, setAssignments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    api.get('/rescuer/assignments', { signal: controller.signal }).then(({ data }) => {
      setAssignments(data.data.assignments)
      setError('')
    }).catch(error => {
      if (!controller.signal.aborted) setError(error.response?.data?.message || 'Unable to load your assignments.')
    }).finally(() => {
      if (!controller.signal.aborted) setLoading(false)
    })
    return () => controller.abort()
  }, [revision])

  return <section>
    <button className="bt" disabled={loading} onClick={() => { setLoading(true); setRevision(value => value + 1) }}>Refresh assignments</button>
    {loading ? <p role="status">Loading assignments…</p> : error ? <p role="alert">{error}</p> : assignments.length === 0 ? <p>No assignments assigned to you.</p> : assignments.map(assignment => <article className="cd" key={assignment.assignment_id}>
      <h2>{assignment.assignment_code}</h2>
      <p>{assignment.team_name} · {assignment.status_label}</p>
      <p>{assignment.event_name} · {assignment.destination_label || assignment.assigned_area}</p>
      <p>Priority: {assignment.priority_level}</p>
      {assignment.dispatch_notes && <p>{assignment.dispatch_notes}</p>}
    </article>)}
  </section>
}
