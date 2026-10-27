import { useEffect, useState } from 'react';
import { hasActiveRescuerTracking, subscribeRescuerPosition } from '@/utils/rescuerLiveLocation';
import { watchForegroundMapGps, type MapGpsPoint } from '@/utils/foregroundMapGps';
import { getSavedGps, subscribeSavedGps } from '@/utils/savedGps';

export function useMapGps(enabled: boolean, allowStandaloneGps = true, useSavedPosition = false) {
  const [position, setPosition] = useState<MapGpsPoint | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    if (!useSavedPosition) setPosition(null);
    setError('');
    let active = true;
    const stopSaved = useSavedPosition ? subscribeSavedGps(setPosition) : () => {};
    if (useSavedPosition) void getSavedGps().then(point => { if (active && point) setPosition(point); });
    if (!enabled) return () => { active = false; stopSaved(); };
    const unsubscribe = subscribeRescuerPosition(fix => {
      if (fix.coords.accuracy != null && fix.coords.accuracy > 75) {
        setError('GPS accuracy is limited. Move to an open area.');
        return;
      }
      setError('');
      setPosition({ latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy });
    }, setError);
    const stop = hasActiveRescuerTracking() || !allowStandaloneGps
      ? () => {}
      : watchForegroundMapGps(
        point => { if (!hasActiveRescuerTracking()) setPosition(point); },
        problem => { if (!hasActiveRescuerTracking()) setError(problem); },
      );
    return () => { active = false; stopSaved(); unsubscribe(); stop(); };
  }, [enabled, allowStandaloneGps, useSavedPosition]);
  return { position, error };
}
