import { useState } from 'react'
import { importHazardLayer, deleteHazardLayer } from '../../api/mappingApi'
import './hazard-planning.css'
import { hazardFeatures, visibleHazardLayers } from '../../utils/hazardMapping'

function download(name, content, type = 'application/json') {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const a = document.createElement('a'); a.href = url; a.download = name; a.click(); URL.revokeObjectURL(url)
}
export default function HazardPlanning({ workspace, purokId, hazardType, user, refresh }) {
  const [success, setSuccess] = useState('')
  const [open, setOpen] = useState(false), [busy, setBusy] = useState(false), [error, setError] = useState('')
  const layers = workspace.hazard_layers || []
  const features = hazardFeatures(layers, purokId, hazardType)
  const visible = visibleHazardLayers(layers, hazardType)
  const government = visible.filter(layer => layer.builtin)
  const localFeatures = features.filter(f => !f.properties.builtin)
  const canEdit = user?.permissions?.can_manage_hazard_maps === true
  const options = workspace.filters.purok_options || []
  function template() {
    download('hazard-layer-template.json', JSON.stringify({ name: 'Official hazard assessment', source: 'C/MDRRMO', source_url: 'https://hazardhunter.georisk.gov.ph/', assessed_at: new Date().toLocaleDateString('en-CA'), geojson: { type: 'FeatureCollection', features: [{ type: 'Feature', properties: { kind: 'hazard', name: 'Replace with assessed area', hazard_type: 'Flood', risk_level: 'high', purok_id: options[0]?.purok_id || 1, individuals: null, families: null, reviewed_by: 'Replace with reviewing office/person' }, geometry: { type: 'Polygon', coordinates: [] } }] } }, null, 2))
  }
  async function upload(event) {
    const file = event.target.files?.[0]; event.target.value = ''; if (!file) return
    setError(''); setSuccess(''); setBusy(true)
    try {
      if (file.size > 5 * 1024 * 1024) throw new Error('Keep each import under 5 MB.')
      await importHazardLayer(JSON.parse(await file.text())); setSuccess('Official map imported. The map and exposure table are refreshing.'); refresh()
    } catch (failure) { setError(Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Import failed.') }
    finally { setBusy(false) }
  }
  async function remove(id) {
    setBusy(true); setError('')
    try { await deleteHazardLayer(id); setSuccess('Hazard map removed.'); refresh() } catch (failure) { setError(failure.response?.data?.message || 'Removal failed.') } finally { setBusy(false) }
  }
  return <div className="hazard-planning">
    <button type="button" aria-expanded={open} onClick={() => setOpen(!open)}>Hazard planning · {layers.length} maps {open ? '▴' : '▾'}</button>
    {open && <div className="hazard-planning-body">
      <p><b>Mambaling, Cebu City hazard map</b> · Government hazard data is loaded automatically and clipped to the barangay boundary.</p>
      {government.length > 0 && <p>Shaded areas show the source agency’s susceptibility classes. These are published hazard maps, not live flood observations. Purok filters apply to local assessments; government coverage remains barangay-wide.</p>}
      {!layers.length && <p>No official hazard maps imported. Request maps from C/MPDO or C/MDRRMO, or obtain GeoRisk data. Unmapped areas have unknown risk.</p>}
      <p><a href="https://hazardhunter.georisk.gov.ph/" target="_blank" rel="noreferrer">GeoRisk HazardHunterPH</a> · <a href="https://cdn.lga.gov.ph/publication/attachments/1704791931.pdf" target="_blank" rel="noreferrer">LGA guidance, items 5 and 7.1</a></p>
      {visible.map(layer => <div className="hazard-source" key={layer.id}><b>{layer.name}</b><span>{layer.source} · {layer.builtin ? `Source: ${layer.source_date} · Retrieved ${layer.retrieved_at}` : `Assessed ${layer.assessed_at}`}</span>{layer.source_url && <a href={layer.source_url} target="_blank" rel="noreferrer">Source</a>}<button type="button" onClick={() => download(`${layer.name}.json`, JSON.stringify(layer, null, 2))}>Export map</button>{canEdit && !layer.builtin && <button type="button" disabled={busy} onClick={() => remove(layer.id)}>Remove</button>}</div>)}
      <details><summary>Local exposure records and assessment tools</summary>
      <p>Government layers do not include individual or family counts per purok. No exposure totals or safe routes are inferred from these maps.</p>
      <p>Exposure is recorded by the assessing office per area and hazard. “Unknown” means no count supplied. Overlapping areas or different hazards must not be added together.</p>
      <div className="hazard-table"><table><caption>Individuals and families at risk by purok / sitio</caption><thead><tr>{['Hazard / area','Purok / sitio','Risk','Individuals','Families'].map(h => <th key={h}>{h}</th>)}</tr></thead><tbody>{localFeatures.filter(f => f.properties.kind === 'hazard').map(f => { const p = f.properties, area = options.find(o => String(o.purok_id) === String(p.purok_id)); return <tr key={f.id}><td>{p.hazard_type} · {p.name}</td><td>{area ? `${area.purok_name} / ${area.sitio_name}` : p.purok_id}</td><td>{p.risk_level}</td><td>{p.individuals ?? 'Unknown'}</td><td>{p.families ?? 'Unknown'}</td></tr> })}</tbody></table></div>
      {canEdit && <><p>Optional local assessment import. Coordinates use WGS84 [longitude, latitude]. Supported kinds: hazard (Polygon/MultiPolygon), evacuation_center (Point), safe_route (LineString). Every feature requires a hazard type, risk level, purok ID, and reviewer. Safe routes also require properties.destination with the designated center name. Replace the empty template geometry with official surveyed coordinates.</p><button type="button" onClick={template}>Download template</button><label className="hazard-upload">{busy ? 'Saving…' : 'Import official map'}<input type="file" accept=".json,.geojson,application/json" disabled={busy} onChange={upload} /></label></>}
      </details>
      {success && <p role="status">{success}</p>}
      {error && <p role="alert">{error}</p>}
    </div>}
  </div>
}
