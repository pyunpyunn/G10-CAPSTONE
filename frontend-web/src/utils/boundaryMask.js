const WORLD_RING = [
  [-180, -85],
  [180, -85],
  [180, 85],
  [-180, 85],
  [-180, -85],
]

export function outsideBoundaryMask(boundary) {
  const polygons = boundary?.type === 'Polygon'
    ? [boundary.coordinates]
    : boundary?.type === 'MultiPolygon' ? boundary.coordinates : []
  const outerRings = polygons.map((polygon) => polygon?.[0]).filter((ring) => Array.isArray(ring) && ring.length >= 4)

  if (outerRings.length === 0) return null

  return {
    type: 'FeatureCollection',
    features: [
      { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [WORLD_RING, ...outerRings] } },
      ...polygons.flatMap((polygon) => (polygon || []).slice(1).map((hole) => ({
        type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [hole] },
      }))),
    ],
  }
}
