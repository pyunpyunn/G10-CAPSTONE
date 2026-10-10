import { useEffect, useState } from 'react'
import { Save, UserPlus } from 'lucide-react'
import { getHeadquartersAccounts, saveHeadquartersAccount } from '../../api/headquartersAccountsApi'

const emptyForm = (accountType = 'command_center') => ({
  account_type: accountType,
  first_name: '',
  last_name: '',
  email: '',
  contact_number: '',
  password: '',
})

export default function HeadquartersAccountsPanel() {
  const [workspace, setWorkspace] = useState(null)
  const [accountType, setAccountType] = useState('command_center')
  const [form, setForm] = useState(() => emptyForm())
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    loadAccounts()
  }, [])

  const captain = workspace?.accounts?.find((account) => account.account_type === 'barangay_captain')

  async function loadAccounts() {
    setIsLoading(true)
    setError('')
    try {
      setWorkspace(await getHeadquartersAccounts())
    } catch {
      setError('Headquarters accounts could not be loaded. Check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function chooseType(nextType) {
    setAccountType(nextType)
    setNotice('')
    setError('')
    if (nextType === 'barangay_captain' && captain) {
      setForm({
        account_type: nextType,
        first_name: captain.first_name || '',
        last_name: captain.last_name || '',
        email: captain.email || '',
        contact_number: captain.contact_number || '',
        password: '',
      })
      return
    }
    setForm(emptyForm(nextType))
  }

  function update(key, value) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function submit(event) {
    event.preventDefault()
    setIsSaving(true)
    setError('')
    setNotice('')
    try {
      const result = await saveHeadquartersAccount(form)
      setNotice(`${result.message} Account ID: ${result.data.account.account_id}`)
      setForm((current) => ({ ...current, password: '' }))
      await loadAccounts()
    } catch (saveError) {
      const validationErrors = saveError.response?.data?.errors
      setError(validationErrors ? Object.values(validationErrors).flat().join(' ') : saveError.response?.data?.message || 'The account could not be saved.')
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="hq-account-workspace">
      <section className="ra-profile-section hq-account-form-section">
        <div className="hq-account-heading">
          <div>
            <h3>Headquarters accounts</h3>
            <p className="ra-section-description">Manage Command Center Personnel and the single Barangay Captain account.</p>
          </div>
        </div>

        <div className="hq-account-type-switch" role="group" aria-label="Headquarters account type">
          <button type="button" className={accountType === 'command_center' ? 'active' : ''} aria-pressed={accountType === 'command_center'} onClick={() => chooseType('command_center')}>Command Center Personnel (HCC)</button>
          <button type="button" className={accountType === 'barangay_captain' ? 'active' : ''} aria-pressed={accountType === 'barangay_captain'} onClick={() => chooseType('barangay_captain')}>Barangay Captain</button>
        </div>

        <form className="hq-account-form" onSubmit={submit}>
          <div className="ra-form-grid">
            <Field label="First name"><input required maxLength={100} value={form.first_name} onChange={(event) => update('first_name', event.target.value)} /></Field>
            <Field label="Last name"><input required maxLength={100} value={form.last_name} onChange={(event) => update('last_name', event.target.value)} /></Field>
            <Field label="Email"><input type="email" maxLength={255} value={form.email} onChange={(event) => update('email', event.target.value)} /></Field>
            <Field label="Contact number"><input type="tel" maxLength={50} value={form.contact_number} onChange={(event) => update('contact_number', event.target.value)} /></Field>
            <Field label={captain && accountType === 'barangay_captain' ? 'New temporary password (optional)' : 'Temporary password'}>
              <input type="password" autoComplete="new-password" minLength={8} maxLength={100} required={!(captain && accountType === 'barangay_captain')} value={form.password} onChange={(event) => update('password', event.target.value)} />
            </Field>
          </div>
          {error && <p className="form-error" role="alert">{error}</p>}
          {notice && <p className="ra-success" role="status">{notice}</p>}
          <div className="hq-account-form-footer">
            <span>{accountType === 'barangay_captain'
              ? `Reserved account: ${workspace?.captain_account_id || 'BDRRM-HQCC-001'}. Saving updates the single captain account.`
              : `Next account ID: ${workspace?.next_account_id || 'assigned on save'}.`}</span>
            <button className="button review" type="submit" disabled={isSaving || isLoading}>
              {accountType === 'command_center' ? <UserPlus size={16} /> : <Save size={16} />}
              {isSaving ? 'Saving...' : accountType === 'command_center' ? 'Create HCC account' : captain ? 'Save captain account' : 'Create captain account'}
            </button>
          </div>
        </form>
      </section>

      <section className="ra-profile-section hq-account-list-section">
        <div className="hq-account-heading">
          <div>
            <h3>Current accounts</h3>
            <p className="ra-section-description">HQ web access uses the standard Admin role. Accounts here are not responder roster entries.</p>
          </div>
          <button className="button secondary" type="button" onClick={loadAccounts} disabled={isLoading}>Refresh</button>
        </div>
        {isLoading ? <p role="status">Loading headquarters accounts...</p> : !workspace?.accounts?.length ? (
          <p className="hq-account-empty">No headquarters accounts found.</p>
        ) : (
          <div className="hq-account-list">
            {workspace.accounts.map((account) => (
              <div className="hq-account-row" key={account.account_id}>
                <div className="hq-account-person">
                  <strong>{account.name || account.account_id}</strong>
                  <span>{account.account_type_label}</span>
                </div>
                <code>{account.account_id}</code>
                <span className={`hq-account-status ${account.is_active ? 'is-active' : ''}`}>{account.is_active ? 'Active' : 'Inactive'}</span>
                {account.account_type === 'barangay_captain' && <button className="button secondary" type="button" onClick={() => chooseType('barangay_captain')}>Manage</button>}
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function Field({ label, children }) {
  return <label><span className="ra-mini-label">{label}</span>{children}</label>
}
