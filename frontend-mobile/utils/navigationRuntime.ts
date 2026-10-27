import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import * as Network from 'expo-network';
import { AppState } from 'react-native';
import { configureRescuerTracking, hasActiveRescuerTracking, subscribeRescuerPosition } from './rescuerLiveLocation';
import { api } from '@/api/client';
import { assignmentNavigationSnapshot } from './assignmentNavigationSnapshot';
import { activeLocalTrip, addLocalTripPoint, acknowledgeOutbox, completeLocalTrip, createLocalTrip, getLocalTrip,
  getPendingOutboxEvents, getActivePack, saveBootstrap } from './navigationStore';

const taskName = 'resq-active-dispatch-navigation';
let work: Promise<unknown> = Promise.resolve();
let syncing: Promise<void> | null = null;
let retryAfter = 0, failures = 0;
let sessionGeneration = 0;
let syncPaused = false;
let syncController: AbortController | null = null;
export let lastNavigationError = '';
const listeners = new Set<(location: Location.LocationObject) => void>();

function serial<T>(operation: () => Promise<T>): Promise<T> {
  const next = work.then(operation);
  work = next.catch((error) => { lastNavigationError = error.message; });
  return next;
}

TaskManager.defineTask<{ locations: Location.LocationObject[] }>(taskName, async ({ data, error }) => {
  if (error) { lastNavigationError = error.message; return; }
  await serial(async () => {
    const trip = await activeLocalTrip();
    if (!trip) return;
    for (const location of data?.locations || []) {
      await addLocalTripPoint(trip.localTripId, { ...location.coords, recordedAt: new Date(location.timestamp).toISOString() });
      listeners.forEach((listener) => listener(location));
    }
  });
});

export function subscribeNavigationPosition(listener: (location: Location.LocationObject) => void) {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
}

// Trip recording and HQ sharing consume the same native GPS service.
subscribeRescuerPosition(async (location) => {
  const generation = sessionGeneration;
  await serial(async () => {
    if (syncPaused || generation !== sessionGeneration) return;
    const trip = await activeLocalTrip();
    if (!trip || syncPaused || generation !== sessionGeneration) return;
    await addLocalTripPoint(trip.localTripId, { ...location.coords, recordedAt: new Date(location.timestamp).toISOString() });
    listeners.forEach(listener => listener(location));
  });
});

export async function refreshNavigation() {
  if (syncPaused) return;
  const generation = sessionGeneration;
  const [overview, bootstrap] = await Promise.all([
    api.get('/rescuer/overview'),
    api.get('/mobile/bootstrap').then(response => response.data.data).catch(async (error: any) => {
      if (![401, 403].includes(error.response?.status)) lastNavigationError = error.userMessage || error.message;
      return { pack: await getActivePack() };
    }),
  ]);
  if (syncPaused || generation !== sessionGeneration) return;
  await saveBootstrap(assignmentNavigationSnapshot(overview.data.data, bootstrap));
}

export async function syncNavigation() {
  if (syncPaused) return;
  if (syncing) return syncing;
  if (Date.now() < retryAfter) return;
  const generation = sessionGeneration;
  syncController = new AbortController();
  const signal = syncController.signal;
  syncing = (async () => {
    try {
      const network = await Network.getNetworkStateAsync();
      if (!network.isConnected || network.isInternetReachable === false) return;
      // Send in order: a trip.start acknowledgement must persist its server ID
      // before any dependent point/end event can be sent, including after restart.
      for (let batch = 0; batch < 20; batch++) {
        if (syncPaused || generation !== sessionGeneration) return;
        const events = await getPendingOutboxEvents(25);
        if (!events.length) break;
        const ready = [];
        for (const event of events) {
          if (event.type === 'trip.start') {
            if (ready.length) break;
            ready.push(event); break;
          }
          if (event.type === 'trip.points' || event.type === 'trip.end') {
            const trip = await getLocalTrip(String(event.payload.local_trip_id));
            if (!trip?.serverTripId) break;
            ready.push({ ...event, payload: { ...event.payload, trip_id: trip.serverTripId } });
          } else ready.push(event);
        }
        if (!ready.length) break;
        const response = await api.post('/mobile/sync', { events: ready }, { signal });
        await acknowledgeOutbox(response.data.data.acks);
        if (response.data.data.acks.some((ack: any) => ack.status !== 'processed')) {
          throw new Error('A trip event was rejected. Its dependent events remain on this phone.');
        }
      }
      failures = 0; retryAfter = 0; lastNavigationError = '';
    } catch (error: any) {
      if (syncPaused || generation !== sessionGeneration) return;
      lastNavigationError = error.message;
      retryAfter = Date.now() + Math.min(300000, 2000 * 2 ** Math.min(failures++, 8));
    }
  })().finally(() => { syncing = null; });
  return syncing;
}

export async function startNavigationTrip(dispatchId: number, profile: 'auto' | 'pedestrian') {
  if (await activeLocalTrip()) throw new Error('End the current trip before starting another.');
  if (!(await Location.requestForegroundPermissionsAsync()).granted) throw new Error('Location permission is required.');
  if (!(await Location.requestBackgroundPermissionsAsync()).granted) throw new Error('Allow background location to record while the screen is locked.');
  const trip = await serial(async () => {
    return createLocalTrip(dispatchId, profile, new Date().toISOString());
  });
  try {
    // Stop the separate recorder used by older builds before reusing live GPS.
    if (await Location.hasStartedLocationUpdatesAsync(taskName)) await Location.stopLocationUpdatesAsync(taskName);
    if (!hasActiveRescuerTracking(dispatchId)) await configureRescuerTracking(dispatchId);
  } catch (error) {
    await endNavigationTrip();
    throw error;
  }
  void syncNavigation();
  return trip;
}

export async function endNavigationTrip({ sync = true } = {}) {
  if (await Location.hasStartedLocationUpdatesAsync(taskName)) await Location.stopLocationUpdatesAsync(taskName);
  await serial(async () => {
    const trip = await activeLocalTrip();
    if (!trip) return;
    await completeLocalTrip(trip.localTripId);
  });
  if (sync) void syncNavigation();
}

export async function suspendNavigationSync() {
  syncPaused = true;
  sessionGeneration++;
  syncController?.abort();
  await syncing;
}

let syncMounts = 0;
let stopSync: (() => void) | null = null;
export function mountNavigationSync() {
  syncPaused = false;
  retryAfter = 0; failures = 0;
  syncMounts++;
  if (stopSync) return () => { if (--syncMounts === 0) { stopSync?.(); stopSync = null; } };
  void Location.hasStartedLocationUpdatesAsync(taskName).then(async started => {
    if (started) await Location.stopLocationUpdatesAsync(taskName);
  }).catch(error => { lastNavigationError = error.message; });
  const foreground = AppState.addEventListener('change', (state) => {
    if (state === 'active') { void syncNavigation(); void refreshNavigation().catch(() => {}); }
  });
  const timer = setInterval(() => {
    void activeLocalTrip().then((trip) => { if (trip) void syncNavigation(); })
      .catch(error => { lastNavigationError = error.message; });
  }, 60000);
  void syncNavigation();
  void refreshNavigation().catch(() => {});
  stopSync = () => { foreground.remove(); clearInterval(timer); };
  return () => { if (--syncMounts === 0) { stopSync?.(); stopSync = null; } };
}
