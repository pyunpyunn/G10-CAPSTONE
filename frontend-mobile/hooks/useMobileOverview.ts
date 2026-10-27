import { useCallback, useRef, useState } from 'react';
import { copyWithStructuralSharing } from '@reduxjs/toolkit/query';
import { getViewState, saveViewState } from '@/state/apiStore';
import { createRefreshQueue } from '@/utils/networkScheduling';
import { mobileSnapshotScope, readMobileSnapshot, writeMobileSnapshot } from '@/utils/mobileSnapshot';

export function useMobileOverview<T>(key: string, read: (refresh?: boolean) => Promise<T>) {
  const cached = useRef(getViewState(`mobile:${key}`));
  const [overview, setOverview] = useState<T | null>(() => cached.current?.value || null);
  const current = useRef(overview);
  current.current = overview;
  const [loading, setLoading] = useState(!overview);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [offline, setOffline] = useState(false);
  const [lastSyncedAt, setLastSyncedAt] = useState<number | null>(cached.current?.savedAt || null);
  const queue = useRef(createRefreshQueue());
  const readVersion = useRef(0);

  const loadOverview = useCallback((isRefresh = false, silent = false, afterWrite = false): Promise<T | null> => {
    if (afterWrite) readVersion.current++;
    const version = readVersion.current;
    return queue.current.run(async () => {
      const scope = await mobileSnapshotScope().catch(() => null);
      if (!current.current && scope) {
        const saved = readMobileSnapshot<T>(scope, key);
        if (saved) {
          current.current = saved.value;
          setOverview(saved.value);
          setLastSyncedAt(saved.savedAt);
          setLoading(false);
        }
      }
      if (!silent) {
        if (current.current) setRefreshing(true);
        else setLoading(true);
      }
      try {
        const response = await read(isRefresh);
        if (version !== readVersion.current) return null;
        const data = copyWithStructuralSharing(current.current, response);
        current.current = data;
        setOverview(data);
        saveViewState(`mobile:${key}`, data);
        const savedAt = Date.now();
        setLastSyncedAt(savedAt);
        setOffline(false);
        if (scope && scope === await mobileSnapshotScope().catch(() => null)) {
          try { writeMobileSnapshot(scope, key, { value: data, savedAt }); }
          catch { console.warn('Could not save the offline page snapshot.'); }
        }
        setError('');
        return data;
      } catch (failure: any) {
        if (version === readVersion.current) {
          setError(failure.userMessage || failure.response?.data?.message || failure.message || 'Unable to refresh data.');
          setOffline(!failure.response || failure.response.status >= 500);
        }
        return null;
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    }, afterWrite || isRefresh && !silent);
  }, [key, read]);

  return { overview, setOverview, loading, refreshing, error, offline, lastSyncedAt, loadOverview };
}
