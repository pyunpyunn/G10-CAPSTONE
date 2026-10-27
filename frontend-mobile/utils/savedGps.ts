import * as Location from 'expo-location';
import { getStoredItem, setStoredItem } from './secureStorage';

export type SavedGpsPoint = { latitude: number; longitude: number; accuracy_m?: number | null; timestamp: number };
const key = 'resq_last_device_gps';
let latest: SavedGpsPoint | null = null;
let writing: Promise<unknown> = Promise.resolve();
const listeners = new Set<(point: SavedGpsPoint) => void>();

function valid(point: SavedGpsPoint): boolean {
  return Number.isFinite(point?.latitude) && Math.abs(point.latitude) <= 90
    && Number.isFinite(point?.longitude) && Math.abs(point.longitude) <= 180
    && Number.isFinite(point?.timestamp) && point.timestamp > 0 && point.timestamp <= Date.now() + 5000
    && (point.accuracy_m == null || Number.isFinite(point.accuracy_m) && point.accuracy_m >= 0 && point.accuracy_m <= 75);
}

export function rememberGps(fix: Location.LocationObject): Promise<unknown> {
  const point = { latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy, timestamp: fix.timestamp };
  if (!valid(point) || latest && point.timestamp <= latest.timestamp) return writing;
  latest = point;
  listeners.forEach(listener => { try { listener(point); } catch { /* Keep saving GPS if a screen has detached. */ } });
  writing = writing.catch(() => {}).then(() => setStoredItem(key, JSON.stringify(point)));
  return writing;
}

export async function getSavedGps(): Promise<SavedGpsPoint | null> {
  if (latest) return latest;
  try {
    const value = await getStoredItem(key);
    const point = value ? JSON.parse(value) : null;
    const current = latest as SavedGpsPoint | null;
    if (valid(point) && (!current || point.timestamp > current.timestamp)) latest = point;
    if (latest) return latest;
  } catch { /* Keep trying the OS cache if app storage is unavailable. */ }
  try {
    // Reads the OS cache without starting GPS or requesting a new fix.
    const fix = await Location.getLastKnownPositionAsync();
    if (fix) await rememberGps(fix);
  } catch { /* Keep the saved position when GPS or permissions are unavailable. */ }
  return latest;
}

export function subscribeSavedGps(listener: (point: SavedGpsPoint) => void) {
  listeners.add(listener);
  if (latest) listener(latest);
  return () => { listeners.delete(listener); };
}
