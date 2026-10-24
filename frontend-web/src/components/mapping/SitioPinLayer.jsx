import { useEffect, useRef } from 'react'
import L from 'leaflet'
import { Marker, Popup, Tooltip, useMap } from 'react-leaflet'

const icon = L.divIcon({
  className: 'reference-sitio-pin',
  html: '<svg width="30" height="40" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 38C11 31 2 24 2 15a13 13 0 0 1 26 0c0 9-9 16-13 23z" fill="#7c3aed" stroke="#fff" stroke-width="2"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>',
  iconSize: [30, 40], iconAnchor: [15, 40], popupAnchor: [0, -36],
})

export default function SitioPinLayer({ sitio }) {
  const map = useMap()
  const marker = useRef(null)
  useEffect(() => {
    map.setView([sitio.latitude, sitio.longitude], Math.max(map.getZoom(), 17), { animate: false })
    marker.current?.openPopup()
  }, [map, sitio])
  return <Marker ref={marker} icon={icon} position={[sitio.latitude, sitio.longitude]}>
    <Tooltip direction="top">{sitio.name} · {sitio.candidate ? 'Google search candidate' : 'Google Maps reference pin'}</Tooltip>
    <Popup><strong>{sitio.candidate ? sitio.title : sitio.name}</strong>{sitio.candidate && <p><strong>Search candidate — sitio match unconfirmed.</strong></p>}<p>Location pin only — not a sitio boundary.</p><p>Google Maps result: {sitio.title}<br />{sitio.address}</p>{sitio.plusCode && <p>{sitio.plusCode}</p>}<p>{sitio.note}</p><p>Lat {sitio.latitude.toFixed(7)} · Lng {sitio.longitude.toFixed(7)}<br />Researched: {sitio.researchedAt}</p><a href={sitio.sourceUrl} target="_blank" rel="noopener noreferrer">View Google Maps result ↗</a></Popup>
  </Marker>
}
