// Session memory only: never persist emergency data or authentication tokens.
import { invalidateApiState } from '@/state/apiStore';
const listeners = new Set<() => void>();

export function invalidateMobileReads() {
  invalidateApiState();
}

export function notifyDisasterBroadcast() {
  invalidateMobileReads();
  listeners.forEach((listener) => listener());
}

export function onDisasterBroadcast(listener: () => void) {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
}

export async function cachedMobileRead<T>(_key: string, load: () => Promise<T>, refresh = false): Promise<T> {
  if (refresh) invalidateMobileReads();
  return load();
}
