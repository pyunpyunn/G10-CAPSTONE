import { AppState } from 'react-native';
import * as Location from 'expo-location';

export type MapGpsPoint = { latitude: number; longitude: number; accuracy_m?: number | null };

// One foreground-only watch; cancellation also covers a pending permission/watch request.
export function watchForegroundMapGps(onPosition: (point: MapGpsPoint) => void, onError: (message: string) => void) {
  let disposed = false;
  let generation = 0;
  let watcher: Location.LocationSubscription | null = null;
  function stop() {
    generation += 1;
    watcher?.remove();
    watcher = null;
  }
  async function start() {
    stop();
    if (disposed || AppState.currentState !== 'active') return;
    const current = generation;
    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      if (disposed || current !== generation) return;
      if (permission.status !== 'granted') {
        onError('Allow location access to follow your GPS position.');
        return;
      }
      const subscription = await Location.watchPositionAsync({
        accuracy: Location.Accuracy.Balanced, timeInterval: 5000, distanceInterval: 5,
      }, (fix) => {
        const { latitude, longitude, accuracy } = fix.coords;
        if (disposed || current !== generation || !Number.isFinite(latitude) || !Number.isFinite(longitude)
          || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || (accuracy != null && accuracy > 75)) return;
        onError('');
        onPosition({ latitude, longitude, accuracy_m: accuracy });
      }, () => { if (!disposed && current === generation) onError('GPS unavailable. The selected route remains visible.'); });
      if (disposed || current !== generation) subscription.remove();
      else watcher = subscription;
    } catch {
      if (!disposed && current === generation) onError('GPS unavailable. The selected route remains visible.');
    }
  }
  const subscription = AppState.addEventListener('change', (state) => {
    if (state === 'active') void start(); else stop();
  });
  void start();
  return () => { disposed = true; stop(); subscription.remove(); };
}
