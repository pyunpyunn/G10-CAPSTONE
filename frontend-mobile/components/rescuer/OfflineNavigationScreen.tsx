import { memo, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Camera, GeoJSONSource, Layer, Map, Marker, type CameraRef, type MarkerRef } from '@maplibre/maplibre-react-native';
import { Stack, useLocalSearchParams } from 'expo-router';
import { activeLocalTrip, getActivePack, getLocalNavigationSnapshot, getLocalTrip, queueOutboxEvent, type LocalTrip } from '@/utils/navigationStore';
import * as Crypto from 'expo-crypto';
import { endNavigationTrip, lastNavigationError, mountNavigationSync, refreshNavigation, startNavigationTrip, syncNavigation } from '@/utils/navigationRuntime';
import { navigationPackStatus, prepareNavigationPack } from '@/utils/navigationPacks';
import { bearingDegrees, decodePolyline6, distanceMeters, routeProgress, type Coordinate, type RouteStep } from '@/utils/routeGeometry';
import type { LocationObject } from 'expo-location';
import { api } from '@/api/client';
import * as Location from 'expo-location';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { configureRescuerTracking, hasActiveRescuerTracking, subscribeRescuerPosition } from '@/utils/rescuerLiveLocation';
import { selectNavigationAssignment, selectAssignedRoute } from '@/utils/assignedNavigationRoute';

const streetStyle = { version: 8 as const, sources: { osm: { type: 'raster' as const, tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '© OpenStreetMap contributors', maxzoom: 19 } }, layers: [{ id: 'streets', type: 'raster' as const, source: 'osm' }] };

const RouteLine = memo(function RouteLine({ points }: { points: Coordinate[] }) {
  const data = useMemo(() => ({ type: 'Feature' as const, properties: {}, geometry: { type: 'LineString' as const, coordinates: points } }), [points]);
  return <GeoJSONSource id="dispatch-route" data={data}><Layer id="dispatch-route-line" type="line" paint={{ 'line-color': palette.navActive, 'line-width': 5 }} /></GeoJSONSource>;
});

