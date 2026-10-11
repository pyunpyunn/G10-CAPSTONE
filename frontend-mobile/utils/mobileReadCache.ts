// Session memory only: never persist emergency data or authentication tokens.
const entries = new Map<string, { expires: number; value: unknown }>();
let generation = 0;
const listeners = new Set<() => void>();

export function invalidateMobileReads() {
  generation += 1;
  entries.clear();
}

export function notifyDisasterBroadcast() {
  invalidateMobileReads();
  listeners.forEach((listener) => listener());
}

export function onDisasterBroadcast(listener: () => void) {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
}

export async function cachedMobileRead<T>(key: string, load: () => Promise<T>, refresh = false): Promise<T> {
  const entry = entries.get(key);
  if (!refresh && entry && entry.expires > Date.now()) return entry.value as T;
  const started = generation;
  const value = await load();
  if (started === generation) {
    if (entries.size >= 50) entries.delete(entries.keys().next().value!);
    entries.set(key, { value, expires: Date.now() + 5000 });
  }
  return value;
}
