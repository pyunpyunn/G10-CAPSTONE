import { digestStringAsync, CryptoDigestAlgorithm, randomUUID } from 'expo-crypto';
import { api } from './client';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';

const inFlight = new Map<string, Promise<any>>();

function canonical(value: any): any {
  if (Array.isArray(value)) return value.map(canonical);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical(value[key])]));
  }
  return value;
}

// A retry of the same report keeps its key across timeouts and app restarts.
// Successful submissions clear the key so a later intentional report is a new action.
export async function submitReport(method: 'post' | 'patch', path: string, payload: any = {}) {
  const token = await getStoredItem('resqperation_mobile_token');
  const fingerprint = await digestStringAsync(CryptoDigestAlgorithm.SHA256,
    JSON.stringify([token, method, path, canonical(payload)]));
  const existing = inFlight.get(fingerprint);
  if (existing) return existing;
  const submission = deliver(method, path, payload, fingerprint);
  inFlight.set(fingerprint, submission);
  try { return await submission; }
  finally { inFlight.delete(fingerprint); }
}

async function deliver(method: 'post' | 'patch', path: string, payload: any, fingerprint: string) {
  const storageKey = `report_key_${fingerprint}`;
  const saved = await getStoredItem(storageKey);
  let key: string;
  let createdAt = Date.now();
  try {
    const record = saved ? JSON.parse(saved) : null;
    if (record && Date.now() - record.createdAt < 6 * 86400000) {
      key = record.key;
      createdAt = record.createdAt;
    } else key = randomUUID();
  } catch { key = randomUUID(); }
  await setStoredItem(storageKey, JSON.stringify({ key, createdAt }));
  for (let attempt = 0; ; attempt += 1) {
    try {
      const response = await api.request({ method, url: path, data: payload, headers: { 'Idempotency-Key': key } });
      // Failure to clear local storage must not turn a successful save into an error.
      await deleteStoredItem(storageKey).catch(() => {});
      return response;
    } catch (failure: any) {
      const status = failure.response?.status;
      const retryable = !failure.response || [408, 429, 500, 502, 503, 504].includes(status);
      if (!retryable) await deleteStoredItem(storageKey).catch(() => {});
      if (!retryable || attempt >= 2) throw failure;
      const retryAfter = Number(failure.response?.headers?.['retry-after']);
      const delay = Number.isFinite(retryAfter) && retryAfter > 0
        ? Math.min(30000, retryAfter * 1000) : (500 * 2 ** attempt) * (0.8 + Math.random() * 0.4);
      await new Promise((resolve) => setTimeout(resolve, delay));
    }
  }
}
