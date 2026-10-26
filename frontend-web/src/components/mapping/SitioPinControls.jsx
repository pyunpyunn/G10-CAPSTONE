import { resolveSitioPin, sitioGooglePins, sitioSearchUrl } from '../../utils/sitioGooglePins'

export default function SitioPinControls({ selectedId, resultId, onSelect, onResultSelect }) {
  const selected = sitioGooglePins.find(sitio => sitio.id === selectedId)
  const point = resolveSitioPin(selectedId, resultId)
  return <section className="map-sitio-controls" aria-label="Google Maps sitio reference pins">
    <label htmlFor="mapping-sitio-pin">Sitio location</label>
    <select id="mapping-sitio-pin" value={selectedId} onChange={event => onSelect(event.target.value)}>
      <option value="">Select a sitio…</option>
      {sitioGooglePins.map(sitio => <option key={sitio.id} value={sitio.id}>{sitio.name}{sitio.unavailable ? ' · pin unavailable' : sitio.candidates ? ' · Google results' : ''}</option>)}
    </select>
    <p>Google Maps reference pins only. These do not show sitio boundaries.</p>
    {selected && <div role="status">
      <strong>{selected.name}</strong>
      <p>{selected.unavailable || selected.candidateNotice || selected.note}</p>
      {selected.candidates && <>
        <label htmlFor="mapping-sitio-google-result">Google Maps result</label>
        <select id="mapping-sitio-google-result" value={resultId} onChange={event => onResultSelect(event.target.value)}>
          <option value="">Choose a result to show its pin…</option>
          {selected.candidates.map(result => <option key={result.id} value={result.id}>{result.title} · {result.address}</option>)}
        </select>
      </>}
      {point && <p>{point.title}<br />{point.address}<br />Lat {point.latitude.toFixed(7)}<br />Lng {point.longitude.toFixed(7)}</p>}
      {point?.plusCode && <p>{point.plusCode}</p>}
      <a href={sitioSearchUrl(point || selected)} target="_blank" rel="noopener noreferrer">{(point || selected).sourceUrl ? 'View Google Maps result' : 'Search Google Maps'} ↗</a>
    </div>}
  </section>
}
