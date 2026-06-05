export default function Badge({ tone = 'gray', children }) {
  return <span className={`badge b-${tone}`}>{children}</span>
}
