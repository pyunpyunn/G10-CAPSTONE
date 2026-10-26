const cloud = <g fill="currentColor" fillOpacity=".92"><circle cx="9" cy="12" r="3.5" /><circle cx="14" cy="10.5" r="4.5" /><circle cx="17.5" cy="13" r="3" /><rect x="7" y="12" width="12" height="4.2" rx="2.1" /></g>
const sun = <><circle cx="12" cy="12" r="4.5" fill="currentColor" /><g stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" /></g></>
export default function WeatherIcon({ condition }) {
  const key = String(condition || '').toLowerCase()
  const storm = /storm|thunder/.test(key), rain = /rain|drizzle/.test(key), clear = /sun|clear/.test(key)
  return <svg viewBox="0 0 24 24" aria-hidden="true">{storm || rain ? <><g transform="translate(0 -3)">{cloud}</g>{storm ? <path d="M12.8 13.2 10 18h2.6l-1 3.6 3.8-5.2h-2.6l1.2-3.2z" fill="currentColor" /> : <g stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"><path className="dr" d="M9 16.5l-1 3" /><path className="dr" d="M13 16.5l-1 3" /><path className="dr" d="M17 16.5l-1 3" /></g>}</> : /partly/.test(key) ? <><g transform="scale(.72)">{sun}</g><g transform="translate(1.5 3)">{cloud}</g></> : clear ? sun : cloud}</svg>
}
