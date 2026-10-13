import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useLocation, useNavigate } from 'react-router-dom'
import { createRescueTeam, createRescuer, deleteRescueTeam, deactivateRescuer, deleteRescuer, getRescueTeamConfig, getRescuer, getRescuers, updateRescueTeam, updateRescuer } from '../api/rescuerApi'
import { getHeadquartersAccounts } from '../api/headquartersAccountsApi'
import RescuerAccountModal, { AccountProfile } from '../components/rescuers/RescuerAccountModal'
import HeadquartersAccountsPanel from '../components/rescuers/HeadquartersAccountsPanel'
import RescuerRosterTable from '../components/rescuers/RescuerRosterTable'
import RescueTeamConfigModal from '../components/rescuers/RescueTeamConfigModal'
import { buildRescuerPayload, emptyRescuerForm, formFromRescuer, rescuerErrorMessage } from '../utils/rescuerHelpers'

export default function RescuerAccountsPage() {
  const location = useLocation()
  return <RescuerAccountsWorkspace key={`${location.pathname}${location.search}`} />
}

function RescuerAccountsWorkspace() {
  const location = useLocation()
  const navigate = useNavigate()
  const route = location.pathname.split('/').pop()
  const isRoster = route === 'rescuers'
  const isTeams = route === 'teams'
  const isNew = route === 'new'
  const params = new URLSearchParams(location.search)
  const requestedId = params.get('id') || location.state?.rescuer?.responder_id
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState(location.state?.notice || '')
  const [page, setPage] = useState(1)
  const [teamFilter, setTeamFilter] = useState('')
  const [selectedId, setSelectedId] = useState(requestedId || null)
  const [detail, setDetail] = useState(null)
  const [detailId, setDetailId] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [detailError, setDetailError] = useState('')
  const [detailVersion, setDetailVersion] = useState(0)
  const [form, setForm] = useState(() => emptyRescuerForm())
  const [original, setOriginal] = useState(null)
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const [teamConfig, setTeamConfig] = useState(null)
  const [teamVersion, setTeamVersion] = useState(0)
  const [teamLoading, setTeamLoading] = useState(isTeams)
  const [teamError, setTeamError] = useState('')
  const [category, setCategory] = useState(location.state?.category || 'responders')
  const [accountType, setAccountType] = useState(params.get('type') || 'rescuer')
  const [hq, setHq] = useState(null)
  const [confirmation, setConfirmation] = useState(null)
  const returnPath = location.state?.fromDispatchAssignment ? '/rescue-management' : '/rescuers'

  useEffect(() => {
    if (isTeams) return
    let ignore = false
    getRescuers({ page, per_page: 8, team: teamFilter }).then(data => {
      if (ignore) return
      setPayload(data)
      if (isNew) setForm(emptyRescuerForm(data.form_options?.defaults))
      if (isRoster) setSelectedId(current => data.rescuers?.data?.some(r => String(r.responder_id) === String(current)) ? current : data.rescuers?.data?.[0]?.responder_id || null)
    }).catch(loadError => { if (!ignore) setError(rescuerErrorMessage(loadError, 'Accounts could not be loaded. Check the backend connection.')) }).finally(() => { if (!ignore) setLoading(false) })
    return () => { ignore = true }
  }, [page, teamFilter, isNew, isRoster, isTeams])

  useEffect(() => {
    if (isTeams || isNew || !selectedId) return
    let ignore = false
    getRescuer(selectedId).then(data => {
      if (ignore) return
      const nextForm = formFromRescuer(data.rescuer)
      setDetail(nextForm); setDetailId(selectedId); setForm(nextForm); setOriginal(nextForm)
    }).catch(loadError => { if (!ignore) setDetailError(rescuerErrorMessage(loadError, 'The selected account could not be loaded.')) }).finally(() => { if (!ignore) setDetailLoading(false) })
    return () => { ignore = true }
  }, [selectedId, isTeams, isNew, detailVersion])

  useEffect(() => {
    if (!isNew && !isRoster) return
    let ignore = false
    getHeadquartersAccounts().then(data => { if (!ignore) setHq(data) }).catch(() => { /* The HQ panel handles its own loading errors. */ })
    return () => { ignore = true }
  }, [isNew, isRoster])

  useEffect(() => { if (isTeams) loadTeamConfig() }, [isTeams])

  async function loadTeamConfig() {
    setTeamLoading(true); setTeamError('')
    try { setTeamConfig(await getRescueTeamConfig()); setTeamVersion(current => current + 1) }
    catch (loadError) { setTeamError(rescuerErrorMessage(loadError, 'Teams could not be loaded.')) }
    finally { setTeamLoading(false) }
  }
  async function reloadRoster() {
    setLoading(true)
    try {
      const data = await getRescuers({ page, per_page: 8, team: teamFilter })
      setPayload(data)
      if (!data.rescuers?.data?.length && page > 1) setPage(page - 1)
      else setSelectedId(current => data.rescuers?.data?.some(r => String(r.responder_id) === String(current)) ? current : data.rescuers?.data?.[0]?.responder_id || null)
      setDetailVersion(current => current + 1)
    } catch (loadError) { setError(rescuerErrorMessage(loadError, 'Accounts could not be refreshed.')) }
    finally { setLoading(false) }
  }
  async function submitAccount(event) {
    event.preventDefault(); setSaving(true); setFormError('')
    try {
      const body = buildRescuerPayload(form, isNew ? 'create' : 'edit')
      const result = isNew ? await createRescuer(body) : await updateRescuer(selectedId, body)
      navigate('/rescuers', { state: { notice: result.message } })
    } catch (saveError) { setFormError(rescuerErrorMessage(saveError)) }
    finally { setSaving(false) }
  }
  async function saveTeam(body) {
    setSaving(true); setTeamError('')
    try {
      setTeamConfig(body.team_id ? await updateRescueTeam(body.team_id, body) : await createRescueTeam(body))
      setTeamVersion(current => current + 1); setNotice('Team saved.')
    } catch (saveError) { setTeamError(rescuerErrorMessage(saveError, 'Unable to save the team.')) }
    finally { setSaving(false) }
  }
  async function confirmAction() {
    if (!confirmation || saving) return
    setSaving(true); setError(''); setTeamError('')
    try {
      if (confirmation.type === 'team') {
        setTeamConfig(await deleteRescueTeam(confirmation.id)); setTeamVersion(current => current + 1)
      } else {
        if (confirmation.type === 'delete') await deleteRescuer(selectedId)
        else if (confirmation.type === 'deactivate') await deactivateRescuer(selectedId)
        else await updateRescuer(selectedId, buildRescuerPayload({ ...detail, account_status: 'active', duty_status: detail.duty_status === 'disabled' ? payload?.form_options?.defaults?.duty_status || 'available' : detail.duty_status, password: '' }, 'edit'))
        await reloadRoster()
      }
      setNotice(`${confirmation.title} completed.`); setConfirmation(null)
    } catch (actionError) {
      const message = rescuerErrorMessage(actionError, 'The action could not be completed.')
      if (confirmation.type === 'team') setTeamError(message); else setError(message)
      setConfirmation(null)
    } finally { setSaving(false) }
  }
  const captain = hq?.accounts?.find(account => account.account_type === 'barangay_captain')
  const detailPending = detailLoading || (selectedId && String(detailId) !== String(selectedId) && !detailError)
  function changePage(nextPage) { setLoading(true); setError(''); setDetailError(''); setPage(nextPage) }
  function selectRescuer(rescuer) {
    if (String(selectedId) === String(rescuer.responder_id)) return
    setDetailError(''); setSelectedId(rescuer.responder_id)
  }
  const roleSelector = <div className="rc" aria-label="Account type">{[['rescuer', 'Rescuer', 'R'], ['command_center', 'HQ Personnel', 'H'], ['barangay_captain', 'Captain', 'C']].map(([type, label, initial]) => <button key={type} className={accountType === type ? 'on' : ''} type="button" disabled={saving} aria-pressed={accountType === type} onClick={() => { setAccountType(type); setFormError('') }}><span className="av s" style={{ '--c': type === 'barangay_captain' ? '#7c3aed' : '#3b82f6' }}>{initial}</span>{label}{type === 'barangay_captain' && <em>{captain ? '1/1 · Edit' : '0/1'}</em>}</button>)}</div>
  const askAccount = type => setConfirmation({ type, title: type === 'delete' ? 'Delete user' : type === 'deactivate' ? 'Deactivate account' : 'Reactivate account', name: detail?.full_name || '', rows: type === 'delete' ? [['Login', 'Disabled'], ['Roster record', 'Removed'], ['Dispatch history', 'Kept']] : [['Login', type === 'deactivate' ? 'Disabled' : 'Enabled'], ['Record', 'Kept'], ['Dispatch history', 'Kept']] })

  return <main className="tm-page" aria-label={isTeams ? 'Team Management' : 'Account Management'}>
    <div className="pg on"><div className="hd">{isRoster ? <><div className="sg2 tm-category"><button type="button" className={category === 'responders' ? 'on' : ''} onClick={() => setCategory('responders')}>Rescuers</button><button type="button" className={category === 'headquarters' ? 'on' : ''} onClick={() => setCategory('headquarters')}>Headquarters</button></div><span className="sp" /><button className="bt" type="button" onClick={() => navigate('/rescuers/teams')}>Manage teams</button><button className="bt p pl2" type="button" onClick={() => navigate('/rescuers/new')}>Create account<i>+</i></button></> : <><span className="tm-page-title">{isTeams ? 'Team Management' : route === 'view' ? 'User details' : route === 'edit' ? 'Edit account' : 'Add new user'}</span><span className="sp" /><button className="bt" type="button" disabled={saving} onClick={() => navigate(returnPath)}>Back to {returnPath === '/rescue-management' ? 'rescue management' : 'accounts'}</button></>}</div>
      {error && <div className="tm-feedback" role="alert">{error}<button className="bt sm" type="button" onClick={reloadRoster}>Retry</button></div>}
      {notice && <div className="tm-feedback tm-success" role="status">{notice}<button className="x" aria-label="Dismiss notification" type="button" onClick={() => setNotice('')}>×</button></div>}
      {isTeams && <RescueTeamConfigModal key={teamVersion} embedded isOpen workspace={teamConfig} isLoading={teamLoading} isSaving={saving} error={teamError} onRetry={loadTeamConfig} onClose={() => navigate('/rescuers')} onSave={saveTeam} onDelete={(id, name) => setConfirmation({ type: 'team', id, title: 'Delete team', name, rows: [['Accounts', 'Kept'], ['Members', 'Moved to Unassigned'], ['Dispatch history', 'Kept']] })} />}
      {isRoster && category === 'headquarters' && <HeadquartersAccountsPanel onManageCaptain={() => navigate('/rescuers/new?type=barangay_captain')} />}
      {isRoster && category === 'responders' && <div className="acc">{detailPending ? <div className="cd emp" role="status">Loading user details…</div> : detailError ? <div className="cd emp" role="alert">{detailError}<button className="bt" onClick={() => { setDetailError(''); setDetailLoading(true); setDetailVersion(current => current + 1) }}>Retry</button></div> : detail && selectedId ? <AccountProfile key={selectedId} form={detail} onEdit={() => navigate(`/rescuers/edit?id=${selectedId}`)} onDeactivate={saving ? undefined : () => askAccount('deactivate')} onReactivate={saving ? undefined : () => askAccount('reactivate')} onDelete={saving ? undefined : () => askAccount('delete')} /> : <div className="cd emp" role="status">{loading ? 'Loading accounts…' : 'Select a rescuer to view their profile.'}</div>}<RescuerRosterTable rescuers={payload?.rescuers?.data || []} pagination={payload?.rescuers} onPageChange={changePage} selectedId={selectedId} onView={selectRescuer} teamOptions={payload?.team_options || []} teamFilter={teamFilter} onTeamFilter={value => { setLoading(true); setError(''); setDetailError(''); setTeamFilter(value); setPage(1) }} /></div>}
      {isNew && accountType !== 'rescuer' && <HeadquartersAccountsPanel key={accountType} editor onBusyChange={setSaving} initialType={accountType} roleSelector={roleSelector} onClose={() => navigate('/rescuers')} onSaved={message => navigate('/rescuers', { state: { notice: message, category: 'headquarters' } })} />}
      {((isNew && accountType === 'rescuer') || route === 'edit' || route === 'view') && <>{(loading && !payload) || detailPending ? <div className="cd emp" role="status">Loading account…</div> : (isNew ? payload?.form_options : detail) && <RescuerAccountModal embedded isOpen mode={isNew ? 'create' : route} form={form} setForm={setForm} formError={formError} isSaving={saving} teamOptions={payload?.team_options || []} accountIdOptions={payload?.account_id_options || []} fallbackAccountId={payload?.next_account_id || ''} formOptions={payload?.form_options} onClose={() => navigate(returnPath)} onReset={() => { setForm(isNew ? emptyRescuerForm(payload?.form_options?.defaults) : { ...original }); setFormError('') }} onSubmit={submitAccount} onEdit={() => navigate(`/rescuers/edit?id=${selectedId}`)} roleSelector={isNew ? roleSelector : undefined} />}{detailError && <div className="tm-feedback" role="alert">{detailError}</div>}{!isNew && !requestedId && <div className="cd emp">Select a user from the account list first.</div>}</>}
    </div>
    {confirmation && <ConfirmDialog action={confirmation} busy={saving} onCancel={() => { if (!saving) setConfirmation(null) }} onConfirm={confirmAction} />}
  </main>
}

