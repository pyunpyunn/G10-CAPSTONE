import { showAuthError, getRememberedLogin, saveRememberedPassword } from '../api/authApi'
import { KeyRound, Pencil, Settings, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { changePassword, getProfile, updateProfile } from '../api/profileApi'
import ActionMenu from '../components/ui/ActionMenu'
import DeleteAccountModal from '../components/profile/DeleteAccountModal'
import PasswordModal from '../components/profile/PasswordModal'
import ProfileEditModal from '../components/profile/ProfileEditModal'
import ProfileIdentity from '../components/profile/ProfileIdentity'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
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
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [profileForm, setProfileForm] = useState(profileFormFromIdentity())
  const [passwordValues, setPasswordValues] = useState(passwordForm())
  const [profileFormError, setProfileFormError] = useState('')
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
    setMessage('')
    setIsPasswordOpen(true)
  }

  function openDeleteModal() {
    setMessage('')
    setIsDeleteOpen(true)
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

    try {
      if (!passwordValues.current_password) throw new Error('Enter your old password.')
      if (passwordValues.password.length < 8 || passwordValues.password.length > 128) throw new Error('New password must contain 8 to 128 characters.')
      if (passwordValues.password !== passwordValues.password_confirmation) throw new Error('New password and confirmation do not match.')
      const data = await changePassword(passwordValues)
      const rememberedLogin = getRememberedLogin()
      const identifiers = [payload?.identity?.account_id, payload?.identity?.user_id, outlet.user?.username, outlet.user?.email]
      if (rememberedLogin && identifiers.includes(rememberedLogin)) {
        await saveRememberedPassword(rememberedLogin, passwordValues.password, true)
      }
      setIsPasswordOpen(false)
      setPasswordValues(passwordForm())
      setMessage(data.message || 'Password changed successfully.')
      await loadProfile(false)
    } catch (saveError) {
      showAuthError(saveError.response ? profileErrorMessage(saveError, 'Unable to change password.') : saveError.message || 'Unable to change password.')
    } finally {
      setIsSaving(false)
    }
  }

  const profileActions = [
    { label: 'Edit profile', icon: Pencil, onClick: openEditModal, disabled: !payload },
    { label: 'Change password', icon: KeyRound, onClick: openPasswordModal, disabled: !payload },
    { label: 'Delete account forever', icon: Trash2, onClick: openDeleteModal, danger: true, disabled: !payload },
  ]

  return (
    <section className="page active profile-page">
      <PageHeader title="Profile" />

      {message && <div className="profile-page-message">{message}</div>}
      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && payload && (
        <div className="profile-layout profile-layout-focused profile-layout-single">
          <ProfileIdentity
            identity={payload.identity || {}}
            barangayProfile={payload.barangay_profile || {}}
            settingsMenu={(
              <ActionMenu
                label="Profile settings"
                buttonClassName="profile-settings-button"
                icon={<Settings size={17} />}
                actions={profileActions}
              />
            )}
          />
        </div>
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
        isSaving={isSaving}
        onClose={() => setIsPasswordOpen(false)}
        onSubmit={submitPassword}
      />

      <DeleteAccountModal
        isOpen={isDeleteOpen}
        identity={payload?.identity || {}}
        onClose={() => setIsDeleteOpen(false)}
      />
    </section>
  )
}