export default function OfflineNavigationScreen() {
  const { dispatchId } = useLocalSearchParams<{ dispatchId?: string }>();
  const [chosenDispatch, setChosenDispatch] = useState(dispatchId || '');
  const [showSettings, setShowSettings] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const [showStatuses, setShowStatuses] = useState(false);
  const [snapshot, setSnapshot] = useState<any>(null), [pack, setPack] = useState<any>(null);
  const [profile, setProfile] = useState<'auto' | 'pedestrian'>('auto');
  const [route, setRoute] = useState<any>(null), [trip, setTrip] = useState<LocalTrip | null>(null);
  const [message, setMessage] = useState(''), [guidance, setGuidance] = useState('');
  const [remaining, setRemaining] = useState(0), [distance, setDistance] = useState(0), [mapReady, setMapReady] = useState(false);
  const [busy, setBusy] = useState(false), [packMessage, setPackMessage] = useState('Map has not been downloaded.');
  const camera = useRef<CameraRef>(null), marker = useRef<MarkerRef>(null);
  const [gpsFix, setGpsFix] = useState<LocationObject | null>(null);
  const latest = useRef<LocationObject | null>(null), arrivalShown = useRef(false), lastStep = useRef(0);
  const firstGps = useRef(true);
  const rerouteAt = useRef(0), rerouting = useRef(false);
  const points = useMemo<Coordinate[]>(() => { try { return route?.polyline6 ? decodePolyline6(route.polyline6) : (route?.coordinates || []).map((point: any) => [Number(point.longitude), Number(point.latitude)] as Coordinate).filter((point: Coordinate) => Number.isFinite(point[0]) && Number.isFinite(point[1])); } catch { return []; } }, [route]);
  const steps: RouteStep[] = useMemo(() => { try { return typeof route?.steps === 'string' ? JSON.parse(route.steps) : route?.steps || []; } catch { return []; } }, [route]);
  const dispatch = snapshot?.dispatches.find((item: any) => Number(item.assignment_id) === Number(trip?.dispatchId || chosenDispatch));
  const householdIds: string[] = (dispatch?.household_id ? [dispatch.household_id] : []).map(String);
  const eligible = (snapshot?.routes || []).filter((item: any) => item.profile === profile && item.status === 'ready'
    && (!item.assignment_id || String(item.assignment_id) === String(dispatch?.assignment_id)) && householdIds.includes(String(item.household_id)));

  async function load() {
    const [data, manifest, active] = await Promise.all([getLocalNavigationSnapshot(), getActivePack(), activeLocalTrip()]);
    setSnapshot(data); setPack(manifest); setTrip(active);
    const selectedProfile = active?.profile || profile;
    if (active) { setProfile(active.profile); setDistance(active.distanceM); }
    const selectedId = active?.dispatchId || dispatchId || chosenDispatch;
    const selected = selectNavigationAssignment(data.dispatches, selectedId);
    setChosenDispatch(selected ? String(selected.assignment_id) : '');
    setRoute(selectAssignedRoute(data.routes, selected, selectedProfile));
    setLoaded(true);
    const status = await navigationPackStatus();
    setPackMessage(status?.state === 'complete' ? 'Map saved for offline use.' : status ? `Map ${Math.round(status.percentage)}% downloaded. Resume on Wi-Fi.` : 'Download the map on Wi-Fi before going offline.');
  }

  useEffect(() => { void action(async () => { await load(); try { await refreshNavigation(); } finally { await load(); } }); }, [dispatchId]);
  useEffect(() => {
    if (!loaded || !dispatch?.household_id || ['completed','cancelled'].includes(dispatch.status)) return;
    let active = true;
    void (async () => {
      if (!(await Location.requestForegroundPermissionsAsync()).granted) throw new Error('Location permission required.');
      const fix = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      if (!active) return;
      latest.current = fix; setGpsFix(fix);
      const response = await api.post(`/rescuer/assignments/${dispatch.assignment_id}/route`, { latitude: fix.coords.latitude, longitude: fix.coords.longitude, household_id: dispatch.household_id, profile });
      if (active) { setRoute(response.data.data); setMessage(''); }
    })().catch(() => { if (active) setMessage('Current directions unavailable. Showing saved route.'); });
    return () => { active = false; };
  }, [loaded, dispatch?.assignment_id, dispatch?.household_id, dispatch?.status, profile]);
  useEffect(() => {
    setRemaining(Number(route?.distance_m || 0));
    setGuidance(steps[0]?.instruction || 'Follow the highlighted route.');
    lastStep.current = 0; arrivalShown.current = false;
  }, [route, steps]);
  useEffect(() => {
    if (dispatch?.assignment_id && !hasActiveRescuerTracking()) void configureRescuerTracking(Number(dispatch.assignment_id)).catch(() => setMessage('Enable location for live navigation.'));
  }, [dispatch?.assignment_id]);
  useEffect(() => subscribeRescuerPosition(fix => { latest.current = fix; setGpsFix(fix); }, problem => { if (problem) setMessage(problem); }), []);
  useEffect(() => mountNavigationSync(), []);
  useEffect(() => {
    if (!mapReady || points.length < 2) return;
    camera.current?.fitBounds([Math.min(...points.map((p) => p[0])), Math.min(...points.map((p) => p[1])),
      Math.max(...points.map((p) => p[0])), Math.max(...points.map((p) => p[1]))], { duration: 0, padding: { top: 140, right: 40, bottom: 100, left: 40 } });
  }, [mapReady, points]);
  useEffect(() => {
    if (mapReady && gpsFix && firstGps.current) { firstGps.current = false; camera.current?.jumpTo({ center: [gpsFix.coords.longitude, gpsFix.coords.latitude], zoom: 17 }); }
  }, [mapReady, gpsFix]);
  useEffect(() => {
    const timer = setInterval(() => {
      const fix = latest.current;
      latest.current = null;
      if (!fix || points.length < 2 || Date.now() - fix.timestamp > 30000 || (fix.coords.accuracy != null && fix.coords.accuracy > 75)) return;
      const position: Coordinate = [fix.coords.longitude, fix.coords.latitude];
      const progress = routeProgress(points, position), offRoute = progress.offRouteM > 40;
      if (offRoute && dispatch && route?.household_id && !rerouting.current && Date.now() > rerouteAt.current) {
        rerouting.current = true; rerouteAt.current = Date.now() + 60000;
        void api.post(`/rescuer/assignments/${dispatch.assignment_id}/route`, { latitude: position[1], longitude: position[0], profile, household_id: route.household_id })
          .then((response) => { setRoute(response.data.data); lastStep.current = 0; })
          .catch(() => { /* Keep the cached line and destination bearing when disconnected. */ })
          .finally(() => { rerouting.current = false; });
      }
      const center = position;
      camera.current?.jumpTo({ center, zoom: profile === 'auto' ? 17 : 18, pitch: 50,
        bearing: fix.coords.heading !== null && fix.coords.heading >= 0 ? fix.coords.heading : bearingDegrees(points[progress.segment], points[progress.segment + 1]) });
      marker.current?.getAnimatableRef()?.setNativeProps({ lngLat: center });
      const next = steps.findIndex((step, index) => index >= lastStep.current && (progress.cumulative[step.polyline_index] ?? 0) > progress.alongM + 8);
      if (!offRoute && next >= 0) lastStep.current = next;
      const step = steps[lastStep.current];
      const toStep = Math.max(0, (progress.cumulative[step?.polyline_index] ?? 0) - progress.alongM);
      const destination = points[points.length - 1];
      setGuidance(offRoute ? `Off route: ${Math.round(distanceMeters(position, destination))} m, bearing ${Math.round(bearingDegrees(position, destination))}° to destination. Follow the highlighted route.`
        : step ? `${Math.round(toStep)} m — ${step.instruction}` : 'Continue to the destination.');
      setRemaining(progress.remainingM);
      if (trip) void getLocalTrip(trip.localTripId).then((value) => { if (value) setDistance(value.distanceM); });
      const radius = Number(process.env.EXPO_PUBLIC_NAVIGATION_ARRIVAL_RADIUS_M || 25);
      if (!arrivalShown.current && distanceMeters(position, destination) <= radius && (fix.coords.accuracy ?? Infinity) <= radius) {
        arrivalShown.current = true;
        setShowStatuses(true);
        Alert.alert('Destination reached', 'Confirm the household’s status in the dispatch report. End the trip when travel is finished.');
      }
    }, 1000);
    return () => clearInterval(timer);
  }, [points, steps, trip, profile, route, dispatch]);

  async function action(operation: () => Promise<unknown>) {
    setBusy(true); setMessage('');
    try { await operation(); } catch (error: any) { setMessage(error.response?.status === 401 ? 'Your session expired. Sign in again to refresh routes; saved directions remain available.' : error.response?.data?.message || error.userMessage || error.message); } finally { setBusy(false); }
  }

  return <View style={{ flex: 1, backgroundColor: palette.page }}>
    <Stack.Screen options={{ title: 'Rescue navigation', headerStyle: { backgroundColor: palette.nav }, headerTintColor: palette.navText }} />
    <Map style={{ flex: 1 }} mapStyle={pack?.style_url || streetStyle} onDidFinishLoadingMap={() => setMapReady(true)}>
      <Camera ref={camera} initialViewState={{ center: [123.8854, 10.3157], zoom: 11 }} />
      {points.length >= 2 && <RouteLine points={points} />}
      {gpsFix && <Marker ref={marker} lngLat={[gpsFix.coords.longitude, gpsFix.coords.latitude]}><View style={{ width: 20, height: 20, borderRadius: 10, backgroundColor: palette.navActive, borderColor: '#fff', borderWidth: 3 }} /></Marker>}
      {points.length > 1 && <Marker lngLat={points[points.length - 1]}><View style={{ width: 22, height: 22, borderRadius: 11, backgroundColor: palette.brandRed, borderColor: '#fff', borderWidth: 3 }} /></Marker>}
    </Map>
    <View style={{ position: 'absolute', top: 16, left: 16, right: 16, padding: 16, borderRadius: 14, backgroundColor: palette.nav, elevation: 5 }}>
      <Text style={{ fontSize: 19, fontWeight: '700', color: palette.navText }}>{points.length >= 2 ? guidance : !loaded ? 'Loading assigned route…' : message || (dispatch ? 'Waiting for your assigned route.' : 'No active dispatch assigned.')}</Text>
      <Text style={{ color: palette.navMuted, marginTop: 6 }}>{gpsFix ? `GPS · ±${Math.round(gpsFix.coords.accuracy || 0)} m` : 'Acquiring current GPS'}</Text>
      {points.length >= 2 && !!message && <Text accessibilityRole="alert" style={{ marginTop: 8, color: palette.brandRed }}>{message}</Text>}
      {points.length >= 2 && <Text style={{ marginTop: 8, color: palette.navText }}>{(remaining / 1000).toFixed(1)} km remaining · {Math.ceil((route?.duration_s || 0) * (remaining / Math.max(1, route?.distance_m || 1)) / 60)} min</Text>}
    </View>
    <View style={{ position: 'absolute', bottom: 24, right: 16, gap: 8 }}>
      <NavButton title="Zoom in" onPress={() => camera.current?.zoomTo(18, { duration: 250 })} />
      <NavButton title="Recenter GPS" disabled={!gpsFix} onPress={() => camera.current?.jumpTo({ center: gpsFix ? [gpsFix.coords.longitude, gpsFix.coords.latitude] : points[0], zoom: 17, pitch: 45, bearing: points.length >= 2 ? bearingDegrees(points[0], points[1]) : 0 })} />
      <NavButton title="Refresh route" disabled={busy} onPress={() => void action(async () => { await refreshNavigation(); await load(); })} />
      <NavButton title="Route options" onPress={() => setShowSettings(true)} />
    </View>
    <Modal visible={showSettings || showStatuses} animationType="slide" onRequestClose={() => { setShowSettings(false); setShowStatuses(false); }}>
    <ScrollView contentContainerStyle={{ padding: 24, paddingTop: 48, gap: 12, backgroundColor: palette.page }}>
    <NavButton title="Back to map" onPress={() => { setShowSettings(false); setShowStatuses(false); }} />
    <Text style={{ fontSize: 22, fontWeight: '700' }}>Dispatch directions</Text>
    <Text>{packMessage}</Text>
    <NavButton title="Refresh assigned routes" disabled={busy} onPress={() => void action(async () => { await refreshNavigation(); await load(); })} />
    <NavButton title="Download / resume offline map (Wi-Fi)" disabled={busy} onPress={() => void action(async () => {
      await prepareNavigationPack((status) => setPackMessage(status.state === 'complete' ? 'Map saved for offline use.' : `Downloading: ${Math.round(status.percentage)}%`), setMessage);
    })} />
    <Text>Travel profile: {profile === 'auto' ? 'Vehicle' : 'On foot'}</Text>
    {!trip && snapshot?.dispatches.filter((item: any) => !['completed', 'cancelled'].includes(item.status)).map((item: any) => <NavButton key={item.assignment_id}
      title={`Dispatch ${item.assignment_code || item.assignment_id}${String(item.assignment_id) === chosenDispatch ? ' (selected)' : ''}`}
      onPress={() => { setChosenDispatch(String(item.assignment_id)); setRoute(selectAssignedRoute(snapshot.routes, item, profile)); }} />)}
    <NavButton title="Switch vehicle / foot" disabled={!!trip} onPress={() => { const value = profile === 'auto' ? 'pedestrian' : 'auto'; setProfile(value); setRoute(selectAssignedRoute(snapshot?.routes || [], dispatch, value)); lastStep.current = 0; arrivalShown.current = false; }} />
    {!trip && eligible.map((item: any) => <NavButton key={`${item.household_id || item.evac_center_id}:${item.profile}`}
      title={`Directions: ${snapshot?.households.find((h: any) => String(h.household_id) === String(item.household_id))?.household_name || snapshot?.evacuation_centers.find((c: any) => String(c.evacuation_center_id) === String(item.evac_center_id))?.name || item.household_id || item.evac_center_id}`}
      onPress={() => { setRoute(item); lastStep.current = 0; arrivalShown.current = false; }} />)}
    {!eligible.length && <Text>No cached routes available for this profile. Refresh while online; ask HQ to precompute missing routes.</Text>}
    {!trip && householdIds.map((id) => <NavButton key={`live-${id}`} disabled={busy} title={`Route from my GPS: ${snapshot?.households.find((h: any) => String(h.household_id) === id)?.household_name || id}`}
      onPress={() => void action(async () => {
        if (!(await Location.requestForegroundPermissionsAsync()).granted) throw new Error('Location permission is required.');
        const fix = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
        const response = await api.post(`/rescuer/assignments/${dispatch.assignment_id}/route`, {
          latitude: fix.coords.latitude, longitude: fix.coords.longitude, profile, household_id: id,
        });
        setRoute(response.data.data); setRemaining(response.data.data.distance_m || 0); lastStep.current = 0; arrivalShown.current = false;
      })} />)}
    <Text style={{ fontSize: 18, fontWeight: '600' }}>{guidance}</Text>
    <Text>{(distance / 1000).toFixed(2)} km recorded · {(remaining / 1000).toFixed(2)} km remaining · ETA {Math.ceil((route?.duration_s || 0) * (remaining / Math.max(1, route?.distance_m || 1)) / 60)} min</Text>
    {trip ? <NavButton title="End trip and stop GPS" disabled={busy} onPress={() => void action(async () => { await endNavigationTrip(); setTrip(null); })} />
      : <NavButton title="Start dispatch trip" disabled={busy || !dispatch || !route || points.length < 2 || ['completed', 'cancelled'].includes(dispatch?.status)} onPress={() => void action(async () => {
        const value = await startNavigationTrip(Number(dispatch.assignment_id), profile); setTrip(value); setDistance(0); setRemaining(route.distance_m || 0);
      })} />}
    {!dispatch && <Text>Open this screen from a dispatch to record a trip. You can still view saved directions.</Text>}
    <NavButton title="Sync saved trip events" disabled={busy} onPress={() => void action(async () => { await syncNavigation(); if (lastNavigationError) setMessage(lastNavigationError); })} />
    {!!message && <Text accessibilityRole="alert" style={{ color: palette.brandRed }}>{message}</Text>}
    {route?.household_id && <NavButton title="Update household status" onPress={() => setShowStatuses(!showStatuses)} />}
    {showStatuses && route?.household_id && snapshot?.status_options.map((option: any) => <NavButton key={option.status_id} title={option.status_label}
      onPress={() => void action(async () => {
        await queueOutboxEvent({ client_event_id: Crypto.randomUUID(), type: 'household.status', occurred_at: new Date().toISOString(),
          payload: { household_id: route.household_id, status_id: Number(option.status_id) } });
        setShowStatuses(false); setMessage('Household status saved on this phone for sync.'); void syncNavigation();
      })} />)}
    <Text style={{ fontWeight: '700' }}>Directions</Text>
    {steps.map((step, index) => <Text key={index}>{index + 1}. {step.instruction} ({Math.round(step.distance_m)} m)</Text>)}
    <Text>© OpenStreetMap contributors</Text>
  </ScrollView>
    </Modal>
  </View>;
}

function NavButton({ title, onPress, disabled = false }: { title: string; onPress: () => void; disabled?: boolean }) {
 return <Pressable accessibilityRole="button" accessibilityState={{ disabled }} disabled={disabled} onPress={onPress} style={[navStyles.button, disabled && navStyles.disabled]}><Text style={navStyles.label}>{title}</Text></Pressable>;
}
const navStyles = StyleSheet.create({
 button: { minHeight: 44, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.md, paddingVertical: 10, borderRadius: radius.md, backgroundColor: palette.navActive, borderWidth: 1, borderColor: palette.navHover },
 label: { color: palette.navText, fontSize: 14, fontWeight: '600', textAlign: 'center' }, disabled: { opacity: 0.45 },
});
