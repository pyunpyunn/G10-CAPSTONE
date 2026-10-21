import { memo, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Button, ScrollView, Text, View } from 'react-native';
import { Camera, GeoJSONSource, Layer, Map, Marker, type CameraRef, type MarkerRef } from '@maplibre/maplibre-react-native';
import { useLocalSearchParams } from 'expo-router';
import { activeLocalTrip, getActivePack, getLocalNavigationSnapshot, getLocalTrip, queueOutboxEvent, type LocalTrip } from '@/utils/navigationStore';
import * as Crypto from 'expo-crypto';
import { endNavigationTrip, lastNavigationError, mountNavigationSync, refreshNavigation, startNavigationTrip, subscribeNavigationPosition, syncNavigation } from '@/utils/navigationRuntime';
import { navigationPackStatus, prepareNavigationPack } from '@/utils/navigationPacks';
import { bearingDegrees, decodePolyline6, distanceMeters, routeProgress, type Coordinate, type RouteStep } from '@/utils/routeGeometry';
import type { LocationObject } from 'expo-location';
import { api } from '@/api/client';

const RouteLine = memo(function RouteLine({ points }: { points: Coordinate[] }) {
  const data = useMemo(() => ({ type: 'Feature' as const, properties: {}, geometry: { type: 'LineString' as const, coordinates: points } }), [points]);
  return <GeoJSONSource id="dispatch-route" data={data}><Layer id="dispatch-route-line" type="line" paint={{ 'line-color': '#1479ed', 'line-width': 5 }} /></GeoJSONSource>;
});

