import { hazardFeatures, riskColors, visibleHazardLayers } from '../../utils/hazardMapping'

export default function HazardMapControls({ workspace, hazardType, onHazardType, onFitHazards }) {
  const layers = workspace.hazard_layers || []
  const types = [...new Set([...layers.map(layer => layer.hazard_type).filter(Boolean), ...hazardFeatures(layers).map(feature => feature.properties.hazard_type)])].sort()
  const visible = visibleHazardLayers(layers, hazardType)
  const agencyLegend = visible.flatMap(layer => (layer.legend || []).map(item => ({ ...item, label: hazardType ? item.label : `${layer.hazard_type}: ${item.label}` })))
  const legend = agencyLegend.length ? agencyLegend : Object.entries(riskColors).map(([label, color]) => ({ label, color }))
  const available = visible.some(layer => layer.raster || layer.geojson?.features?.length)
  return <section className="map-hazard-controls" aria-label="Hazard map controls">
    <label className="map-hazard-label" htmlFor="mapping-hazard-type">Hazard</label>
    <select id="mapping-hazard-type" aria-label="Hazard type" value={hazardType} onChange={event => onHazardType(event.target.value)}>
      <option value="">All hazards</option>{types.map(type => <option key={type}>{type}</option>)}
    </select>
    <button type="button" className="map-hazard-fit" disabled={!available} onClick={onFitHazards}>Fit hazard map</button>
    <ul className="map-hazard-legend" aria-label="Hazard susceptibility legend">{legend.map(({ label, color }, index) => <li key={`${label}-${index}`}><i aria-hidden="true" style={{ backgroundColor:color }} /><span>{label}</span></li>)}</ul>
  </section>
}
