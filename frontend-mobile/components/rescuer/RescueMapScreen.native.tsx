import { useEffect, useMemo, useRef, useState } from 'react';
import { useIsFocused } from '@react-navigation/native';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Camera, GeoJSONSource, Layer, Map, Marker, type CameraRef } from '@maplibre/maplibre-react-native';
import { api } from '@/api/client';
import { useMapGps } from '@/hooks/useMapGps';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getActivePack, getCachedCenterRoute, saveCachedCenterRoute } from '@/utils/navigationStore';
import { decodePolyline6, downsampleRouteCoordinates, type Coordinate } from '@/utils/routeGeometry';
import { ActionButton, EmptyState, SectionHeader } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  selectedAssignment?: any;
  showNavigationButton?: boolean;
  onOpenNavigation?: () => void;
};

type MapPoint = { latitude: number; longitude: number; accuracy_m?: number | null };
type CachedRoute = Awaited<ReturnType<typeof getCachedCenterRoute>>;

const defaultCenter: Coordinate = [123.8854, 10.3157];
const streetStyle = {
  version: 8 as const,
  sources: {
    osm: {
      type: 'raster' as const,
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '© OpenStreetMap contributors',
      maxzoom: 19,
    },
  },
  layers: [{ id: 'streets', type: 'raster' as const, source: 'osm' }],
};

