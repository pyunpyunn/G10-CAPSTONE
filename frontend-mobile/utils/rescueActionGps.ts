import * as Location from 'expo-location';
import { getSavedGps, rememberGps } from './savedGps';

export async function rescueActionGps() {
  const saved = await getSavedGps();
  // Dispatch actions need a recent fix; evacuation-center browsing may use older saved GPS.
  if (saved && Date.now() - saved.timestamp < 30000) return saved;
  let timeout: ReturnType<typeof setTimeout> | undefined;
  try {
    const fix = await Promise.race([
      Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High }),
      new Promise<never>((_, reject) => {
        timeout = setTimeout(() => reject(new Error('GPS is taking too long. Move to an open area and retry.')), 8000);
      }),
    ]);
    if (!Number.isFinite(fix.coords.latitude) || !Number.isFinite(fix.coords.longitude)
      || Math.abs(fix.coords.latitude) > 90 || Math.abs(fix.coords.longitude) > 180
      || !Number.isFinite(fix.timestamp) || Date.now() - fix.timestamp >= 30000 || fix.timestamp > Date.now() + 5000
      || fix.coords.accuracy != null && fix.coords.accuracy > 75) {
      throw new Error('A recent, accurate GPS position is unavailable. Move to an open area and retry.');
    }
    void rememberGps(fix).catch(() => {});
    return { latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy };
  } finally {
    if (timeout) clearTimeout(timeout);
  }
}
