import { useState } from 'react'
import LoginLanding from '../components/login/LoginLanding'
import LoginModal from '../components/login/LoginModal'

export default function LoginPage({ onLogin, error }) {
  const [isModalOpen, setIsModalOpen] = useState(false)

  return (
    <>
      <LoginLanding onOpenLogin={() => setIsModalOpen(true)} />

      {isModalOpen && (
        <LoginModal
          error={error}
          onClose={() => setIsModalOpen(false)}
          onLogin={onLogin}
        />
      )}
    </>
  )
}
