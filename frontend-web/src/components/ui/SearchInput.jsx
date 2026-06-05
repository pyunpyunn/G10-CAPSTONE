export default function SearchInput({ value, onChange, placeholder = 'Search' }) {
  return (
    <input
      className="search-input"
      type="search"
      value={value}
      placeholder={placeholder}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}
