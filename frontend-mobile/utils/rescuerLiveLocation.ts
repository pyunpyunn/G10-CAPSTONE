import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { AppState, Platform } from 'react-native';
import { api } from '@/api/client';
import { getStoredItem, setStoredItem, deleteStoredItem } from './secureStorage';

const task = 'resq-active-rescuer-live-location';
const targetKey = 'resq_active_rescuer_location';
const tokenKey = 'resqperation_mobile_token';
type Target = { assignmentId: number; session: string };
let target: Target | null = null;
let paused = false;
let generation = 0;
let setup: Promise<void> = Promise.resolve();
let watcher: Location.LocationSubscription | null = null;
let removeForeground: (() => void) | null = null;
let controller: AbortController | null = null;
let latest: Location.LocationObject | null = null;
let pending: Location.LocationObject | null = null;
let lastUploaded: Location.LocationObject | null = null;
let sending = false;
let lastRouteAt = 0;
let lastUploadedAt = 0;
let lastUploadedFix: Location.LocationObject | null = null;
let watchdog: ReturnType<typeof setInterval> | null = null;
let acquiring = false;
const listeners = new Set<(fix: Location.LocationObject) => void>();
const errors = new Set<(message: string) => void>();
const report = (message: string) => errors.forEach(fn => fn(message));
const MIN_UPLOAD_INTERVAL_MS = 15000;
const MAX_STATIONARY_UPLOAD_INTERVAL_MS = 60000;
const MIN_UPLOAD_DISTANCE_M = 15;

function distanceBetweenFixes(first: Location.LocationObject, second: Location.LocationObject): number {
  const radians = (degrees: number) => degrees * Math.PI / 180;
  const latitudeDelta = radians(second.coords.latitude - first.coords.latitude);
  const longitudeDelta = radians(second.coords.longitude - first.coords.longitude);
  const firstLatitude = radians(first.coords.latitude);
  const secondLatitude = radians(second.coords.latitude);
  const haversine = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(firstLatitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;
  return 6371000 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
}

function shouldUploadFix(fix: Location.LocationObject, now: number): boolean {
  if (!lastUploadedFix) return true;
  const elapsed = now - lastUploadedAt;
  return elapsed >= MAX_STATIONARY_UPLOAD_INTERVAL_MS
    || (elapsed >= MIN_UPLOAD_INTERVAL_MS && distanceBetweenFixes(lastUploadedFix, fix) >= MIN_UPLOAD_DISTANCE_M);
}

function distanceMeters(first: Location.LocationObject, second: Location.LocationObject): number {
  const radians = Math.PI / 180;
  const latitudeDelta = (second.coords.latitude - first.coords.latitude) * radians;
  const longitudeDelta = (second.coords.longitude - first.coords.longitude) * radians;
  const latitude1 = first.coords.latitude * radians;
  const latitude2 = second.coords.latitude * radians;
  const haversine = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(latitude1) * Math.cos(latitude2) * Math.sin(longitudeDelta / 2) ** 2;
  return 12742000 * Math.atan2(Math.sqrt(haversine), Math.sqrt(Math.max(0, 1 - haversine)));
}

function shouldUpload(fix: Location.LocationObject): boolean {
  if (!lastUploaded) return true;
  const elapsedMs = Date.now() - lastUploaded.timestamp;
  if (elapsedMs >= 60000) return true;
  return elapsedMs >= 15000 && distanceMeters(lastUploaded, fix) >= 15;
}

export function validLiveFix(fix: Location.LocationObject): boolean {
  const c = fix?.coords;
  return !!c && Number.isFinite(c.latitude) && Number.isFinite(c.longitude) && Math.abs(c.latitude) <= 90 && Math.abs(c.longitude) <= 180
    && Number.isFinite(fix.timestamp) && Date.now() - fix.timestamp < 30000 && fix.timestamp <= Date.now() + 5000;
}

export function subscribeRescuerPosition(listener: (fix: Location.LocationObject) => void, onError?: (message: string) => void) {
  listeners.add(listener); if (onError) errors.add(onError);
  if (latest && validLiveFix(latest)) listener(latest);
  return () => { listeners.delete(listener); if (onError) errors.delete(onError); };
}
export function hasActiveRescuerTracking() { return !!target; }

async function publish(fix: Location.LocationObject, expected = generation) {
  if (paused || !target || expected !== generation || !validLiveFix(fix) || (latest && fix.timestamp < latest.timestamp)) return;
  latest = fix;
  listeners.forEach(fn => fn(fix));
  if (fix.coords.accuracy != null && fix.coords.accuracy > 75) {
    report('GPS accuracy is limited. Move to an open area.');
    return;
  }
  if (!shouldUpload(fix)) return;
  pending = fix;
  if (sending) return;
  sending = true;
  try {
    while (pending && target && expected === generation) {
      const point = pending; pending = null;
      const requestedAt = Date.now();
      if (!shouldUploadFix(point, requestedAt)) break;
      const owner = target;
      if (await getStoredItem(tokenKey) !== owner.session || expected !== generation) return;
      controller = new AbortController();
<<<<<<< HEAD
      const refresh = requestedAt - lastRouteAt > 60000;
=======
      const refresh = Date.now() - lastRouteAt > 60000;
>>>>>>> sub-main
      try {
        await api.post(`/rescuer/assignments/${owner.assignmentId}/location`, {
          latitude: point.coords.latitude, longitude: point.coords.longitude, accuracy_m: point.coords.accuracy,
          recorded_at: new Date(point.timestamp).toISOString(), refresh_route: refresh,
        }, { signal: controller.signal });
<<<<<<< HEAD
        lastUploadedAt = Date.now();
        lastUploadedFix = point;
        if (refresh) lastRouteAt = lastUploadedAt;
=======
        lastUploaded = point;
        if (refresh) lastRouteAt = Date.now();
>>>>>>> sub-main
        if (expected === generation) report(point.coords.accuracy != null && point.coords.accuracy > 75 ? 'GPS accuracy is limited.' : '');
      } catch (error: any) {
        if (expected === generation) report(error.response?.status === 409 ? 'Assignment changed. Refresh your queue.' : 'GPS is live; HQ sync unavailable.');
        // Retry the newest fix, never a backlog of obsolete positions.
        break;
      }
    }
  } finally { sending = false; controller = null; }
}

async function acquireCurrent(expected: number) {
  if (acquiring || expected !== generation || !target) return;
  acquiring = true;
  try { await publish(await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High }), expected); }
  catch { if (expected === generation) report('Waiting for current GPS. Check device location settings.'); }
  finally { acquiring = false; }
}

