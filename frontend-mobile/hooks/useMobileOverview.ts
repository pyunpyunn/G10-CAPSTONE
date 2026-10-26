import { useCallback, useRef, useState } from 'react';
import { copyWithStructuralSharing } from '@reduxjs/toolkit/query';
import { getViewState, saveViewState } from '@/state/apiStore';
import { createRefreshQueue } from '@/utils/networkScheduling';

export function useMobileOverview<T>(key: string, read: (refresh?: boolean) => Promise<T>) {
  const cached = useRef(getViewState(`mobile:${key}`));
  const [overview, setOverview] = useState<T | null>(() => cached.current?.value || null);
  const current = useRef(overview);
  current.current = overview;
  const [loading, setLoading] = useState(!overview);
  const [refreshing, setRefreshing] = useState(false);
  const [fetching, setFetching] = useState(false);
  const [error, setError] = useState('');
  const [lastUpdated, setLastUpdated] = useState(cached.current?.savedAt || 0);
  const queue = useRef(createRefreshQueue());
  const readVersion = useRef(0);

  const loadOverview = useCallback((isRefresh = false, silent = false, afterWrite = false): Promise<T | null> => {
    if (afterWrite) readVersion.current++;
    const version = readVersion.current;
    return queue.current.run(async () => {
      setFetching(true);
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
        setLastUpdated(Date.now());
        setError('');
        return data;
      } catch (failure: any) {
        if (version === readVersion.current) setError(failure.userMessage || failure.response?.data?.message || failure.message || 'Unable to refresh data.');
        return null;
      } finally {
        setLoading(false);
        setRefreshing(false);
        setFetching(false);
      }
    }, afterWrite || isRefresh && !silent);
  }, [key, read]);

  return { overview, setOverview, loading, refreshing, fetching, error, lastUpdated, loadOverview };
}