export function RescueMapScreen({
  assignments,
  evacuationCenters = [],
  activeAssignment,
  selectedAssignment,
  showNavigationButton = false,
  onOpenNavigation,
}: RescueMapProps) {
  const isFocused = useIsFocused();
  const [tracking, setTracking] = useState(true);
  const { position, error: gpsError } = useMapGps(tracking && isFocused, !activeAssignment?.assignment_id);
  const [selectedCenterId, setSelectedCenterId] = useState<string | null>(null);
  const [routePoints, setRoutePoints] = useState<Coordinate[]>([]);
  const [cachedRoute, setCachedRoute] = useState<CachedRoute>(null);
  const [routeSource, setRouteSource] = useState('');
  const [routeError, setRouteError] = useState('');
  const [routeLoading, setRouteLoading] = useState(false);
  const [mapReady, setMapReady] = useState(false);
  const [mapStyle, setMapStyle] = useState<any>(streetStyle);
  const camera = useRef<CameraRef>(null);
  const requestSequence = useRef(0);
  const routeRequest = useRef<AbortController | null>(null);
  const gpsCentered = useRef(false);

  const visibleCenters = useMemo(() => evacuationCenters.filter((center) =>
    center.latitude !== null && center.latitude !== undefined && center.latitude !== ''
      && center.longitude !== null && center.longitude !== ''
      && Number.isFinite(Number(center.latitude)) && Number.isFinite(Number(center.longitude))
      && Math.abs(Number(center.latitude)) <= 90 && Math.abs(Number(center.longitude)) <= 180
  ), [evacuationCenters]);
  const selectedCenter = visibleCenters.find((center) =>
    String(center.evacuation_center_id || center.center_id) === selectedCenterId
  );
  const focusedAssignment = selectedAssignment || activeAssignment;
  const displayRoutePoints = useMemo(() => downsampleRouteCoordinates(routePoints, 800), [routePoints]);
  const activeAssignments = useMemo(() => assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(
      String(item.status_key || '').replaceAll('-', '_').toLowerCase()
    )
  ), [assignments]);
  const routeFeature = useMemo(() => ({
    type: 'Feature' as const,
    properties: {},
    geometry: { type: 'LineString' as const, coordinates: displayRoutePoints },
  }), [displayRoutePoints]);
  const initialCenter: Coordinate = position
    ? [position.longitude, position.latitude]
    : focusedAssignment?.longitude && focusedAssignment?.latitude
      ? [Number(focusedAssignment.longitude), Number(focusedAssignment.latitude)]
      : defaultCenter;

  useEffect(() => {
    let active = true;
    void getActivePack().then((pack) => {
      if (active && pack?.style_url) setMapStyle(pack.style_url);
    }).catch((error) => {
      console.error('Saved MapLibre style could not be loaded.', error);
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!isFocused) {
      requestSequence.current++;
      routeRequest.current?.abort();
      routeRequest.current = null;
      setMapReady(false);
      setRouteLoading(false);
      return;
    }
    gpsCentered.current = false;
  }, [isFocused]);

  useEffect(() => {
    if (!mapReady || !position || gpsCentered.current) return;
    gpsCentered.current = true;
    camera.current?.jumpTo({ center: [position.longitude, position.latitude], zoom: 15 });
  }, [mapReady, position]);

  useEffect(() => {
    if (!isFocused || !mapReady || routePoints.length < 2) return;
    const bounds = routePoints.reduce<[number, number, number, number]>((value, point) => [
      Math.min(value[0], point[0]), Math.min(value[1], point[1]),
      Math.max(value[2], point[0]), Math.max(value[3], point[1]),
    ], [Infinity, Infinity, -Infinity, -Infinity]);
    camera.current?.fitBounds(bounds, { duration: 250, padding: { top: 50, right: 40, bottom: 50, left: 40 } });
  }, [isFocused, mapReady, routePoints]);

  function cachedRouteMatchesLocation(
    saved: CachedRoute,
    center: any,
    current: MapPoint,
  ): boolean {
    if (!saved || saved.centerId !== String(center.evacuation_center_id || center.center_id)
      || saved.profile !== 'auto' || saved.route?.status === 'failed'
      || !Array.isArray(saved.route?.coordinates) && typeof saved.route?.polyline6 !== 'string') return false;
    const sameDestination = distanceMeters(saved.destination, {
      latitude: Number(center.latitude), longitude: Number(center.longitude),
    }) <= 20;
    return sameDestination && distanceMeters(saved.origin, current) <= 150;
  }

  async function selectCenter(center: any) {
    const requestId = ++requestSequence.current;
    routeRequest.current?.abort();
    const id = String(center.evacuation_center_id || center.center_id);
    setSelectedCenterId(id);
    setRoutePoints([]);
    setCachedRoute(null);
    setRouteSource('');
    setRouteError('');
    setRouteLoading(false);
    try {
      const saved = await getCachedCenterRoute(id, 'auto');
      if (requestId !== requestSequence.current || !saved) return;
      setCachedRoute(saved);
      if (position && cachedRouteMatchesLocation(saved, center, position)) {
        setRoutePoints(routeCoordinates(saved.route));
        setRouteSource('Saved road route');
      }
    } catch (error) {
      console.error('Saved evacuation-center route could not be loaded.', error);
    }
  }

  async function routeToSelectedCenter() {
    if (!selectedCenter || !position) {
      setRouteError('Waiting for a usable GPS fix before routing.');
      return;
    }
    if (position.accuracy_m != null && position.accuracy_m > 75) {
      setRouteError('GPS accuracy is limited. Move to an open area and try again.');
      return;
    }
    if (cachedRouteMatchesLocation(cachedRoute, selectedCenter, position)) {
      setRoutePoints(routeCoordinates(cachedRoute?.route));
      setRouteSource('Saved road route');
      setRouteError('');
      return;
    }

    const requestId = ++requestSequence.current;
    const origin = { latitude: position.latitude, longitude: position.longitude };
    const destination = { latitude: Number(selectedCenter.latitude), longitude: Number(selectedCenter.longitude) };
    const centerId = String(selectedCenter.evacuation_center_id || selectedCenter.center_id);
    routeRequest.current?.abort();
    const controller = new AbortController();
    routeRequest.current = controller;
    setRouteLoading(true);
    setRouteError('');
    try {
      const response = await api.post(`/rescuer/evacuation-centers/${encodeURIComponent(centerId)}/route`, {
        ...origin,
        profile: 'auto',
      }, { signal: controller.signal });
      if (requestId !== requestSequence.current) return;
      const route = response.data.data;
      const coordinates = routeCoordinates(route);
      if (coordinates.length < 2) throw new Error('The road route to this center is unavailable.');
      const saved = { centerId, profile: 'auto' as const, origin, destination, route, updatedAt: new Date().toISOString() };
      setCachedRoute(saved);
      setRoutePoints(coordinates);
      setRouteSource('Current road route');
      void saveCachedCenterRoute(centerId, 'auto', origin, destination, route).catch((error) => {
        console.error('Evacuation-center route could not be saved for offline reuse.', error);
      });
    } catch (error: any) {
      if (requestId === requestSequence.current) {
        setRouteError(error.response?.data?.message || error.userMessage || error.message || 'Unable to load this road route.');
        if (cachedRoute && routeCoordinates(cachedRoute.route).length > 1) {
          setRoutePoints(routeCoordinates(cachedRoute.route));
          setRouteSource('Showing saved route; live route refresh failed.');
        }
      }
    } finally {
      if (routeRequest.current === controller) routeRequest.current = null;
      if (requestId === requestSequence.current) setRouteLoading(false);
    }
  }

  function handleTrackPress() {
    if (!tracking && !activeAssignment?.assignment_id) {
      Alert.alert('No active assignment', 'GPS can follow your position, but location sharing to HQ needs an active dispatch assignment.');
    }
    setTracking((value) => !value);
  }

  const initialViewState = { center: initialCenter, zoom: position ? 15 : 12 };

  return (
    <View style={styles.stack}>
      {showNavigationButton && onOpenNavigation ? (
        <Pressable
          style={({ pressed }) => [styles.navigationButton, pressed && styles.navigationButtonPressed]}
          onPress={onOpenNavigation}
          accessibilityRole="button"
        >
          <Ionicons name="navigate-outline" size={20} color="#fff" />
          <Text style={styles.navigationButtonText}>Dispatch directions / offline navigation</Text>
        </Pressable>
      ) : null}
      <View style={styles.card}>
        <SectionHeader
          title="Barangay rescue map"
          action={
            <ActionButton
              label={tracking ? 'Stop following' : 'Follow GPS'}
              icon={tracking ? 'stop-outline' : 'navigate-outline'}
              tone={tracking ? 'danger' : 'primary'}
              onPress={handleTrackPress}
            />
          }
        />

        <View style={styles.layerDropdown}>
          <View style={styles.layerHeader}>
            <Ionicons name="business-outline" size={18} color={palette.navActive} />
            <Text style={styles.layerTitle}>Evacuation centers</Text>
            <Text style={styles.layerCount}>{visibleCenters.length}</Text>
          </View>
          <View style={styles.layerContent}>
            {visibleCenters.length ? visibleCenters.map((center: any, index: number) => {
              const id = String(center.evacuation_center_id || center.center_id || index);
              const selected = selectedCenterId === id;
              return (
                <Pressable
                  key={id}
                  style={[styles.layerItem, selected && styles.layerItemSelected]}
                  onPress={() => void selectCenter(center)}
                  accessibilityRole="button"
                  accessibilityState={{ selected }}
                >
                  <View style={styles.rowText}>
                    <Text style={styles.rowTitle}>{center.name || center.center_name || 'Evacuation center'}</Text>
                    <Text style={styles.rowMeta}>{center.status_label || center.status || 'Available'}</Text>
                  </View>
                  <Ionicons name={selected ? 'radio-button-on' : 'radio-button-off'} size={20} color={palette.navActive} />
                </Pressable>
              );
            }) : <Text style={styles.smallText}>No mapped evacuation centers available.</Text>}
            {selectedCenter ? (
              <Pressable
                style={[styles.routeButton, routeLoading && styles.routeButtonDisabled]}
                onPress={() => void routeToSelectedCenter()}
                disabled={routeLoading}
                accessibilityRole="button"
              >
                <Ionicons name="navigate-outline" size={18} color="#fff" />
                <Text style={styles.routeButtonText}>
                  {routeLoading ? 'Loading road route…' : routePoints.length ? 'Refresh road route' : `Route to ${selectedCenter.name || 'selected center'}`}
                </Text>
              </Pressable>
            ) : null}
            {!!routeSource && <Text style={styles.rowMeta}>{routeSource}</Text>}
          </View>
        </View>

        {gpsError || routeError ? (
          <View style={styles.errorStrip}>
            <Ionicons name="alert-circle-outline" size={18} color={palette.unsafe} />
            <Text accessibilityRole="alert" style={styles.errorText}>{routeError || gpsError}</Text>
          </View>
        ) : null}

        {isFocused ? (
          <View style={styles.map}>
            <Map
              style={StyleSheet.absoluteFill}
              mapStyle={mapStyle}
              onDidFinishLoadingMap={() => setMapReady(true)}
            >
              <Camera ref={camera} initialViewState={initialViewState} />
              {routePoints.length > 1 ? (
                <GeoJSONSource id="evacuation-center-route" data={routeFeature}>
                  <Layer
                    id="evacuation-center-route-line"
                    type="line"
                    paint={{ 'line-color': palette.navActive, 'line-width': 5 }}
                  />
                </GeoJSONSource>
              ) : null}
              {visibleCenters.map((center: any, index: number) => (
                <Marker
                  key={String(center.evacuation_center_id || center.center_id || index)}
                  lngLat={[Number(center.longitude), Number(center.latitude)]}
                >
                  <View style={[
                    styles.centerMarker,
                    selectedCenterId === String(center.evacuation_center_id || center.center_id) && styles.selectedCenterMarker,
                  ]}>
                    <Ionicons name="business" size={13} color="#fff" />
                  </View>
                </Marker>
              ))}
              {focusedAssignment && Number.isFinite(Number(focusedAssignment.longitude)) && Number.isFinite(Number(focusedAssignment.latitude)) ? (
                <Marker key={focusedAssignment.assignment_id} lngLat={[Number(focusedAssignment.longitude), Number(focusedAssignment.latitude)]}>
                  <View style={styles.assignmentMarker}>
                    <Ionicons name="alert" size={14} color="#fff" />
                  </View>
                </Marker>
              ) : null}
              {position ? (
                <Marker lngLat={[position.longitude, position.latitude]}>
                  <View style={styles.gpsMarker} />
                </Marker>
              ) : null}
            </Map>
          </View>
        ) : null}
        {activeAssignments.length === 0 ? (
          <EmptyState icon="location-outline" title="No active dispatch assignments" />
        ) : null}
      </View>
    </View>
  );
}

