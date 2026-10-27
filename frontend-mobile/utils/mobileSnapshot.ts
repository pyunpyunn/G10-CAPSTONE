import Storage from './mobileSnapshotStorage';
import { CryptoDigestAlgorithm, digestStringAsync } from 'expo-crypto';
import { getStoredItem } from './secureStorage';

export type MobileSnapshot<T> = { value: T; savedAt: number };

// Token fingerprints isolate accounts, household owners, and server sessions.
export async function mobileSnapshotScope() {
  const token = await getStoredItem('resqperation_mobile_token');
  return token ? digestStringAsync(CryptoDigestAlgorithm.SHA256, token) : null;
}

export function readMobileSnapshot<T>(scope: string, key: string): MobileSnapshot<T> | null {
  try {
    const raw = Storage.getItemSync(`mobile-snapshot:${scope}:${key}`);
    const saved = raw ? JSON.parse(raw) : null;
    return saved?.value && Number.isFinite(saved.savedAt) ? saved : null;
  } catch { return null; }
}

export function writeMobileSnapshot<T>(scope: string, key: string, snapshot: MobileSnapshot<T>) {
  Storage.setItemSync(`mobile-snapshot:${scope}:${key}`, JSON.stringify(snapshot));
}

export async function clearMobileSnapshots() {
  const scope = await mobileSnapshotScope();
  if (!scope) return;
  for (const key of ['household', 'rescuer']) Storage.removeItemSync(`mobile-snapshot:${scope}:${key}`);
}
