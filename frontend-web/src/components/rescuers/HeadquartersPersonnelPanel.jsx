import { useEffect, useState } from 'react'
import { getHeadquartersAccounts, saveHeadquartersAccount } from '../../api/headquartersAccountsApi'
import { AccountProfile } from './RescuerAccountModal'

const emptyForm = () => ({ account_type: 'command_center', first_name: '', last_name: '', email: '', contact_number: '', password: '' })

export default function HeadquartersPersonnelPanel({ editor = false, roleSelector, onClose, onSaved, onManagePersonnel, onBusyChange }) {
  const [workspace, setWorkspace] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [selectedId, setSelectedId] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [section, setSection] = useState('personal')
  const selected = workspace?.accounts?.find(account => account.account_id === selectedId) || workspace?.accounts?.[0]

  useEffect(() => {
    let ignore = false
    getHeadquartersAccounts()
      .then(data => { if (!ignore) setWorkspace(data) })
      .catch(() => { if (!ignore) setError('Headquarters accounts could not be loaded. Check the backend connection.') })
      .finally(() => { if (!ignore) setIsLoading(false) })
    return () => { ignore = true }
  }, [])

  function reset() {
    setForm(emptyForm())
    setError('')
  }

  async function submit(event) {
    event.preventDefault()
    setIsSaving(true)
    onBusyChange?.(true)
    setError('')
    setNotice('')
    try {
      const result = await saveHeadquartersAccount(form)
      if (onSaved) onSaved(result.message)
      else {
        setNotice(result.message)
        setWorkspace(await getHeadquartersAccounts())
        setForm(current => ({ ...current, password: '' }))
      }
    } catch (saveError) {
      setError(saveError.response?.data?.errors
        ? Object.values(saveError.response.data.errors).flat().join(' ')
        : saveError.response?.data?.message || 'The account could not be saved.')
    } finally {
      setIsSaving(false)
      onBusyChange?.(false)
    }
  }

  const field = (key, label, type = 'text', span = 's3') => (
    <label className={`fl ${span}`}>
      <span>{label}</span>
      <input
        type={type}
        required={key === 'first_name' || key === 'last_name'}
        maxLength={key === 'email' ? 255 : key === 'contact_number' ? 50 : 100}
        value={form[key]}
        onChange={event => setForm(current => ({ ...current, [key]: event.target.value }))}
      />
    </label>
  )

  if (!editor) {
    return <>
      {error && <div className="tm-feedback" role="alert">{error}</div>}
      {isLoading ? <div className="cd emp" role="status">Loading headquarters accounts…</div> : (
        <div className="acc">
          {selected ? (
            <AccountProfile
              key={selected.account_id}
              form={{ ...selected, full_name: selected.name, title: selected.is_team_leader ? 'HQ Leader' : 'HQ Personnel' }}
              role="HQ"
              onEdit={selected.responder_id ? () => onManagePersonnel?.(selected) : undefined}
            />
          ) : <div className="cd emp">No headquarters accounts found</div>}
          <aside className="cd tm-account-list">
            <div className="ch">Headquarters<em className="mu">{workspace?.accounts?.length || 0}</em></div>
            <div className="cb">
              {(workspace?.accounts || []).map(account => (
                <button
                  className={`nr${selected?.account_id === account.account_id ? ' on' : ''}`}
                  key={account.account_id}
                  aria-pressed={selected?.account_id === account.account_id}
                  onClick={() => setSelectedId(account.account_id)}
                >
                  <span>{account.name}</span>
                  <em>{account.is_team_leader ? 'HQ Leader' : 'HQ Personnel'}</em>
                </button>
              ))}
            </div>
          </aside>
        </div>
      )}
    </>
  }

  return (
    <div className="cd tm-account-editor">
      <header className="mh2"><h3>New HQ Personnel account</h3></header>
      {error && <div className="tm-feedback" role="alert">{error}</div>}
      {notice && <div className="tm-feedback" role="status">{notice}</div>}
      <form id="hqAccountForm" className="fm" onSubmit={submit}>
        <aside className="rail">
          {['personal', 'access'].map((id, index) => (
            <button
              type="button"
              className={section === id ? 'on' : ''}
              key={id}
              onClick={() => {
                setSection(id)
                document.getElementById(`hq-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
              }}
            >
              <i>{index + 1}</i>{id === 'personal' ? 'Personal' : 'Access'}
            </button>
          ))}
        </aside>
        <div className="fbody">
          {roleSelector}
          {isLoading ? <div className="emp" role="status">Loading account details…</div> : <>
            <section className="sec" id="hq-personal">
              <div className="st">Personal</div>
              <div className="fg">
                {field('first_name', 'First name')}
                {field('last_name', 'Last name')}
                {field('contact_number', 'Mobile', 'tel')}
                {field('email', 'Email', 'email')}
              </div>
            </section>
            <section className="sec" id="hq-access">
              <div className="st">Account access</div>
              <div className="fg">
                <label className="fl s3"><span>Account ID</span><input disabled value={workspace?.next_account_id || ''} /></label>
                <label className="fl s3">
                  <span>Temporary password</span>
                  <div className="tm-password">
                    <input
                      type="password"
                      autoComplete="new-password"
                      minLength={8}
                      maxLength={100}
                      required
                      value={form.password}
                      onChange={event => setForm(current => ({ ...current, password: event.target.value }))}
                    />
                    <button
                      className="bt sm"
                      type="button"
                      onClick={() => setForm(current => ({
                        ...current,
                        password: `Tmp-${Array.from(crypto.getRandomValues(new Uint8Array(8)), value => value.toString(16).padStart(2, '0')).join('')}`,
                      }))}
                    >Generate</button>
                  </div>
                </label>
              </div>
            </section>
          </>}
        </div>
      </form>
      <footer className="mf">
        <button className="bt" type="button" disabled={isSaving} onClick={onClose}>Cancel</button>
        <button className="bt" type="button" disabled={isSaving} onClick={reset}>Reset changes</button>
        <button className="bt p nx" type="submit" form="hqAccountForm" disabled={isSaving}>
          {isSaving ? 'Saving…' : 'Create account'}
        </button>
      </footer>
    </div>
  )
}