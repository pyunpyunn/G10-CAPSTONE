export default function IconButton({ label, children, onClick, className = '' }) {
  return (
    <button className={`icon-button ${className}`.trim()} type="button" aria-label={label} title={label} onClick={onClick}>
      {children}
    </button>
  )
}
