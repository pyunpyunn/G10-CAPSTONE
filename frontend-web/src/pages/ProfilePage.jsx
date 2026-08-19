import { KeyRound, Pencil, Settings, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import { saveRecoveryQuestions } from '../api/authApi'
import { changePassword, getProfile, updateProfile } from '../api/profileApi'
import ActionMenu from '../components/ui/ActionMenu'
import DeleteAccountModal from '../components/profile/DeleteAccountModal'
import PasswordModal from '../components/profile/PasswordModal'
import RecoveryQuestionsModal from '../components/profile/RecoveryQuestionsModal'
import ProfileEditModal from '../components/profile/ProfileEditModal'
import ProfileIdentity from '../components/profile/ProfileIdentity'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  passwordForm,
  profileErrorMessage,
  profileFormFromIdentity,
} from '../utils/profileHelpers'
import { pageDataError } from '../utils/pageShell'

export default function ProfilePage() {
  const outlet = useOutletContext() || {}
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [isPasswordOpen, setIsPasswordOpen] = useState(false)
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [isRecoveryOpen, setIsRecoveryOpen] = useState(false)
  const [profileForm, setProfileForm] = useState(profileFormFromIdentity())
  const [passwordValues, setPasswordValues] = useState(passwordForm())
  const [recoveryValues, setRecoveryValues] = useState({ current_password: '', question_1: '', answer_1: '', question_2: '', answer_2: '' })
  const [profileFormError, setProfileFormError] = useState('')
  const [passwordError, setPasswordError] = useState('')
  const [recoveryError, setRecoveryError] = useState('')
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

  function openDeleteModal() {
    setMessage('')
    setIsDeleteOpen(true)
  }

  function openRecoveryModal() {
    setRecoveryValues({ current_password: '', question_1: '', answer_1: '', question_2: '', answer_2: '' })
    setRecoveryError('')
    setIsRecoveryOpen(true)
  }

  async function submitRecoveryQuestions(event) {
    event.preventDefault()
    setIsSaving(true)
    setRecoveryError('')

    try {
      const data = await saveRecoveryQuestions(recoveryValues)
      setIsRecoveryOpen(false)
      setMessage(data.message || 'Recovery questions saved successfully.')
    } catch (saveError) {
      setRecoveryError(profileErrorMessage(saveError, 'Unable to save recovery questions.'))
    } finally {
      setIsSaving(false)
    }
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

  const profileActions = [
    { label: 'Edit profile', icon: Pencil, onClick: openEditModal, disabled: !payload },
    { label: 'Change password', icon: KeyRound, onClick: openPasswordModal, disabled: !payload },
    { label: 'Set recovery questions', icon: KeyRound, onClick: openRecoveryModal, disabled: !payload },
    { label: 'Delete account forever', icon: Trash2, onClick: openDeleteModal, danger: true, disabled: !payload },
  ]
  const isInitialLoading = isLoading && !payload
  const dataError = pageDataError(error, Boolean(payload))

  return (
    <section className="page active profile-page">
      <PageHeader title="Profile" />

      {message && <div className="profile-page-message">{message}</div>}
      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}
      {isInitialLoading ? <LoadingState /> : null}

      <div className="profile-layout profile-layout-focused profile-layout-single">
        <ProfileIdentity
          identity={payload?.identity || {}}
          barangayProfile={payload?.barangay_profile || {}}
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

      <RecoveryQuestionsModal
        isOpen={isRecoveryOpen}
        form={recoveryValues}
        setForm={setRecoveryValues}
        formError={recoveryError}
        isSaving={isSaving}
        onClose={() => setIsRecoveryOpen(false)}
        onSubmit={submitRecoveryQuestions}
      />

      <DeleteAccountModal
        isOpen={isDeleteOpen}
        identity={payload?.identity || {}}
        onClose={() => setIsDeleteOpen(false)}
      />
    </section>
  )
}
