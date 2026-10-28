import Storage from './mobileSnapshotStorage';
import { CryptoDigestAlgorithm, digestStringAsync } from 'expo-crypto';
import { deleteStoredItem, getStoredItem, setStoredItem } from './secureStorage';

export type MobileSnapshot<T> = { value: T; savedAt: number };
const snapshotScopeKey = 'resqperation_mobile_snapshot_scope';
const tokenKey = 'resqperation_mobile_token';

export async function setMobileSnapshotIdentity(
  role: string,
  userId: string,
  householdId: string | undefined,
  serverUrl: string,
  migrateCurrentTokenSnapshots = false,
) {
  const identity = role === 'household_resident' ? householdId : userId;
  if (!identity) return;

  const fingerprint = await digestStringAsync(
    CryptoDigestAlgorithm.SHA256,
    `${serverUrl}|${role}|${identity}`,
  );
  const nextScope = `account:${fingerprint}`;
  const currentScope = await getStoredItem(snapshotScopeKey);
  const token = await getStoredItem(tokenKey);

  if (migrateCurrentTokenSnapshots && !currentScope && token) {
    const previousScope = await digestStringAsync(CryptoDigestAlgorithm.SHA256, token);
    for (const key of ['household', 'rescuer']) {
      const previous = readMobileSnapshot(previousScope, key);
      if (previous && !readMobileSnapshot(nextScope, key)) {
        writeMobileSnapshot(nextScope, key, previous);
      }
    }
  }

  await setStoredItem(snapshotScopeKey, fingerprint);
}

// Account identity keeps snapshots available when the server rotates session tokens.
export async function mobileSnapshotScope() {
  const identity = await getStoredItem(snapshotScopeKey);
  if (identity) return `account:${identity}`;
  const token = await getStoredItem(tokenKey);
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
  const identity = await getStoredItem(snapshotScopeKey);
  const token = await getStoredItem(tokenKey);
  const scopes = new Set<string>();
  if (identity) scopes.add(`account:${identity}`);
  if (token) scopes.add(await digestStringAsync(CryptoDigestAlgorithm.SHA256, token));
  for (const scope of scopes) {
    for (const key of ['household', 'rescuer']) Storage.removeItemSync(`mobile-snapshot:${scope}:${key}`);
  }
  await deleteStoredItem(snapshotScopeKey);
}