function ConfirmDialog({ action, busy, onCancel, onConfirm }) {
  const cancel = useRef(null)
  const dialog = useRef(null)
  useEffect(() => {
    const previous = document.activeElement
    cancel.current?.focus()
    return () => previous?.focus()
  }, [])
  return createPortal(<div className="tm-page ov on" onClick={e => { if (e.target === e.currentTarget && !busy) onCancel() }} onKeyDown={e => {
    if (e.key === 'Escape' && !busy) onCancel()
    if (e.key === 'Tab') {
      const buttons = [...dialog.current.querySelectorAll('button:not(:disabled)')]
      const first = buttons[0], last = buttons[buttons.length - 1]
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus() }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus() }
    }
  }}><div className="mb" ref={dialog} role="dialog" aria-modal="true" aria-labelledby="tm-confirm-title"><h3 id="tm-confirm-title">{action.title}</h3><div>{action.name}</div><div>{action.rows.map(([key, value]) => <div className="kr" key={key}><span>{key}</span><b>{value}</b></div>)}</div><div className="tm-team-actions"><button ref={cancel} className="bt" type="button" disabled={busy} onClick={onCancel}>Cancel</button><button className={`bt ${action.type === 'delete' || action.type === 'team' ? 'd' : 'p nx'}`} type="button" disabled={busy} onClick={onConfirm}>{busy ? 'Saving…' : action.title}</button></div></div></div>, document.body)
}
