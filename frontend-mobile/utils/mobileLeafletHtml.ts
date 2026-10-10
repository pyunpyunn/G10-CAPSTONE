import mambalingBoundary from '@/assets/mambaling-boundary.json';
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
  interactive = false,
  geotagPicker = false,
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
  interactive?: boolean;
  geotagPicker?: boolean;
}) {
  const data = JSON.stringify({
    markers,
    routes,
    interactive,
    geotagPicker,
    boundary: geotagPicker ? mambalingBoundary : null,
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
    if (data.geotagPicker) {
      const border = L.geoJSON(data.boundary, { style: { color: '#236a73', weight: 3, fillOpacity: 0.06 } }).addTo(map);
      map.fitBounds(border.getBounds(), { padding: [18, 18] });
      const pin = L.marker(border.getBounds().getCenter(), { draggable: true, autoPan: true }).addTo(map);
      pin.bindTooltip('Drag to your household', { direction: 'top', offset: [0, -35] });
      const send = (message) => {
        if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(message));
        else window.parent.postMessage(message, '*');
      };
      const publish = () => {
        const point = pin.getLatLng();
        send({ type: 'mapPress', latitude: point.lat, longitude: point.lng });
      };
      pin.on('dragstart', () => send({ type: 'pinMoving' }));
      pin.on('dragend', publish);
      map.on('click', (event) => { pin.setLatLng(event.latlng); publish(); });
      window.addEventListener('message', (event) => {
        if (event.source === window.parent && event.data?.type === 'setHouseholdPin' && Number.isFinite(event.data.latitude) && Number.isFinite(event.data.longitude)) {
          window.setHouseholdPin(event.data.latitude, event.data.longitude);
        }
      });
      window.setHouseholdPin = (latitude, longitude) => {
        pin.setLatLng([latitude, longitude]);
        if (!map.getBounds().contains(pin.getLatLng())) map.panTo(pin.getLatLng());
      };
    }
    if (data.interactive && !data.geotagPicker) {
      map.on('click', (event) => {
        if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify({
          type: 'mapPress', latitude: event.latlng.lat, longitude: event.latlng.lng
        }));
      });
    }
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
    if (!data.geotagPicker && bounds.isValid()) map.fitBounds(bounds.pad(0.18), { maxZoom: 16 });
  </script>
</body>
</html>`;
}