export default function OfflineNavigationScreen() {
  const { dispatchId } = useLocalSearchParams<{ dispatchId?: string }>();
  const [chosenDispatch, setChosenDispatch] = useState(dispatchId || '');
  const [showStatuses, setShowStatuses] = useState(false);
  const [snapshot, setSnapshot] = useState<any>(null), [pack, setPack] = useState<any>(null);
  const [profile, setProfile] = useState<'auto' | 'pedestrian'>('auto');
  const [route, setRoute] = useState<any>(null), [trip, setTrip] = useState<LocalTrip | null>(null);
  const [message, setMessage] = useState(''), [guidance, setGuidance] = useState('Start a trip to follow the route.');
  const [remaining, setRemaining] = useState(0), [distance, setDistance] = useState(0), [mapReady, setMapReady] = useState(false);
  const [busy, setBusy] = useState(false), [packMessage, setPackMessage] = useState('Map has not been downloaded.');
  const camera = useRef<CameraRef>(null), marker = useRef<MarkerRef>(null);
  const latest = useRef<LocationObject | null>(null), arrivalShown = useRef(false), lastStep = useRef(0);
  const rerouteAt = useRef(0), rerouting = useRef(false);
  const points = useMemo(() => { try { return route?.polyline6 ? decodePolyline6(route.polyline6) : []; } catch { return []; } }, [route]);
  const steps: RouteStep[] = useMemo(() => { try { return typeof route?.steps === 'string' ? JSON.parse(route.steps) : route?.steps || []; } catch { return []; } }, [route]);
  const eligible = (snapshot?.routes || []).filter((item: any) => item.profile === profile && item.status === 'ready');
  const dispatch = snapshot?.dispatches.find((item: any) => Number(item.assignment_id) === Number(trip?.dispatchId || chosenDispatch));

  async function load() {
    const [data, manifest, active] = await Promise.all([getLocalNavigationSnapshot(), getActivePack(), activeLocalTrip()]);
    setSnapshot(data); setPack(manifest); setTrip(active);
    const selectedProfile = active?.profile || profile;
    if (active) { setProfile(active.profile); setDistance(active.distanceM); }
    const selected = data.dispatches.find((item: any) => Number(item.assignment_id) === Number(dispatchId || active?.dispatchId));
    setRoute((current: any) => current || data.routes.find((item: any) => item.profile === selectedProfile && item.status === 'ready'
      && (!selected?.household_id || String(item.household_id) === String(selected.household_id))) || null);
    const status = await navigationPackStatus();
    setPackMessage(status?.state === 'complete' ? 'Map saved for offline use.' : status ? `Map ${Math.round(status.percentage)}% downloaded. Resume on Wi-Fi.` : 'Download the map on Wi-Fi before going offline.');
  }

  useEffect(() => { void load().catch((error) => setMessage(error.message)); }, []);
  useEffect(() => mountNavigationSync(), []);
  useEffect(() => subscribeNavigationPosition((location) => { latest.current = location; }), []);
  useEffect(() => {
    if (!mapReady || points.length < 2) return;
    camera.current?.fitBounds([Math.min(...points.map((p) => p[0])), Math.min(...points.map((p) => p[1])),
      Math.max(...points.map((p) => p[0])), Math.max(...points.map((p) => p[1]))], { duration: 0, padding: { top: 30, right: 30, bottom: 30, left: 30 } });
  }, [mapReady, points]);
  useEffect(() => {
    const timer = setInterval(() => {
      const fix = latest.current;
      latest.current = null;
      if (!fix || !trip || points.length < 2 || fix.coords.accuracy === null || fix.coords.accuracy > 30) return;
      const position: Coordinate = [fix.coords.longitude, fix.coords.latitude];
      const progress = routeProgress(points, position), offRoute = progress.offRouteM > 40;
      if (offRoute && route?.household_id && !rerouting.current && Date.now() > rerouteAt.current) {
        rerouting.current = true; rerouteAt.current = Date.now() + 60000;
        void api.post(`/dispatches/${trip.dispatchId}/route`, { latitude: position[1], longitude: position[0], profile, household_id: route.household_id })
          .then((response) => { setRoute(response.data.data); lastStep.current = 0; })
          .catch(() => { /* Keep the cached line and destination bearing when disconnected. */ })
          .finally(() => { rerouting.current = false; });
      }
      const center = offRoute ? position : progress.snapped;
      camera.current?.jumpTo({ center, zoom: profile === 'auto' ? 17 : 18, pitch: 50,
        bearing: fix.coords.heading !== null && fix.coords.heading >= 0 ? fix.coords.heading : bearingDegrees(points[progress.segment], points[progress.segment + 1]) });
      marker.current?.getAnimatableRef()?.setNativeProps({ lngLat: center });
      const next = steps.findIndex((step, index) => index >= lastStep.current && (progress.cumulative[step.polyline_index] ?? 0) > progress.alongM + 8);
      if (!offRoute && next >= 0) lastStep.current = next;
      const step = steps[lastStep.current];
      const toStep = Math.max(0, (progress.cumulative[step?.polyline_index] ?? 0) - progress.alongM);
      const destination = points[points.length - 1];
      setGuidance(offRoute ? `Off route: ${Math.round(distanceMeters(position, destination))} m, bearing ${Math.round(bearingDegrees(position, destination))}° to destination. Cached directions remain below.`
        : step ? `${Math.round(toStep)} m — ${step.instruction}` : 'Continue to the destination.');
      setRemaining(progress.remainingM);
      void getLocalTrip(trip.localTripId).then((value) => { if (value) setDistance(value.distanceM); });
      const radius = Number(process.env.EXPO_PUBLIC_NAVIGATION_ARRIVAL_RADIUS_M || 25);
      if (!arrivalShown.current && distanceMeters(position, destination) <= radius && fix.coords.accuracy <= radius) {
        arrivalShown.current = true;
        setShowStatuses(true);
        Alert.alert('Destination reached', 'Confirm the household’s status in the dispatch report. End the trip when travel is finished.');
      }
    }, 1000);
    return () => clearInterval(timer);
  }, [points, steps, trip, profile, route]);

  async function action(operation: () => Promise<unknown>) {
    setBusy(true); setMessage('');
    try { await operation(); } catch (error: any) { setMessage(error.message); } finally { setBusy(false); }
  }

  return <ScrollView contentContainerStyle={{ padding: 16, gap: 12, backgroundColor: '#fff' }}>
    <Text style={{ fontSize: 22, fontWeight: '700' }}>Dispatch directions</Text>
    <Text>{packMessage}</Text>
    <Button title="Refresh assigned routes" disabled={busy} onPress={() => void action(async () => { await refreshNavigation(); await load(); })} />
    <Button title="Download / resume offline map (Wi-Fi)" disabled={busy} onPress={() => void action(async () => {
      await prepareNavigationPack((status) => setPackMessage(status.state === 'complete' ? 'Map saved for offline use.' : `Downloading: ${Math.round(status.percentage)}%`), setMessage);
    })} />
    <Text>Travel profile: {profile === 'auto' ? 'Vehicle' : 'On foot'}</Text>
    {!trip && snapshot?.dispatches.filter((item: any) => !['completed', 'cancelled'].includes(item.status)).map((item: any) => <Button key={item.assignment_id}
      title={`Dispatch ${item.assignment_code || item.assignment_id}${String(item.assignment_id) === chosenDispatch ? ' (selected)' : ''}`}
      onPress={() => { setChosenDispatch(String(item.assignment_id)); setRoute(snapshot.routes.find((r: any) => r.profile === profile && r.status === 'ready' && String(r.household_id) === String(item.household_id)) || null); }} />)}
    <Button title="Switch vehicle / foot" disabled={!!trip} onPress={() => { const value = profile === 'auto' ? 'pedestrian' : 'auto'; setProfile(value); setRoute(null); lastStep.current = 0; arrivalShown.current = false; }} />
    {!trip && eligible.map((item: any) => <Button key={`${item.household_id || item.evac_center_id}:${item.profile}`}
      title={`Directions: ${snapshot?.households.find((h: any) => String(h.household_id) === String(item.household_id))?.household_name || snapshot?.evacuation_centers.find((c: any) => String(c.evacuation_center_id) === String(item.evac_center_id))?.name || item.household_id || item.evac_center_id}`}
      onPress={() => { setRoute(item); lastStep.current = 0; arrivalShown.current = false; }} />)}
    {!eligible.length && <Text>No cached routes available for this profile. Refresh while online; ask HQ to precompute missing routes.</Text>}
    {pack?.style_url && points.length >= 2 && <Map style={{ height: 380 }} mapStyle={pack.style_url} onDidFinishLoadingMap={() => setMapReady(true)}>
      <Camera ref={camera} initialViewState={{ center: points[0], zoom: 15 }} />
      <RouteLine points={points} />
      <Marker ref={marker} lngLat={points[0]}><View style={{ width: 18, height: 18, borderRadius: 9, backgroundColor: '#1479ed', borderColor: '#fff', borderWidth: 3 }} /></Marker>
    </Map>}
    <Text style={{ fontSize: 18, fontWeight: '600' }}>{guidance}</Text>
    <Text>{(distance / 1000).toFixed(2)} km recorded · {(remaining / 1000).toFixed(2)} km remaining · ETA {Math.ceil((route?.duration_s || 0) * (remaining / Math.max(1, route?.distance_m || 1)) / 60)} min</Text>
    {trip ? <Button title="End trip and stop GPS" disabled={busy} onPress={() => void action(async () => { await endNavigationTrip(); setTrip(null); })} />
      : <Button title="Start dispatch trip" disabled={busy || !dispatch || !route || points.length < 2 || ['completed', 'cancelled'].includes(dispatch?.status)} onPress={() => void action(async () => {
        const value = await startNavigationTrip(Number(dispatch.assignment_id), profile); setTrip(value); setDistance(0);
      })} />}
    {!dispatch && <Text>Open this screen from a dispatch to record a trip. You can still view saved directions.</Text>}
    <Button title="Sync saved trip events" disabled={busy} onPress={() => void action(async () => { await syncNavigation(); if (lastNavigationError) setMessage(lastNavigationError); })} />
    {!!message && <Text accessibilityRole="alert" style={{ color: '#b91c1c' }}>{message}</Text>}
    {route?.household_id && <Button title="Update household status" onPress={() => setShowStatuses(!showStatuses)} />}
    {showStatuses && route?.household_id && snapshot?.status_options.map((option: any) => <Button key={option.status_id} title={option.status_label}
      onPress={() => void action(async () => {
        await queueOutboxEvent({ client_event_id: Crypto.randomUUID(), type: 'household.status', occurred_at: new Date().toISOString(),
          payload: { household_id: route.household_id, status_id: Number(option.status_id) } });
        setShowStatuses(false); setMessage('Household status saved on this phone for sync.'); void syncNavigation();
      })} />)}
    <Text style={{ fontWeight: '700' }}>Directions</Text>
    {steps.map((step, index) => <Text key={index}>{index + 1}. {step.instruction} ({Math.round(step.distance_m)} m)</Text>)}
    <Text>© OpenStreetMap contributors</Text>
  </ScrollView>;
}
