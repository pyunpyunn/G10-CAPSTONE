import { useEffect, useState } from 'react';
import { hasActiveRescuerTracking, subscribeRescuerPosition } from '@/utils/rescuerLiveLocation';
import { watchForegroundMapGps, type MapGpsPoint } from '@/utils/foregroundMapGps';

export function useMapGps(enabled: boolean, allowStandaloneGps = true) {
  const [position, setPosition] = useState<MapGpsPoint | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    setPosition(null);
    setError('');
    if (!enabled) return;
<<<<<<< HEAD
    const unsubscribe = subscribeRescuerPosition(fix => setPosition({ latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy }), setError);
    const stop = hasActiveRescuerTracking() || !allowStandaloneGps ? () => {} : watchForegroundMapGps(point => { if (!hasActiveRescuerTracking()) setPosition(point); }, problem => { if (!hasActiveRescuerTracking()) setError(problem); });
=======
    const unsubscribe = subscribeRescuerPosition(fix => {
      if (fix.coords.accuracy != null && fix.coords.accuracy > 75) {
        setError('GPS accuracy is limited. Move to an open area.');
        return;
      }
      setError('');
      setPosition({ latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy });
    }, setError);
    const stop = hasActiveRescuerTracking() ? () => {} : watchForegroundMapGps(point => { if (!hasActiveRescuerTracking()) setPosition(point); }, problem => { if (!hasActiveRescuerTracking()) setError(problem); });
>>>>>>> sub-main
    return () => { unsubscribe(); stop(); };
  }, [enabled, allowStandaloneGps]);
  return { position, error };
}
