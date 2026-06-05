export default function Button({ tone = 'secondary', size = '', type = 'button', children, onClick, disabled = false }) {
  const classes = ['btn', `btn-${tone}`]

  if (size) {
    classes.push(`btn-${size}`)
  }

  return (
    <button className={classes.join(' ')} type={type} onClick={onClick} disabled={disabled}>
      {children}
    </button>
  )
}
