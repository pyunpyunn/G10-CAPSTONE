import { randomUUID } from 'expo-crypto';
import * as Network from 'expo-network';
import Storage from './mobileSnapshotStorage';
import { saveHouseholdMemberStatus, saveHouseholdStatus } from '@/api/household';
import { mobileSnapshotScope } from './mobileSnapshot';

type HouseholdStatusSubmission = {
  id: string;
  idempotencyKey: string;
  kind: 'household' | 'member';
  memberId?: string;
  expectedEventId: string;
  payload: Record<string, unknown>;
  createdAt: number;
  state: 'pending' | 'failed';
  lastError?: string;
};

export type HouseholdStatusOutboxCounts = { pending: number; failed: number };
export type HouseholdStatusSyncResult = {
  syncedIds: string[];
  failures: { id: string; message: string; retryable: boolean }[];
};

const storagePrefix = 'household-status-outbox:';
let storageTail: Promise<void> = Promise.resolve();
let activeSync: Promise<HouseholdStatusSyncResult> | null = null;

function serialized<T>(task: () => Promise<T>): Promise<T> {
  const result = storageTail.then(task, task);
  storageTail = result.then(() => undefined, () => undefined);
  return result;
}

async function scopedKey(): Promise<string> {
  const scope = await mobileSnapshotScope();
  if (!scope) throw new Error('Household status reports cannot be queued before account identity is available.');
  return `${storagePrefix}${scope}`;
}

async function readQueue(key: string): Promise<HouseholdStatusSubmission[]> {
  const raw = Storage.getItemSync(key);
  if (!raw) return [];
  const parsed: unknown = JSON.parse(raw);
  if (!Array.isArray(parsed)) throw new Error('Saved household status queue is corrupted and cannot be read safely.');
  return parsed as HouseholdStatusSubmission[];
}

async function updateQueue(
  key: string,
  update: (items: HouseholdStatusSubmission[]) => HouseholdStatusSubmission[],
): Promise<HouseholdStatusSubmission[]> {
  return serialized(async () => {
    const next = update(await readQueue(key));
    Storage.setItemSync(key, JSON.stringify(next));
    return next;
  });
}

export async function enqueueHouseholdStatusReport(input: {
  kind: HouseholdStatusSubmission['kind'];
  memberId?: string;
  expectedEventId: string;
  payload: Record<string, unknown>;
}): Promise<string> {
  const key = await scopedKey();
  const submission: HouseholdStatusSubmission = {
    id: randomUUID(),
    idempotencyKey: randomUUID(),
    kind: input.kind,
    memberId: input.memberId,
    expectedEventId: input.expectedEventId,
    payload: input.payload,
    createdAt: Date.now(),
    state: 'pending',
  };
  await updateQueue(key, (items) => [...items, submission]);
  return submission.id;
}

export async function getHouseholdStatusOutboxCounts(): Promise<HouseholdStatusOutboxCounts> {
  const key = await scopedKey();
  return serialized(async () => {
    const items = await readQueue(key);
    return {
      pending: items.filter((item) => item.state === 'pending').length,
      failed: items.filter((item) => item.state === 'failed').length,
    };
  });
}

export async function discardFailedHouseholdStatusReports(): Promise<number> {
  const key = await scopedKey();
  return serialized(async () => {
    const items = await readQueue(key);
    const remaining = items.filter((item) => item.state !== 'failed');
    Storage.setItemSync(key, JSON.stringify(remaining));
    return items.length - remaining.length;
  });
}

function failureDetails(error: any): { message: string; retryable: boolean } {
  const status = Number(error?.response?.status);
  const retryable = !status || [401, 408, 425, 429].includes(status) || status >= 500;
  const message = error?.response?.data?.message
    || error?.userMessage
    || error?.message
    || 'The status report could not be synchronized.';
  return { message: String(message), retryable };
}

async function synchronize(): Promise<HouseholdStatusSyncResult> {
  const key = await scopedKey();
  const result: HouseholdStatusSyncResult = { syncedIds: [], failures: [] };
  const network = await Network.getNetworkStateAsync();
  if (network.isConnected === false || network.isInternetReachable === false) return result;

  const items = await serialized(() => readQueue(key));
  for (const item of items.filter((candidate) => candidate.state === 'pending').sort((a, b) => a.createdAt - b.createdAt)) {
    try {
      const payload = { ...item.payload, expected_event_id: item.expectedEventId };
      if (item.kind === 'member' && item.memberId) {
        await saveHouseholdMemberStatus(item.memberId, payload, item.idempotencyKey);
      } else if (item.kind === 'household') {
        await saveHouseholdStatus(payload, item.idempotencyKey);
      } else {
        throw new Error('The saved household status report has no member ID.');
      }

      await updateQueue(key, (current) => current.filter((entry) => entry.id !== item.id));
      result.syncedIds.push(item.id);
    } catch (error: any) {
      const details = failureDetails(error);
      if (!details.retryable) {
        await updateQueue(key, (current) => current.map((entry) => entry.id === item.id
          ? { ...entry, state: 'failed', lastError: details.message }
          : entry));
      }
      result.failures.push({ id: item.id, ...details });
      if (details.retryable) break;
    }
  }
  return result;
}

export async function syncPendingHouseholdStatuses(): Promise<HouseholdStatusSyncResult> {
  // A report may have been queued after the running sync read its queue.
  while (activeSync) await activeSync;
  const sync = synchronize();
  activeSync = sync;
  try {
    return await sync;
  } finally {
    if (activeSync === sync) activeSync = null;
  }
}
