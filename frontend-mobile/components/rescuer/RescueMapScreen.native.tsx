<<<<<<< HEAD
import { statusColor } from '@/constants/statusPalette';
import { useMemo, useState } from 'react';
=======
import { useEffect, useMemo, useRef, useState } from 'react';
>>>>>>> sub-main
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import Ionicons from '@expo/vector-icons/Ionicons';
<<<<<<< HEAD
=======
import { Camera, GeoJSONSource, Layer, Map, Marker, type CameraRef } from '@maplibre/maplibre-react-native';
import { api } from '@/api/client';
>>>>>>> sub-main
import { useMapGps } from '@/hooks/useMapGps';
import { palette, radius, spacing } from '@/constants/resqTheme';
<<<<<<< HEAD
=======
import { getActivePack } from '@/utils/navigationStore';
import { decodePolyline6, type Coordinate } from '@/utils/routeGeometry';
>>>>>>> sub-main
import { ActionButton, EmptyState, SectionHeader } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  selectedAssignment?: any;
  showNavigationButton?: boolean;
  onOpenNavigation?: () => void;
};

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
<<<<<<< HEAD
  const { position, error: gpsError } = useMapGps(tracking, !activeAssignment?.assignment_id);
  const [showEvacuationCenters, setShowEvacuationCenters] = useState(true);
  const [showAssignedRoutes, setShowAssignedRoutes] = useState(true);
  const [expandedLayer, setExpandedLayer] = useState<'centers' | 'routes' | null>(null);

  function stopTracking() {
    setTracking(false);
  }

  function handleTrackPress() {
    if (tracking) {
      stopTracking();
=======
  const { position, error: gpsError } = useMapGps(tracking);
  const [selectedCenterId, setSelectedCenterId] = useState<string | null>(null);
  const [routePoints, setRoutePoints] = useState<Coordinate[]>([]);
  const [routeError, setRouteError] = useState('');
  const [routeLoading, setRouteLoading] = useState(false);
  const [mapReady, setMapReady] = useState(false);
  const [mapStyle, setMapStyle] = useState<any>(streetStyle);
  const camera = useRef<CameraRef>(null);
  const requestSequence = useRef(0);
  const gpsCentered = useRef(false);

  const visibleCenters = useMemo(() => evacuationCenters.filter((center) =>
    center.latitude !== null && center.latitude !== undefined && center.latitude !== ''
      && center.longitude !== null && center.longitude !== undefined && center.longitude !== ''
      && Number.isFinite(Number(center.latitude)) && Number.isFinite(Number(center.longitude))
      && Math.abs(Number(center.latitude)) <= 90 && Math.abs(Number(center.longitude)) <= 180
  ), [evacuationCenters]);
  const selectedCenter = visibleCenters.find((center) =>
    String(center.evacuation_center_id || center.center_id) === selectedCenterId
  );
  const focusedAssignment = selectedAssignment || activeAssignment;
  const routeFeature = useMemo(() => ({
    type: 'Feature' as const,
    properties: {},
    geometry: { type: 'LineString' as const, coordinates: routePoints },
  }), [routePoints]);
  const initialCenter: Coordinate = position
    ? [position.longitude, position.latitude]
    : focusedAssignment?.longitude && focusedAssignment?.latitude
      ? [Number(focusedAssignment.longitude), Number(focusedAssignment.latitude)]
      : defaultCenter;

  useEffect(() => {
    if (!mapReady || !position || gpsCentered.current) return;
    gpsCentered.current = true;
    camera.current?.jumpTo({ center: [position.longitude, position.latitude], zoom: 15 });
  }, [mapReady, position]);

  async function routeToSelectedCenter() {
    if (!selectedCenter || !position) {
      setRouteError('Waiting for a usable GPS fix before routing.');
      return;
    }
    if (position.accuracy_m != null && position.accuracy_m > 75) {
      setRouteError('GPS accuracy is limited. Move to an open area and try again.');
>>>>>>> sub-main
      return;
    }

    const requestId = ++requestSequence.current;
    setRouteLoading(true);
    setRouteError('');
    setRoutePoints([]);
    try {
      const centerId = String(selectedCenter.evacuation_center_id || selectedCenter.center_id);
      const response = await api.post(`/rescuer/evacuation-centers/${encodeURIComponent(centerId)}/route`, {
        latitude: position.latitude,
        longitude: position.longitude,
        profile: 'auto',
      });
      if (requestId !== requestSequence.current) return;
      const points = decodePolyline6(response.data.data.polyline6);
      if (points.length < 2) throw new Error('The road route to this center is unavailable.');
      setRoutePoints(points);
    } catch (error: any) {
      if (requestId === requestSequence.current) {
        setRouteError(error.response?.data?.message || error.userMessage || error.message || 'Unable to load this road route.');
      }
    } finally {
      if (requestId === requestSequence.current) setRouteLoading(false);
    }
  }

  function selectCenter(center: any) {
    requestSequence.current++;
    setSelectedCenterId(String(center.evacuation_center_id || center.center_id));
    setRoutePoints([]);
    setRouteError('');
    setRouteLoading(false);
  }

  function handleTrackPress() {
    if (!tracking && !activeAssignment?.assignment_id) {
      Alert.alert('No active assignment', 'GPS can follow your position, but location sharing to HQ needs an active dispatch assignment.');
    }
    setTracking((value) => !value);
  }

<<<<<<< HEAD
  const activeAssignments = useMemo(() => assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(String(item.status_key || '').replaceAll('-', '_').toLowerCase())
  ), [assignments]);
  const focusedAssignment = selectedAssignment || activeAssignment;
  const mappedAssignments = useMemo(() => activeAssignments.filter((item) => item.latitude && item.longitude), [activeAssignments]);
  const visibleCenters = useMemo(() => showEvacuationCenters
    ? evacuationCenters.filter((center) => center.latitude && center.longitude)
    : [], [evacuationCenters, showEvacuationCenters]);
  const mapMarkers = useMemo(() => [
    ...visibleCenters.map((center) => ({
      latitude: center.latitude,
      longitude: center.longitude,
      label: center.name,
      description: `Evacuation center · ${center.status || 'Active'}`,
      color: palette.safe,
    })),
    ...(showAssignedRoutes ? mappedAssignments.map((assignment) => ({
      latitude: assignment.latitude,
      longitude: assignment.longitude,
      label: assignment.assigned_area || assignment.household_id || 'Dispatch destination',
      description: assignment.status_label || 'Active dispatch',
      color: statusColor(assignment.status_key),
    })) : []),
  ], [visibleCenters, mappedAssignments, showAssignedRoutes]);
  const mapRoutes = useMemo(() => showAssignedRoutes ? mappedAssignments.flatMap((assignment) => {
      const route = assignment.route || {};
      const planned = normalizeCoordinates(route.coordinates);
      const trail = normalizeCoordinates(route.trail_coordinates);

      return [
        ...(planned.length > 1 ? [{
          id: `dispatch-${assignment.assignment_id}`,
          label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · planned route`,
          coordinates: planned,
          color: statusColor(assignment.status_key),
        }] : []),
        ...(trail.length > 1 ? [{
          id: `dispatch-trail-${assignment.assignment_id}`,
          label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · GPS trail`,
          coordinates: trail,
          color: palette.navActive,
        }] : []),
      ];
    }) : [], [mappedAssignments, showAssignedRoutes]);
=======
  const activeAssignments = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(
      String(item.status_key || '').replaceAll('-', '_').toLowerCase()
    )
  );
  const initialViewState = { center: initialCenter, zoom: position ? 15 : 12 };

  useEffect(() => {
    let active = true;
    void getActivePack().then((pack) => {
      if (active && pack?.style_url) setMapStyle(pack.style_url);
    }).catch((error) => {
      if (active) setRouteError(error.message || 'Saved map style is unavailable. Using the online map style.');
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!mapReady || routePoints.length < 2) return;
    camera.current?.fitBounds(
      [Math.min(...routePoints.map((point) => point[0])), Math.min(...routePoints.map((point) => point[1])),
        Math.max(...routePoints.map((point) => point[0])), Math.max(...routePoints.map((point) => point[1]))],
      { duration: 350, padding: { top: 50, right: 40, bottom: 50, left: 40 } },
    );
  }, [mapReady, routePoints]);
>>>>>>> sub-main

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
                  onPress={() => selectCenter(center)}
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
                  {routeLoading ? 'Loading road route…' : `Route to ${selectedCenter.name || 'selected center'}`}
                </Text>
              </Pressable>
            ) : null}
          </View>
        </View>

