import { useState } from 'react'
import { recordSituationSubmission } from '../../api/situationReportApi'
import { situationErrorMessage } from '../../utils/situationReportHelpers'

export default function ManualSubmissionLog({ report, onRecorded }) {
  const [form,setForm] = useState({submitted_at:'',delivered_by:'',received_by:''})
  const [busy,setBusy] = useState(false), [error,setError] = useState('')
  async function save(event) {
    event.preventDefault()
    event.stopPropagation()
    setBusy(true); setError('')
    try {
      const result = await recordSituationSubmission(report.sit_rep_id,{...form,submitted_at:new Date(form.submitted_at).toISOString()})
      onRecorded({...report,submissions:[result.submission,...(report.submissions || [])]})
      setForm({submitted_at:'',delivered_by:'',received_by:''})
    } catch(failure) { setError(situationErrorMessage(failure,'Could not record the manual handoff.')) }
    finally { setBusy(false) }
  }
  // This component sits within the report form; avoid nesting a second HTML form.
  return <div className="card"><h3>Manual handoff note</h3><p>Record keeping only</p>{(report.submissions || []).map(entry => <p key={entry.id}><b>Handed over to CCDRRMO</b><br />{new Date(entry.submitted_at).toLocaleString('en-PH',{timeZone:'Asia/Manila'})}<br />Delivered by: {entry.delivered_by}<br />Received by: {entry.received_by}</p>)}<div>{[['submitted_at','Date and time delivered'],['delivered_by','Delivered by'],['received_by','Receiver at CCDRRMO']].map(([key,label]) => <div className="fld" key={key}><label htmlFor={`handoff-${key}`}>{label}</label><input id={`handoff-${key}`} className="in" type={key === 'submitted_at' ? 'datetime-local' : 'text'} value={form[key]} maxLength={key === 'submitted_at' ? undefined : 150} disabled={busy} onChange={e => setForm(current => ({...current,[key]:e.target.value}))} /></div>)}{error && <p role="alert">{error}</p>}<button type="button" className="btn p" disabled={busy || Object.values(form).some(v => !v.trim())} onClick={save}>{busy ? 'Recording…' : 'Record handoff'}</button></div></div>
}
