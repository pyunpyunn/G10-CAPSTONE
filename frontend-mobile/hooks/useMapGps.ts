import { useEffect, useState } from 'react';
import { watchForegroundMapGps, type MapGpsPoint } from '@/utils/foregroundMapGps';

export function useMapGps(enabled: boolean) {
  const [position, setPosition] = useState<MapGpsPoint | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    setPosition(null);
    setError('');
    if (!enabled) return;
    return watchForegroundMapGps(setPosition, setError);
  }, [enabled]);
  return { position, error };
}
