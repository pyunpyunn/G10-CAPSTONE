import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import * as Network from 'expo-network';
import { AppState } from 'react-native';
import { api } from '@/api/client';
import { activeLocalTrip, addLocalTripPoint, acknowledgeOutbox, completeLocalTrip, createLocalTrip, getLocalTrip,
  getPendingOutboxEvents, saveBootstrap } from './navigationStore';

const taskName = 'resq-active-dispatch-navigation';
let work: Promise<unknown> = Promise.resolve();
let syncing: Promise<void> | null = null;
let retryAfter = 0, failures = 0;
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

export async function refreshNavigation() {
  // A complete team snapshot also removes households/routes that were reassigned.
  const response = await api.get('/mobile/bootstrap');
  await saveBootstrap(response.data.data);
}

export async function syncNavigation() {
  if (syncing) return syncing;
  if (Date.now() < retryAfter) return;
  syncing = (async () => {
    try {
      const network = await Network.getNetworkStateAsync();
      if (!network.isConnected || network.isInternetReachable === false) return;
      // Send in order: a trip.start acknowledgement must persist its server ID
      // before any dependent point/end event can be sent, including after restart.
      for (let batch = 0; batch < 20; batch++) {
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
        const response = await api.post('/mobile/sync', { events: ready });
        await acknowledgeOutbox(response.data.data.acks);
        if (response.data.data.acks.some((ack: any) => ack.status !== 'processed')) {
          throw new Error('A trip event was rejected. Its dependent events remain on this phone.');
        }
      }
      failures = 0; retryAfter = 0; lastNavigationError = '';
    } catch (error: any) {
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
    await Location.startLocationUpdatesAsync(taskName, {
      accuracy: Location.Accuracy.High, timeInterval: profile === 'pedestrian' ? 4000 : 3000,
      distanceInterval: profile === 'pedestrian' ? 5 : 10,
      pausesUpdatesAutomatically: true, activityType: profile === 'pedestrian' ? Location.ActivityType.Fitness : Location.ActivityType.AutomotiveNavigation,
      showsBackgroundLocationIndicator: true,
      foregroundService: { notificationTitle: 'RESQPERATION trip recording', notificationBody: 'Location is recorded during your active dispatch.', killServiceOnDestroy: false },
    });
  } catch (error) {
    await endNavigationTrip();
    throw error;
  }
  void syncNavigation();
  return trip;
}

export async function endNavigationTrip() {
  if (await Location.hasStartedLocationUpdatesAsync(taskName)) await Location.stopLocationUpdatesAsync(taskName);
  await serial(async () => {
    const trip = await activeLocalTrip();
    if (!trip) return;
    await completeLocalTrip(trip.localTripId);
  });
  void syncNavigation();
}

let syncMounts = 0;
let stopSync: (() => void) | null = null;
export function mountNavigationSync() {
  syncMounts++;
  if (stopSync) return () => { if (--syncMounts === 0) { stopSync?.(); stopSync = null; } };
  const foreground = AppState.addEventListener('change', (state) => {
    if (state === 'active') { void syncNavigation(); void refreshNavigation().catch(() => {}); }
  });
  const timer = setInterval(() => { void activeLocalTrip().then((trip) => { if (trip) void syncNavigation(); }); }, 60000);
  void syncNavigation();
  void refreshNavigation().catch(() => {});
  stopSync = () => { foreground.remove(); clearInterval(timer); };
  return () => { if (--syncMounts === 0) { stopSync?.(); stopSync = null; } };
}