TaskManager.defineTask<{ locations: Location.LocationObject[] }>(task, async ({ data, error }) => {
  if (error) { report(error.message); return; }
  const expected = generation;
  if (paused) return;
  const stored = await getStoredItem(targetKey);
  if (!stored) return;
  const owner: Target = JSON.parse(stored);
  if (await getStoredItem(tokenKey) !== owner.session || paused || expected !== generation) return;
  target = owner;
  const newest = [...(data?.locations || [])].sort((a,b) => b.timestamp-a.timestamp)[0];
  if (newest) await publish(newest, expected);
});

export function configureRescuerTracking(assignmentId: number | null) {
  const requestGeneration = ++generation;
  paused = true;
  controller?.abort(); watcher?.remove(); watcher = null;
  removeForeground?.(); removeForeground = null;
  target = null; pending = null; latest = null; lastUploaded = null;
  if (watchdog) clearInterval(watchdog); watchdog = null;
  setup = setup.catch(() => {}).then(async () => {
    if (Platform.OS !== 'web' && await Location.hasStartedLocationUpdatesAsync(task)) await Location.stopLocationUpdatesAsync(task);
    await deleteStoredItem(targetKey);
    if (!assignmentId || requestGeneration !== generation) return;
    const session = await getStoredItem(tokenKey);
    if (!session || requestGeneration !== generation) return;
    target = { assignmentId, session }; lastRouteAt = 0; lastUploadedAt = 0; lastUploadedFix = null;
    await setStoredItem(targetKey, JSON.stringify(target));
    if (requestGeneration !== generation) return;
    paused = false;
    const startForeground = async () => {
      watcher?.remove(); watcher = null;
      if (requestGeneration !== generation || AppState.currentState !== 'active') return;
      if (!(await Location.requestForegroundPermissionsAsync()).granted) { report('Enable precise location for active rescue tracking.'); return; }
      if (requestGeneration !== generation) return;
      void acquireCurrent(requestGeneration);
<<<<<<< HEAD
      const subscription = await Location.watchPositionAsync({ accuracy: Location.Accuracy.High, timeInterval: 10000, distanceInterval: 10, mayShowUserSettingsDialog: true }, fix => { void publish(fix, requestGeneration); });
=======
      const subscription = await Location.watchPositionAsync({ accuracy: Location.Accuracy.High, timeInterval: 5000, distanceInterval: 5, mayShowUserSettingsDialog: true }, fix => { void publish(fix, requestGeneration); });
>>>>>>> sub-main
      if (requestGeneration !== generation) subscription.remove(); else watcher = subscription;
    };
    const appState = AppState.addEventListener('change', state => {
      if (state === 'active') void startForeground().catch(() => report('GPS watch unavailable. Retrying current fixes.')); else { watcher?.remove(); watcher = null; }
    });
    removeForeground = () => appState.remove();
    watchdog = setInterval(() => {
      if (AppState.currentState === 'active' && requestGeneration === generation && (!latest || Date.now() - latest.timestamp > 30000)) { report('Refreshing current GPS…'); void acquireCurrent(requestGeneration); }
    }, 20000);
    await startForeground().catch(() => report('GPS watch unavailable. Retrying current fixes.'));
    if (requestGeneration !== generation) return;
    if (Platform.OS !== 'web') {
      try {
        if ((await Location.requestBackgroundPermissionsAsync()).granted && requestGeneration === generation) {
<<<<<<< HEAD
          await Location.startLocationUpdatesAsync(task, { accuracy: Location.Accuracy.High, timeInterval: 15000, distanceInterval: 15,
=======
          await Location.startLocationUpdatesAsync(task, { accuracy: Location.Accuracy.High, timeInterval: 10000, distanceInterval: 10,
>>>>>>> sub-main
            pausesUpdatesAutomatically: false, showsBackgroundLocationIndicator: true,
            foregroundService: { notificationTitle: 'RESQPERATION · Active rescue GPS', notificationBody: 'Your current location is shared with HQ during this assignment.', killServiceOnDestroy: false } });
        } else if (requestGeneration === generation) report('Allow background location to update HQ while the screen is locked.');
      } catch { if (requestGeneration === generation) report('Background GPS unavailable. Keep the app open.'); }
    }
  });
  return setup;
}

export function stopRescuerTracking() { return configureRescuerTracking(null); }
