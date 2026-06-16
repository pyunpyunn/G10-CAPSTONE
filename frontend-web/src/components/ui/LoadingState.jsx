<<<<<<< HEAD
export default function LoadingState() {
=======
export default function LoadingState({ inline = false }) {
>>>>>>> 4748515fd9da7c3d41af7e11c0951e50f424cd0c
  return (
    <div className={`page-loading-card ${inline ? 'inline' : ''}`} role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div className="page-loading-title">Loading...</div>
    </div>
  )
}
