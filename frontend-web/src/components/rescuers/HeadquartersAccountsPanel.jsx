import { useEffect, useState } from 'react'
import { getHeadquartersAccounts, saveHeadquartersAccount } from '../../api/headquartersAccountsApi'
import { AccountProfile } from './RescuerAccountModal'

const emptyForm = type => ({ account_type: type, first_name: '', last_name: '', email: '', contact_number: '', password: '' })

export default function HeadquartersAccountsPanel({ editor = false, initialType = 'command_center', roleSelector, onClose, onSaved, onManageCaptain, onBusyChange }) {
  const [workspace, setWorkspace] = useState(null)
  const [form, setForm] = useState(() => emptyForm(initialType))
  const [selectedId, setSelectedId] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [section, setSection] = useState('personal')
  const captain = workspace?.accounts?.find(account => account.account_type === 'barangay_captain')
  const selected = workspace?.accounts?.find(account => account.account_id === selectedId) || workspace?.accounts?.[0]
  useEffect(() => {
    let ignore = false
    getHeadquartersAccounts().then(data => {
      if (ignore) return
      setWorkspace(data)
      const existing = data.accounts?.find(account => account.account_type === 'barangay_captain')
      if (initialType === 'barangay_captain' && existing) setForm({ ...emptyForm(initialType), first_name: existing.first_name || '', last_name: existing.last_name || '', email: existing.email || '', contact_number: existing.contact_number || '' })
    }).catch(() => { if (!ignore) setError('Headquarters accounts could not be loaded. Check the backend connection.') }).finally(() => { if (!ignore) setIsLoading(false) })
    return () => { ignore = true }
  }, [initialType])
  function reset() { setForm(initialType === 'barangay_captain' && captain ? { ...emptyForm(initialType), first_name: captain.first_name || '', last_name: captain.last_name || '', email: captain.email || '', contact_number: captain.contact_number || '' } : emptyForm(initialType)); setError('') }
  async function submit(event) {
    event.preventDefault(); setIsSaving(true); onBusyChange?.(true); setError(''); setNotice('')
    try {
      const result = await saveHeadquartersAccount(form)
      if (onSaved) onSaved(result.message)
      else { setNotice(result.message); setWorkspace(await getHeadquartersAccounts()); setForm(current => ({ ...current, password: '' })) }
    } catch (saveError) { setError(saveError.response?.data?.errors ? Object.values(saveError.response.data.errors).flat().join(' ') : saveError.response?.data?.message || 'The account could not be saved.') }
    finally { setIsSaving(false); onBusyChange?.(false) }
  }
  const field = (key, label, type = 'text', span = 's3') => <label className={`fl ${span}`}><span>{label}</span><input type={type} required={key === 'first_name' || key === 'last_name'} maxLength={key === 'email' ? 255 : key === 'contact_number' ? 50 : 100} value={form[key]} onChange={e => setForm(current => ({ ...current, [key]: e.target.value }))} /></label>
  if (!editor) return <>{error && <div className="tm-feedback" role="alert">{error}</div>}{isLoading ? <div className="cd emp" role="status">Loading headquarters accounts…</div> : <div className="acc">{selected ? <AccountProfile key={selected.account_id} form={{ ...selected, full_name: selected.name, title: selected.account_type_label }} role={selected.account_type === 'barangay_captain' ? 'Captain' : 'HQ Personnel'} onEdit={selected.account_type === 'barangay_captain' ? onManageCaptain : undefined} /> : <div className="cd emp">No headquarters accounts found</div>}<aside className="cd tm-account-list"><div className="ch">Headquarters<em className="mu">{workspace?.accounts?.length || 0}</em></div><div className="cb">{(workspace?.accounts || []).map(account => <button className={`nr${selected?.account_id === account.account_id ? ' on' : ''}`} key={account.account_id} aria-pressed={selected?.account_id === account.account_id} onClick={() => setSelectedId(account.account_id)}><span>{account.name}</span><em>{account.account_type === 'barangay_captain' ? 'Captain' : 'HQ Personnel'}</em></button>)}</div></aside></div>}</>
  return <div className="cd tm-account-editor"><header className="mh2"><h3>{initialType === 'barangay_captain' && captain ? 'Edit captain account' : 'New account'}</h3></header>{error && <div className="tm-feedback" role="alert">{error}</div>}{notice && <div className="tm-feedback" role="status">{notice}</div>}<form id="hqAccountForm" className="fm" onSubmit={submit}><aside className="rail">{['personal', 'access'].map((id, index) => <button type="button" className={section === id ? 'on' : ''} key={id} onClick={() => { setSection(id); document.getElementById(`hq-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }) }}><i>{index + 1}</i>{id === 'personal' ? 'Personal' : 'Access'}</button>)}</aside><div className="fbody">{roleSelector}{isLoading ? <div className="emp" role="status">Loading account details…</div> : <><section className="sec" id="hq-personal"><div className="st">Personal</div><div className="fg">{field('first_name', 'First name')}{field('last_name', 'Last name')}{field('contact_number', 'Mobile', 'tel')}{field('email', 'Email', 'email')}</div></section><section className="sec" id="hq-access"><div className="st">Account access</div><div className="fg"><label className="fl s3"><span>Account ID</span><input disabled value={(initialType === 'barangay_captain' ? workspace?.captain_account_id : workspace?.next_account_id) || ''} /></label><label className="fl s3"><span>{initialType === 'barangay_captain' && captain ? 'New password (optional)' : 'Temporary password'}</span><div className="tm-password"><input type="password" autoComplete="new-password" minLength={8} maxLength={100} required={!(initialType === 'barangay_captain' && captain)} value={form.password} onChange={e => setForm(current => ({ ...current, password: e.target.value }))} /><button className="bt sm" type="button" onClick={() => setForm(current => ({ ...current, password: `Tmp-${Array.from(crypto.getRandomValues(new Uint8Array(8)), n => n.toString(16).padStart(2, '0')).join('')}` }))}>Generate</button></div></label></div>{initialType === 'barangay_captain' && <p className="mu">One Barangay Captain account only. Saving updates the reserved captain account.</p>}</section></>}</div></form><footer className="mf"><button className="bt" type="button" onClick={onClose} disabled={isSaving}>Cancel</button><button className="bt" type="button" onClick={reset} disabled={isLoading || isSaving}>Reset changes</button><button className="bt p nx" type="submit" form="hqAccountForm" disabled={isLoading || isSaving || !workspace}>{isSaving ? 'Saving…' : initialType === 'barangay_captain' && captain ? 'Save changes' : 'Create account'}</button></footer></div>
}