<<<<<<< HEAD
        {gpsError ? (
          <View style={styles.errorStrip}>
            <Ionicons name="alert-circle-outline" size={18} color={palette.unsafe} />
            <Text style={styles.errorText}>{gpsError}</Text>
          </View>
        ) : null}

        {isFocused ? (
          <MobileLeafletMap
            markers={mapMarkers}
            routes={mapRoutes}
            followPosition={tracking ? position : null}
            center={(focusedAssignment?.latitude && focusedAssignment?.longitude
              ? { latitude: Number(focusedAssignment.latitude), longitude: Number(focusedAssignment.longitude) }
              : defaultRegion)}
          />
        ) : null}

        {mappedAssignments.length === 0 ? (
          <EmptyState
            icon="location-outline"
            title="No mapped assignments"
          />
=======
        {gpsError || routeError ? (
          <View style={styles.errorStrip}>
            <Ionicons name="alert-circle-outline" size={18} color={palette.unsafe} />
            <Text accessibilityRole="alert" style={styles.errorText}>{routeError || gpsError}</Text>
          </View>
        ) : null}

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
                  paint={{ 'line-color': palette.navActive, 'line-width': 6 }}
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
            {activeAssignments.filter((item) => Number.isFinite(Number(item.longitude)) && Number.isFinite(Number(item.latitude))).map((assignment: any) => (
              <Marker key={assignment.assignment_id} lngLat={[Number(assignment.longitude), Number(assignment.latitude)]}>
                <View style={styles.assignmentMarker}>
                  <Ionicons name="alert" size={14} color="#fff" />
                </View>
              </Marker>
            ))}
            {position ? (
              <Marker lngLat={[position.longitude, position.latitude]}>
                <View style={styles.gpsMarker} />
              </Marker>
            ) : null}
          </Map>
        </View>
        {activeAssignments.length === 0 ? (
          <EmptyState icon="location-outline" title="No active dispatch assignments" />
>>>>>>> sub-main
        ) : null}
      </View>
    </View>
  );
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
<<<<<<< HEAD
  smallText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
  },
  rowText: {
    flex: 1,
  },
  rowTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  rowMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
=======
  selectedCenterMarker: { backgroundColor: palette.navActive, transform: [{ scale: 1.15 }] },
  assignmentMarker: {
    width: 24, height: 24, alignItems: 'center', justifyContent: 'center',
    borderRadius: 12, borderWidth: 2, borderColor: '#fff', backgroundColor: palette.unsafe,
  },
  gpsMarker: {
    width: 20, height: 20, borderRadius: 10, borderWidth: 4,
    borderColor: '#fff', backgroundColor: palette.navActive,
>>>>>>> sub-main
  },
});
