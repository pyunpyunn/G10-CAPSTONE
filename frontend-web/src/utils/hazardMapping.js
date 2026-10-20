export const riskColors = { low: '#d5a500', medium: '#e87924', high: '#c73545' }
export function hazardFeatures(layers = [], purokId = '', hazardType = '') {
  return layers.flatMap(layer => (layer.geojson?.features || []).filter(feature =>
    (!purokId || layer.builtin || String(feature.properties.purok_id) === String(purokId)) &&
    (!hazardType || feature.properties.hazard_type === hazardType)
  ).map((feature, index) => ({ ...feature, id: `${layer.id}-${index}`, properties: { ...feature.properties, source: layer.source, assessed_at: layer.assessed_at, source_date:layer.source_date, builtin:layer.builtin } })))
}

export function visibleHazardLayers(layers = [], hazardType = '') {
  return layers.filter(layer => !hazardType || layer.hazard_type === hazardType || layer.geojson?.features?.some(f => f.properties.hazard_type === hazardType))
}