function routeCoordinates(route: any): Coordinate[] {
  if (typeof route?.polyline6 === 'string') {
    try { return downsampleRouteCoordinates(decodePolyline6(route.polyline6), 800); } catch { return []; }
  }
  const points = (route?.coordinates || [])
    .map((point: any) => [
      Number(point.longitude ?? point.lng ?? point[0]),
      Number(point.latitude ?? point.lat ?? point[1]),
    ] as Coordinate)
    .filter((point: Coordinate) => Number.isFinite(point[0]) && Number.isFinite(point[1])
      && Math.abs(point[0]) <= 180 && Math.abs(point[1]) <= 90);
  return downsampleRouteCoordinates(points, 800);
}

function distanceMeters(first: { latitude: number; longitude: number }, second: { latitude: number; longitude: number }) {
  const radians = Math.PI / 180;
  const latitude1 = first.latitude * radians;
  const latitudeDelta = (second.latitude - first.latitude) * radians;
  const longitudeDelta = (second.longitude - first.longitude) * radians;
  const haversine = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(latitude1) * Math.cos(second.latitude * radians) * Math.sin(longitudeDelta / 2) ** 2;
  return 12742000 * Math.atan2(Math.sqrt(haversine), Math.sqrt(Math.max(0, 1 - haversine)));
}

