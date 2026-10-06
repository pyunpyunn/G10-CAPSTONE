export type LeafletPoint = {
  latitude: number | string;
  longitude: number | string;
  label: string;
  description?: string;
  color?: string;
};

export type LeafletRoute = {
  id: string;
  label: string;
  coordinates: Array<{ latitude: number | string; longitude: number | string }>;
  color?: string;
};

export function mobileLeafletHtml({
  markers,
  routes,
  center,
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
}) {
  const data = JSON.stringify({
    markers,
    routes,
    center: center || { latitude: 10.3157, longitude: 123.8854 },
  }).replace(/</g, '\\u003c');

  return `<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    html, body { width: 100vw; height: 100vh; min-height: 100vh; margin: 0; background: #e8eef0; }
    #map { position: fixed; inset: 0; width: 100vw; height: 100vh; background: #e8eef0; }
    .leaflet-control-attribution { font-size: 9px; }
    .leaflet-popup-content-wrapper { border-radius: 6px; }
    .leaflet-popup-content { margin: 9px 12px; font: 13px system-ui, sans-serif; }
    .map-label { font-weight: 700; }
  </style>
</head>
<body>
  <div id="map" role="application" aria-label="OpenStreetMap"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const data = ${data};
    const map = L.map('map', { zoomControl: true }).setView([data.center.latitude, data.center.longitude], 14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    const bounds = L.latLngBounds([]);
    data.markers.forEach((point) => {
      const latitude = Number(point.latitude);
      const longitude = Number(point.longitude);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
      const marker = L.circleMarker([latitude, longitude], {
        radius: 8,
        color: '#ffffff',
        weight: 2,
        fillColor: point.color || '#236a73',
        fillOpacity: 1
      }).addTo(map);
      const popup = document.createElement('div');
      const label = document.createElement('div');
      label.className = 'map-label';
      label.textContent = point.label || 'Location';
      popup.appendChild(label);
      if (point.description) {
        const description = document.createElement('div');
        description.textContent = point.description;
        popup.appendChild(description);
      }
      marker.bindPopup(popup);
      bounds.extend([latitude, longitude]);
    });
    data.routes.forEach((route) => {
      const coordinates = (route.coordinates || []).map((point) => [Number(point.latitude), Number(point.longitude)])
        .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));
      if (coordinates.length < 2) return;
      L.polyline(coordinates, {
        color: route.color || '#267b75',
        weight: 5,
        opacity: 0.9,
        lineCap: 'round',
        lineJoin: 'round'
      }).addTo(map).bindPopup(route.label || 'Route');
      coordinates.forEach((point) => bounds.extend(point));
    });
    if (bounds.isValid()) map.fitBounds(bounds.pad(0.18), { maxZoom: 16 });
  </script>
</body>
</html>`;
}