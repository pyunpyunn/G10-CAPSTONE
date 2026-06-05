import { KeyRound, Pencil } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { changePassword, getProfile, updateProfile } from '../api/profileApi'
import PasswordModal from '../components/profile/PasswordModal'
import ProfileActivityList from '../components/profile/ProfileActivityList'
import ProfileEditModal from '../components/profile/ProfileEditModal'
import ProfileIdentity from '../components/profile/ProfileIdentity'
import ProfilePermissionList from '../components/profile/ProfilePermissionList'
import ProfileSummary from '../components/profile/ProfileSummary'
import LoadingState from '../components/ui/LoadingState'
import {
  passwordForm,
  profileErrorMessage,
  profileFormFromIdentity,
} from '../utils/profileHelpers'

export default function ProfilePage() {
  const outlet = useOutletContext() || {}
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [isPasswordOpen, setIsPasswordOpen] = useState(false)
  const [profileForm, setProfileForm] = useState(profileFormFromIdentity())
  const [passwordValues, setPasswordValues] = useState(passwordForm())
  const [profileFormError, setProfileFormError] = useState('')
  const [passwordError, setPasswordError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    loadProfile()
  }, [])

  async function loadProfile(showLoading = true) {
    if (showLoading) {
      setIsLoading(true)
    }

    setError('')

    try {
      const data = await getProfile()
      setPayload(data)
    } catch (loadError) {
      setError(profileErrorMessage(loadError, 'Profile details cannot be loaded right now.'))
    } finally {
      if (showLoading) {
        setIsLoading(false)
      }
    }
  }

  function openEditModal() {
    setProfileForm(profileFormFromIdentity(payload?.identity || {}))
    setProfileFormError('')
    setMessage('')
    setIsEditOpen(true)
  }

  function openPasswordModal() {
    setPasswordValues(passwordForm())
    setPasswordError('')
    setMessage('')
    setIsPasswordOpen(true)
  }

  async function submitProfile(event) {
    event.preventDefault()
    setIsSaving(true)
    setProfileFormError('')

    try {
      const data = await updateProfile(profileForm)

      if (data.user && outlet.onUserChange) {
        outlet.onUserChange(data.user)
      }

      setPayload((currentPayload) => ({
        ...currentPayload,
        user: data.user || currentPayload?.user,
        identity: data.identity || currentPayload?.identity,
        summary: data.summary || currentPayload?.summary,
      }))
      setIsEditOpen(false)
      setMessage('Profile details updated.')
      await loadProfile(false)
    } catch (saveError) {
      setProfileFormError(profileErrorMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  async function submitPassword(event) {
    event.preventDefault()
    setIsSaving(true)
    setPasswordError('')

    try {
      const data = await changePassword(passwordValues)
      setIsPasswordOpen(false)
      setPasswordValues(passwordForm())
      setMessage(data.message || 'Password changed successfully.')
      await loadProfile(false)
    } catch (saveError) {
      setPasswordError(profileErrorMessage(saveError, 'Unable to change password.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="page active profile-page">
      <div className="page-ops-row">
        <div className="left">
          <span className="ops-label">Account settings</span>
        </div>
        <div className="right">
          <button className="btn btn-secondary btn-sm" type="button" disabled={!payload} onClick={openPasswordModal}>
            <KeyRound size={14} />
            Change password
          </button>
          <button className="btn btn-primary btn-sm" type="button" disabled={!payload} onClick={openEditModal}>
            <Pencil size={14} />
            Edit profile
          </button>
        </div>
      </div>

      {message && <div className="profile-page-message">{message}</div>}
      {isLoading && <LoadingState message="Loading profile..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && payload && (
        <>
          <ProfileSummary summary={payload.summary || []} />
          <div className="profile-layout">
            <ProfileIdentity
              identity={payload.identity || {}}
              barangayProfile={payload.barangay_profile || {}}
            />
            <div className="profile-stack">
              <ProfilePermissionList permissions={payload.permissions || []} />
              <ProfileActivityList activity={payload.activity || []} />
            </div>
          </div>
        </>
      )}

      <ProfileEditModal
        isOpen={isEditOpen}
        form={profileForm}
        setForm={setProfileForm}
        formError={profileFormError}
        isSaving={isSaving}
        onClose={() => setIsEditOpen(false)}
        onSubmit={submitProfile}
      />

      <PasswordModal
        isOpen={isPasswordOpen}
        form={passwordValues}
        setForm={setPasswordValues}
        formError={passwordError}
        isSaving={isSaving}
        onClose={() => setIsPasswordOpen(false)}
        onSubmit={submitPassword}
      />
    </section>
  )
}
