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
  geotagMode = 'drag',
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
  interactive?: boolean;
  geotagPicker?: boolean;
  geotagMode?: 'drag' | 'center';
}) {
  const data = JSON.stringify({
    markers,
    routes,
    interactive,
    geotagPicker,
    geotagMode,
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
    let baseLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      keepBuffer: 1,
      updateWhenIdle: true,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    const bounds = L.latLngBounds([]);
    if (data.geotagPicker) {
      const border = L.geoJSON(data.boundary, { style: { color: '#236a73', weight: 3, fillOpacity: 0.06 } }).addTo(map);
      map.fitBounds(border.getBounds(), { padding: [18, 18] });
      const geometry = data.boundary.type === 'Feature' ? data.boundary.geometry : data.boundary;
      const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
      const boundaryContains = (point) => polygons.some((rings) => {
        const insideRing = (ring) => {
          let inside = false;
          for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [xi, yi] = ring[i], [xj, yj] = ring[j];
            const cross = (point.lng - xi) * (yj - yi) - (point.lat - yi) * (xj - xi);
            if (Math.abs(cross) < 1e-10
              && point.lng >= Math.min(xi, xj) - 1e-10 && point.lng <= Math.max(xi, xj) + 1e-10
              && point.lat >= Math.min(yi, yj) - 1e-10 && point.lat <= Math.max(yi, yj) + 1e-10) return true;
            if ((yi > point.lat) !== (yj > point.lat)
              && point.lng < (xj - xi) * (point.lat - yi) / (yj - yi) + xi) inside = !inside;
          }
          return inside;
        };
        return insideRing(rings[0]) && !rings.slice(1).some(insideRing);
      });
      const send = (message) => {
        if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(message));
        else window.parent.postMessage(message, '*');
      };
      const basemapControl = L.control({ position: 'topright' });
      let basemapButton;
      basemapControl.onAdd = () => {
        const container = L.DomUtil.create('div', 'leaflet-bar');
        basemapButton = L.DomUtil.create('button', '', container);
        basemapButton.type = 'button';
        basemapButton.textContent = 'Satellite';
        basemapButton.setAttribute('aria-label', 'Switch to satellite map');
        basemapButton.style.cssText = 'background:white;border:0;padding:9px;font:13px system-ui;min-height:40px';
        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.on(basemapButton, 'click', () => {
          const satellite = basemapButton.textContent === 'Satellite';
          map.removeLayer(baseLayer);
          baseLayer = L.tileLayer(satellite
            ? 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
            : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            keepBuffer: 1,
            updateWhenIdle: true,
            attribution: satellite ? 'Tiles &copy; Esri' : '&copy; OpenStreetMap contributors'
          }).addTo(map);
          basemapButton.textContent = satellite ? 'Map' : 'Satellite';
          basemapButton.setAttribute('aria-label', satellite ? 'Switch to street map' : 'Switch to satellite map');
        });
        return container;
      };
      basemapControl.addTo(map);
      if (data.geotagMode === 'center') {
        const pin = L.marker(map.getCenter(), {
          interactive: false,
          icon: L.divIcon({
            className: 'household-center-pin',
            html: '<svg width="36" height="46" viewBox="0 0 36 46" aria-hidden="true"><path d="M18 44C14 36 3 26 3 16a15 15 0 1 1 30 0c0 10-11 20-15 28Z" fill="#b4232f" stroke="#fff" stroke-width="3"/><circle cx="18" cy="16" r="5" fill="#fff"/></svg>',
            iconSize: [36, 46],
            iconAnchor: [18, 44]
          })
        }).addTo(map);
        let userPanning = false;
        map.on('move', () => pin.setLatLng(map.getCenter()));
        map.on('dragstart', () => {
          userPanning = true;
          send({ type: 'pinMoving' });
        });
        map.on('moveend', () => {
          if (!userPanning) return;
          userPanning = false;
          const point = map.getCenter();
          send({ type: 'mapPress', latitude: point.lat, longitude: point.lng });
        });
        window.setHouseholdPin = (latitude, longitude) => {
          const point = L.latLng(latitude, longitude);
          map.panTo(point, { animate: false });
        };
      } else {
        const boundsCenter = border.getBounds().getCenter();
        const initialPin = boundaryContains(boundsCenter) ? boundsCenter : L.latLng(10.2875, 123.8854);
        const pin = L.marker(initialPin, { draggable: true, autoPan: true }).addTo(map);
        let lastValid = pin.getLatLng();
        pin.bindTooltip('Drag to your household', { direction: 'top', offset: [0, -35] });
        const publish = () => {
          const point = pin.getLatLng();
          send({ type: 'mapPress', latitude: point.lat, longitude: point.lng });
        };
        pin.on('dragstart', () => send({ type: 'pinMoving' }));
        pin.on('dragend', () => {
          if (!boundaryContains(pin.getLatLng())) {
            pin.setLatLng(lastValid);
            return;
          }
          lastValid = pin.getLatLng();
          publish();
        });
        map.on('click', (event) => {
          if (!boundaryContains(event.latlng)) return;
          pin.setLatLng(event.latlng);
          lastValid = event.latlng;
          publish();
        });
        window.setHouseholdPin = (latitude, longitude) => {
          const point = L.latLng(latitude, longitude);
          if (!boundaryContains(point)) return;
          pin.setLatLng(point);
          lastValid = point;
          if (!map.getBounds().contains(point)) map.panTo(point);
        };
      }
      window.addEventListener('message', (event) => {
        if (event.source === window.parent && event.data?.type === 'setHouseholdPin' && Number.isFinite(event.data.latitude) && Number.isFinite(event.data.longitude)) {
          window.setHouseholdPin(event.data.latitude, event.data.longitude);
        }
      });
    }
    window.addEventListener('message', (event) => {
      if (event.source === window.parent && event.data?.type === 'updateRescueData') window.updateRescueData?.(event.data);
    });
    if (data.interactive && !data.geotagPicker) {
      map.on('click', (event) => {
        if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify({
          type: 'mapPress', latitude: event.latlng.lat, longitude: event.latlng.lng
        }));
      });
    }
    let renderedLayers = [];
    window.updateRescueData = (next) => {
      renderedLayers.forEach(layer => map.removeLayer(layer));
      renderedLayers = [];
    (next.markers || []).forEach((point) => {
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
      renderedLayers.push(marker);
      marker.bindPopup(popup);
      bounds.extend([latitude, longitude]);
    });
    (next.routes || []).forEach((route) => {
      const coordinates = (route.coordinates || []).map((point) => [Number(point.latitude), Number(point.longitude)])
        .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));
      if (coordinates.length < 2) return;
      const routeLine = L.polyline(coordinates, {
        color: route.color || '#267b75',
        weight: 5,
        opacity: 0.9,
        lineCap: 'round',
        lineJoin: 'round'
      }).addTo(map).bindPopup(route.label || 'Route');
      renderedLayers.push(routeLine);
      coordinates.forEach((point) => bounds.extend(point));
    });
    };
    window.updateRescueData(data);
    if (!data.geotagPicker && bounds.isValid()) map.fitBounds(bounds.pad(0.18), { maxZoom: 16 });
    let gpsMarker = null;
    let gpsPosition = null;
    let followGps = true;
    let initialGpsZoom = true;
    if (!data.geotagPicker) {
      const followControl = L.control({ position: 'topright' });
      let followButton;
      followControl.onAdd = () => {
        const container = L.DomUtil.create('div', 'leaflet-bar');
        followButton = L.DomUtil.create('button', '', container);
        followButton.textContent = 'Follow GPS';
        followButton.setAttribute('aria-label', 'Follow GPS position');
        followButton.style.cssText = 'background:white;border:0;padding:9px;font:13px system-ui;min-height:40px';
        followButton.disabled = true;
        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.on(followButton, 'click', () => {
          if (!gpsPosition) return;
          followGps = true;
          initialGpsZoom = true;
          window.setGpsPosition(gpsPosition);
        });
        return container;
      };
      followControl.addTo(map);
      map.on('dragstart', () => { followGps = false; if (followButton) followButton.textContent = 'Follow GPS'; });
      window.setGpsPosition = (point) => {
        if (!point) {
          if (gpsMarker) { map.removeLayer(gpsMarker); gpsMarker = null; }
          gpsPosition = null;
          followGps = true;
          if (followButton) { followButton.disabled = true; followButton.textContent = 'Follow GPS'; }
          initialGpsZoom = true;
          return;
        }
        if (!Array.isArray(point) || !Number.isFinite(point[0]) || !Number.isFinite(point[1])
          || Math.abs(point[0]) > 90 || Math.abs(point[1]) > 180) return;
        gpsPosition = point;
        if (!gpsMarker) gpsMarker = L.circleMarker(point, { radius: 8, color: '#fff', weight: 3, fillColor: '#1479ed', fillOpacity: 1 }).addTo(map);
        else gpsMarker.setLatLng(point);
        if (followButton) { followButton.disabled = false; followButton.textContent = followGps ? 'Following GPS' : 'Follow GPS'; }
        if (followGps) {
          map.setView(point, initialGpsZoom ? 17 : map.getZoom(), { animate: false });
          initialGpsZoom = false;
        }
      };
    }
  </script>
</body>
</html>`;
}