const styles = StyleSheet.create({
  stack: { gap: spacing.md },
  navigationButton: {
    minHeight: 52, flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: spacing.sm, borderRadius: radius.md, paddingHorizontal: spacing.md, backgroundColor: palette.nav,
  },
  navigationButtonPressed: { opacity: 0.85 },
  navigationButtonText: { color: palette.navText, fontWeight: '800' },
  card: {
    gap: spacing.md, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg,
    padding: spacing.md, backgroundColor: palette.card,
  },
  layerDropdown: {
    overflow: 'hidden', borderWidth: 1, borderColor: palette.border, borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  layerHeader: {
    minHeight: 48, flexDirection: 'row', alignItems: 'center', gap: spacing.sm,
    paddingHorizontal: spacing.md, backgroundColor: palette.secondary,
  },
  layerTitle: { flex: 1, color: palette.text, fontSize: 13, fontWeight: '800' },
  layerCount: { color: palette.textSoft, fontSize: 12, fontWeight: '800' },
  layerContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.sm },
  layerItem: {
    minHeight: 54, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    gap: spacing.sm, paddingVertical: spacing.sm, borderBottomWidth: 1, borderBottomColor: palette.border,
  },
  layerItemSelected: { backgroundColor: palette.secondary },
  rowText: { flex: 1 },
  rowTitle: { color: palette.text, fontSize: 13, fontWeight: '900' },
  rowMeta: { marginTop: 2, color: palette.textSoft, fontSize: 12, fontWeight: '700' },
  routeButton: {
    minHeight: 44, marginTop: spacing.sm, flexDirection: 'row', alignItems: 'center',
    justifyContent: 'center', gap: spacing.sm, borderRadius: radius.md, backgroundColor: palette.navActive,
  },
  routeButtonDisabled: { opacity: 0.6 },
  routeButtonText: { color: '#fff', fontSize: 13, fontWeight: '800' },
  errorStrip: {
    flexDirection: 'row', alignItems: 'center', gap: spacing.sm, borderRadius: radius.md,
    padding: spacing.sm, backgroundColor: '#96202012',
  },
  errorText: { flex: 1, color: palette.unsafe, fontSize: 12, fontWeight: '800' },
  smallText: { paddingVertical: spacing.sm, color: palette.textSoft, fontSize: 13, fontWeight: '800' },
  map: { height: 340, overflow: 'hidden', borderRadius: radius.md, backgroundColor: '#e8eef0' },
  centerMarker: {
    width: 25, height: 25, alignItems: 'center', justifyContent: 'center',
    borderRadius: 13, borderWidth: 2, borderColor: '#fff', backgroundColor: palette.safe,
  },
  selectedCenterMarker: { backgroundColor: palette.navActive, transform: [{ scale: 1.15 }] },
  assignmentMarker: {
    width: 24, height: 24, alignItems: 'center', justifyContent: 'center',
    borderRadius: 12, borderWidth: 2, borderColor: '#fff', backgroundColor: palette.unsafe,
  },
  gpsMarker: {
    width: 20, height: 20, borderRadius: 10, borderWidth: 4,
    borderColor: '#fff', backgroundColor: palette.navActive,
  },
});
