import { useState } from 'react'
import { LockKeyhole, LogIn, ShieldCheck } from 'lucide-react'

export default function LoginPage({ onLogin, error }) {
  const [form, setForm] = useState({ login: '', password: '' })
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)

    try {
      await onLogin(form)
    } finally {
      setSubmitting(false)
    }
  }

  function updateField(event) {
    setForm({
      ...form,
      [event.target.name]: event.target.value,
    })
  }

  return (
    <main className="login-screen">
      <section className="login-panel">
        <div className="login-brand">
          <span className="brand-mark">R</span>
          <div>
            <strong>RESQPERATION</strong>
            <span>HQ/Admin Web Access</span>
          </div>
        </div>

        <div className="login-copy">
          <p className="eyebrow">Verified command accounts only</p>
          <h1>Sign in to command dashboard</h1>
          <p>
            Use the account ID assigned for HQ operations. Household and rescuer users
            should sign in through the mobile app.
          </p>
        </div>

        <form className="login-form" onSubmit={handleSubmit}>
          <label>
            Account ID or email
            <input
              name="login"
              value={form.login}
              onChange={updateField}
              placeholder="2024035500"
              autoComplete="username"
            />
          </label>

          <label>
            Password
            <input
              name="password"
              type="password"
              value={form.password}
              onChange={updateField}
              placeholder="Enter password"
              autoComplete="current-password"
            />
          </label>

          {error && <div className="form-error">{error}</div>}

          <button className="primary-button" type="submit" disabled={submitting}>
            <LogIn size={17} />
            {submitting ? 'Signing in...' : 'Continue'}
          </button>
        </form>
      </section>

      <section className="login-ops" aria-label="System access notes">
        <div className="ops-card">
          <ShieldCheck size={24} />
          <div>
            <strong>Role matched from database</strong>
            <span>Web access opens only for HQ/Admin accounts.</span>
          </div>
        </div>
        <div className="ops-card">
          <LockKeyhole size={24} />
          <div>
            <strong>Backend validates every entry</strong>
            <span>Laravel handles credentials, token access, and input errors.</span>
          </div>
        </div>
      </section>
    </main>
  )
}
