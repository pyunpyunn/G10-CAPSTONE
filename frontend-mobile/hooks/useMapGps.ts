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
    const unsubscribe = subscribeRescuerPosition(fix => setPosition({ latitude: fix.coords.latitude, longitude: fix.coords.longitude, accuracy_m: fix.coords.accuracy }), setError);
    const stop = hasActiveRescuerTracking() || !allowStandaloneGps ? () => {} : watchForegroundMapGps(point => { if (!hasActiveRescuerTracking()) setPosition(point); }, problem => { if (!hasActiveRescuerTracking()) setError(problem); });
    return () => { unsubscribe(); stop(); };
  }, [enabled, allowStandaloneGps]);
  return { position, error };
}
