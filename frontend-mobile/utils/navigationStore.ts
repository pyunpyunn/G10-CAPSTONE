import * as SQLite from 'expo-sqlite';
import { Platform } from 'react-native';
import * as Crypto from 'expo-crypto';
import { getStoredItem, setStoredItem } from './secureStorage';

const databaseName = 'resq_navigation.db';
const maxLocalTrips = 100;
let databasePromise: Promise<SQLite.SQLiteDatabase> | null = null;

export type LocalTrip = {
  localTripId: string;
  serverTripId?: number;
  dispatchId: number;
  profile: 'auto' | 'pedestrian';
  startedAt: string;
  endedAt?: string;
  distanceM: number;
  status: 'recording' | 'completed';
};

export type OutboxEvent = {
  client_event_id: string;
  type: 'trip.start' | 'trip.points' | 'trip.end' | 'household.status';
  occurred_at: string;
  payload: Record<string, unknown>;
};

async function database() {
  if (Platform.OS === 'web') {
    throw new Error('Offline navigation storage is available in the native mobile app only.');
  }

  if (!databasePromise) {
    databasePromise = getStoredItem('resq_navigation_database').then((name) => SQLite.openDatabaseAsync(name || databaseName)).then(async (db) => {
      try {
        await db.execAsync('PRAGMA busy_timeout = 5000; PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON;');
        await db.execAsync(`
          CREATE TABLE IF NOT EXISTS metadata (
            key TEXT PRIMARY KEY NOT NULL,
            value TEXT NOT NULL
          );
          CREATE TABLE IF NOT EXISTS households (
            household_id TEXT PRIMARY KEY NOT NULL,
            payload_json TEXT NOT NULL
          );
          CREATE TABLE IF NOT EXISTS dispatches (
            dispatch_id INTEGER PRIMARY KEY NOT NULL,
            payload_json TEXT NOT NULL
          );
          CREATE TABLE IF NOT EXISTS routes (
            team_id INTEGER NOT NULL,
            target_key TEXT NOT NULL,
            profile TEXT NOT NULL,
            status TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            PRIMARY KEY (team_id, target_key, profile)
          );
          CREATE TABLE IF NOT EXISTS evacuation_centers (
            center_id TEXT PRIMARY KEY NOT NULL,
            payload_json TEXT NOT NULL
          );
          CREATE TABLE IF NOT EXISTS center_route_cache (
            center_id TEXT NOT NULL,
            profile TEXT NOT NULL,
            origin_latitude REAL NOT NULL,
            origin_longitude REAL NOT NULL,
            destination_latitude REAL NOT NULL,
            destination_longitude REAL NOT NULL,
            route_json TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (center_id, profile)
          );
          CREATE INDEX IF NOT EXISTS center_route_cache_updated_idx ON center_route_cache(updated_at);
          CREATE TABLE IF NOT EXISTS outbox (
            client_event_id TEXT PRIMARY KEY NOT NULL,
            type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            occurred_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            error_message TEXT
          );
          CREATE INDEX IF NOT EXISTS outbox_pending_created_idx ON outbox(status, created_at);
          CREATE TABLE IF NOT EXISTS local_trips (
            local_trip_id TEXT PRIMARY KEY NOT NULL,
            server_trip_id INTEGER,
            dispatch_id INTEGER NOT NULL,
            profile TEXT NOT NULL,
            started_at TEXT NOT NULL,
            ended_at TEXT,
            distance_m REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL
          );
          CREATE INDEX IF NOT EXISTS local_trips_started_idx ON local_trips(started_at DESC);
          CREATE TABLE IF NOT EXISTS local_trip_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            local_trip_id TEXT NOT NULL,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            accuracy REAL,
            speed REAL,
            heading REAL,
            recorded_at TEXT NOT NULL,
            FOREIGN KEY (local_trip_id) REFERENCES local_trips(local_trip_id) ON DELETE CASCADE
          );
          CREATE INDEX IF NOT EXISTS local_trip_points_trip_recorded_idx ON local_trip_points(local_trip_id, recorded_at);
        `);
        return db;
      } catch (error) {
        await db.closeAsync().catch(() => {});
        throw error;
      }
    }).catch((error) => {
      databasePromise = null;
      throw error;
    });
  }

  return databasePromise;
}

async function saveBootstrapStored(snapshot: any) {
  const db = await database();
  await withNavigationTransaction(db, async (transaction) => {
    if (snapshot.full_snapshot) {
      await transaction.execAsync('DELETE FROM households; DELETE FROM dispatches; DELETE FROM routes; DELETE FROM evacuation_centers;');
    }

    for (const household of snapshot.households || []) {
      await transaction.runAsync(
        'INSERT OR REPLACE INTO households (household_id, payload_json) VALUES (?, ?)',
        String(household.household_id), JSON.stringify(household),
      );
    }

    for (const geotag of snapshot.geotags || []) {
      const household = await transaction.getFirstAsync<{ payload_json: string }>(
        'SELECT payload_json FROM households WHERE household_id = ?', String(geotag.household_id),
      );
      if (household) {
        await transaction.runAsync(
          'UPDATE households SET payload_json = ? WHERE household_id = ?',
          JSON.stringify({ ...JSON.parse(household.payload_json), geotag }), String(geotag.household_id),
        );
      }
    }

    for (const dispatch of snapshot.dispatches || []) {
      await transaction.runAsync(
        'INSERT OR REPLACE INTO dispatches (dispatch_id, payload_json) VALUES (?, ?)',
        Number(dispatch.assignment_id), JSON.stringify(dispatch),
      );
    }

    for (const route of snapshot.routes || []) {
      const targetKey = route.household_id ? `household:${route.household_id}` : `center:${route.evac_center_id}`;
      await transaction.runAsync(
        'INSERT OR REPLACE INTO routes (team_id, target_key, profile, status, payload_json) VALUES (?, ?, ?, ?, ?)',
        Number(route.team_id), targetKey, route.profile, route.status, JSON.stringify(route),
      );
    }

    for (const center of snapshot.evacuation_centers || []) {
      await transaction.runAsync(
        'INSERT OR REPLACE INTO evacuation_centers (center_id, payload_json) VALUES (?, ?)',
        String(center.evacuation_center_id), JSON.stringify(center),
      );
    }

    await transaction.runAsync(
      'INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)',
      'bootstrap_cursor', String(snapshot.cursor || ''),
    );
    await transaction.runAsync(
      'INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)',
      'active_pack', JSON.stringify(snapshot.pack || null),
    );
    await transaction.runAsync(
      'INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)',
      'team', JSON.stringify(snapshot.team || null),
    );
    if (snapshot.status_options) await transaction.runAsync('INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)',
      'status_options', JSON.stringify(snapshot.status_options));
  });
}

async function getBootstrapCursorStored(): Promise<string | null> {
  const db = await database();
  const row = await db.getFirstAsync<{ value: string }>('SELECT value FROM metadata WHERE key = ?', 'bootstrap_cursor');
  return row?.value || null;
}

async function getActivePackStored(): Promise<any | null> {
  const db = await database();
  const row = await db.getFirstAsync<{ value: string }>('SELECT value FROM metadata WHERE key = ?', 'active_pack');
  return row?.value ? JSON.parse(row.value) : null;
}

async function getLocalTeamStored(): Promise<any | null> {
  const db = await database();
  const row = await db.getFirstAsync<{ value: string }>('SELECT value FROM metadata WHERE key = ?', 'team');
  return row?.value ? JSON.parse(row.value) : null;
}

async function getCachedCenterRouteStored(centerId: string, profile: 'auto' | 'pedestrian') {
  const db = await database();
  const row = await db.getFirstAsync<{
    center_id: string;
    profile: 'auto' | 'pedestrian';
    origin_latitude: number;
    origin_longitude: number;
    destination_latitude: number;
    destination_longitude: number;
    route_json: string;
    updated_at: string;
  }>('SELECT * FROM center_route_cache WHERE center_id = ? AND profile = ?', centerId, profile);
  if (!row) return null;
  return {
    centerId: row.center_id,
    profile: row.profile,
    origin: { latitude: Number(row.origin_latitude), longitude: Number(row.origin_longitude) },
    destination: { latitude: Number(row.destination_latitude), longitude: Number(row.destination_longitude) },
    route: JSON.parse(row.route_json),
    updatedAt: row.updated_at,
  };
}

async function saveCachedCenterRouteStored(
  centerId: string,
  profile: 'auto' | 'pedestrian',
  origin: { latitude: number; longitude: number },
  destination: { latitude: number; longitude: number },
  route: Record<string, unknown>,
) {
  if (!centerId || !Number.isFinite(origin.latitude) || !Number.isFinite(origin.longitude)
    || !Number.isFinite(destination.latitude) || !Number.isFinite(destination.longitude)) {
    throw new Error('Cannot cache an evacuation-center route with invalid coordinates.');
  }
  const db = await database();
  await db.runAsync(
    `INSERT OR REPLACE INTO center_route_cache
      (center_id, profile, origin_latitude, origin_longitude, destination_latitude, destination_longitude, route_json, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    centerId, profile, origin.latitude, origin.longitude, destination.latitude, destination.longitude,
    JSON.stringify(route), new Date().toISOString(),
  );
}

async function getLocalNavigationSnapshotStored() {
  const db = await database();
  const [team, households, dispatchRows, routeRows, centerRows] = await Promise.all([
    getLocalTeamStored(),
    db.getAllAsync<{ payload_json: string }>('SELECT payload_json FROM households'),
    db.getAllAsync<{ payload_json: string }>('SELECT payload_json FROM dispatches ORDER BY dispatch_id DESC'),
    db.getAllAsync<{ payload_json: string }>('SELECT payload_json FROM routes'),
    db.getAllAsync<{ payload_json: string }>('SELECT payload_json FROM evacuation_centers'),
  ]);

  return {
    team,
    status_options: JSON.parse(await navigationMetadataStored('status_options') || '[]'),
    households: households.map((row) => JSON.parse(row.payload_json)),
    dispatches: dispatchRows.map((row) => JSON.parse(row.payload_json)),
    routes: routeRows.map((row) => JSON.parse(row.payload_json)),
    evacuation_centers: centerRows.map((row) => JSON.parse(row.payload_json)),
  };
}

async function queueOutboxEventStored(event: OutboxEvent) {
  const db = await database();
  await db.runAsync(
    'INSERT OR IGNORE INTO outbox (client_event_id, type, payload_json, occurred_at, created_at, status) VALUES (?, ?, ?, ?, ?, ?)',
    event.client_event_id, event.type, JSON.stringify(event.payload), event.occurred_at, new Date().toISOString(), 'pending',
  );
}

async function getPendingOutboxEventsStored(limit = 25): Promise<OutboxEvent[]> {
  const db = await database();
  const rows = await db.getAllAsync<{ client_event_id: string; type: OutboxEvent['type']; payload_json: string; occurred_at: string }>(
    'SELECT client_event_id, type, payload_json, occurred_at FROM outbox WHERE status = ? ORDER BY created_at LIMIT ?',
    'pending', Math.max(1, Math.min(limit, 50)),
  );
  return rows.map((row) => ({
    client_event_id: row.client_event_id,
    type: row.type,
    payload: JSON.parse(row.payload_json),
    occurred_at: row.occurred_at,
  }));
}

async function acknowledgeOutboxStored(acks: Array<{ client_event_id: string; status: string; error?: string; result?: { trip_id?: number } }>) {
  const db = await database();
  await withNavigationTransaction(db, async (transaction) => {
    for (const ack of acks) {
      if (ack.status === 'processed') {
        const event = await transaction.getFirstAsync<{ type: string; payload_json: string }>(
          'SELECT type, payload_json FROM outbox WHERE client_event_id = ?', ack.client_event_id,
        );
        if (event?.type === 'trip.start' && ack.result?.trip_id) {
          await transaction.runAsync('UPDATE local_trips SET server_trip_id = ? WHERE local_trip_id = ?',
            ack.result.trip_id, String(JSON.parse(event.payload_json).local_trip_id));
        }
        await transaction.runAsync('DELETE FROM outbox WHERE client_event_id = ?', ack.client_event_id);
      } else {
        await transaction.runAsync(
          'UPDATE outbox SET status = ?, error_message = ? WHERE client_event_id = ?',
          'failed', ack.error || 'Server rejected this event.', ack.client_event_id,
        );
      }
    }
  });
}

async function createLocalTripStored(dispatchId: number, profile: 'auto' | 'pedestrian', startedAt: string): Promise<LocalTrip> {
  const db = await database();
  const localTripId = Crypto.randomUUID();
  await withNavigationTransaction(db, async (transaction) => {
  await transaction.runAsync(
    'INSERT INTO local_trips (local_trip_id, dispatch_id, profile, started_at, distance_m, status) VALUES (?, ?, ?, ?, ?, ?)',
    localTripId, dispatchId, profile, startedAt, 0, 'recording',
  );
  await transaction.runAsync('INSERT INTO outbox (client_event_id, type, payload_json, occurred_at, created_at) VALUES (?, ?, ?, ?, ?)',
    Crypto.randomUUID(), 'trip.start', JSON.stringify({ local_trip_id: localTripId, dispatch_id: dispatchId, profile }), startedAt, startedAt);
  });
  await pruneLocalTrips(db);
  return { localTripId, dispatchId, profile, startedAt, distanceM: 0, status: 'recording' };
}

async function getLocalTripStored(localTripId: string): Promise<LocalTrip | null> {
  const db = await database();
  const row = await db.getFirstAsync<any>('SELECT * FROM local_trips WHERE local_trip_id = ?', localTripId);
  return row ? {
    localTripId: row.local_trip_id,
    serverTripId: row.server_trip_id ?? undefined,
    dispatchId: Number(row.dispatch_id),
    profile: row.profile,
    startedAt: row.started_at,
    endedAt: row.ended_at ?? undefined,
    distanceM: Number(row.distance_m || 0),
    status: row.status,
  } : null;
}

async function addLocalTripPointStored(localTripId: string, point: {
  latitude: number; longitude: number; accuracy: number | null; speed: number | null; heading: number | null; recordedAt: string;
}) {
  const db = await database();
  const trip = await db.getFirstAsync<{ profile: 'auto' | 'pedestrian' }>(
    "SELECT profile FROM local_trips WHERE local_trip_id = ? AND status = 'recording'", localTripId,
  );
  if (!trip || !Number.isFinite(point.latitude) || !Number.isFinite(point.longitude)
    || Math.abs(point.latitude) > 90 || Math.abs(point.longitude) > 180
    || !Number.isFinite(Date.parse(point.recordedAt)) || point.accuracy === null || point.accuracy > 30) {
    return { accepted: false, distanceM: 0 };
  }

  const maxSpeedMs = trip.profile === 'auto' ? 45 : 3.5;
  if (point.speed !== null && point.speed > maxSpeedMs) {
    return { accepted: false, distanceM: 0 };
  }

  const previous = await db.getFirstAsync<{ latitude: number; longitude: number; recorded_at: string }>(
    'SELECT latitude, longitude, recorded_at FROM local_trip_points WHERE local_trip_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1',
    localTripId,
  );
  let distanceM = 0;
  if (previous) {
    const elapsedS = (Date.parse(point.recordedAt) - Date.parse(previous.recorded_at)) / 1000;
    if (elapsedS <= 0) return { accepted: false, distanceM: 0 };
    distanceM = haversineMeters(previous.latitude, previous.longitude, point.latitude, point.longitude);
    if (distanceM / elapsedS > maxSpeedMs) return { accepted: false, distanceM: 0 };
  }

  const minimumStepM = trip.profile === 'auto' ? Math.max(7, Math.min(25, (point.speed || 0) * 2)) : 3;
  if (previous && distanceM < minimumStepM) return { accepted: false, distanceM: 0 };
  let pointId = 0;
  await withNavigationTransaction(db, async (transaction) => {
  const result = await transaction.runAsync(
    'INSERT INTO local_trip_points (local_trip_id, latitude, longitude, accuracy, speed, heading, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
    localTripId, point.latitude, point.longitude, point.accuracy, point.speed, point.heading, point.recordedAt,
  );
  if (distanceM >= minimumStepM) {
    await transaction.runAsync('UPDATE local_trips SET distance_m = distance_m + ? WHERE local_trip_id = ?', distanceM, localTripId);
  }
  await transaction.runAsync('INSERT INTO outbox (client_event_id, type, payload_json, occurred_at, created_at) VALUES (?, ?, ?, ?, ?)',
    Crypto.randomUUID(), 'trip.points', JSON.stringify({ local_trip_id: localTripId, points: [{ lat: point.latitude, lng: point.longitude,
      accuracy: point.accuracy, speed: point.speed !== null && point.speed >= 0 ? point.speed : null,
      heading: point.heading !== null && point.heading >= 0 ? point.heading : null, recorded_at: point.recordedAt }] }), point.recordedAt, new Date().toISOString());
  pointId = result.lastInsertRowId;
  });
  return { accepted: true, distanceM: distanceM >= minimumStepM ? distanceM : 0, pointId };
}

async function completeLocalTripStored(localTripId: string) {
  const db = await database(), endedAt = new Date().toISOString();
  await withNavigationTransaction(db, async (transaction) => {
    await transaction.runAsync("UPDATE local_trips SET ended_at = ?, status = 'completed' WHERE local_trip_id = ?", endedAt, localTripId);
    await transaction.runAsync('INSERT INTO outbox (client_event_id, type, payload_json, occurred_at, created_at) VALUES (?, ?, ?, ?, ?)',
      Crypto.randomUUID(), 'trip.end', JSON.stringify({ local_trip_id: localTripId }), endedAt, endedAt);
  });
}

async function updateLocalTripStored(localTripId: string, updates: { serverTripId?: number; endedAt?: string; distanceM?: number; status?: string }) {
  const db = await database();
  await db.runAsync(
    'UPDATE local_trips SET server_trip_id = COALESCE(?, server_trip_id), ended_at = COALESCE(?, ended_at), distance_m = COALESCE(?, distance_m), status = COALESCE(?, status) WHERE local_trip_id = ?',
    updates.serverTripId ?? null, updates.endedAt ?? null, updates.distanceM ?? null, updates.status ?? null, localTripId,
  );
}

async function getLocalTripPointsStored(localTripId: string) {
  const db = await database();
  return db.getAllAsync<any>('SELECT latitude, longitude, accuracy, speed, heading, recorded_at FROM local_trip_points WHERE local_trip_id = ? ORDER BY recorded_at, id', localTripId);
}

async function pendingEventCountStored() {
  const db = await database();
  const row = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM outbox');
  return Number(row?.count || 0);
}

async function navigationMetadataStored(key: string, value?: string | null): Promise<string | null> {
  const db = await database();
  if (value === null) await db.runAsync('DELETE FROM metadata WHERE key = ?', key);
  else if (value !== undefined) await db.runAsync('INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)', key, value);
  const row = await db.getFirstAsync<{ value: string }>('SELECT value FROM metadata WHERE key = ?', key);
  return row?.value ?? null;
}

async function activeLocalTripStored(): Promise<LocalTrip | null> {
  const db = await database();
  const row = await db.getFirstAsync<{ local_trip_id: string }>("SELECT local_trip_id FROM local_trips WHERE status = 'recording' LIMIT 1");
  return row ? getLocalTripStored(row.local_trip_id) : null;
}

async function clearNavigationStorageStored() {
  const db = await database();
  await db.execAsync('DELETE FROM outbox; DELETE FROM local_trip_points; DELETE FROM local_trips; DELETE FROM households; DELETE FROM routes; DELETE FROM dispatches; DELETE FROM evacuation_centers; DELETE FROM metadata;');
}

async function pruneLocalTrips(db: SQLite.SQLiteDatabase) {
  await db.runAsync(`
    DELETE FROM local_trips
    WHERE status != 'recording'
      AND local_trip_id IN (
        SELECT local_trip_id FROM local_trips
        WHERE status != 'recording'
        ORDER BY started_at DESC
        LIMIT -1 OFFSET ?
      )
      AND NOT EXISTS (SELECT 1 FROM outbox WHERE payload_json LIKE '%' || local_trips.local_trip_id || '%')
  `, maxLocalTrips);
}

function haversineMeters(latitude1: number, longitude1: number, latitude2: number, longitude2: number) {
  const earthRadiusM = 6371000;
  const latitudeDelta = ((latitude2 - latitude1) * Math.PI) / 180;
  const longitudeDelta = ((longitude2 - longitude1) * Math.PI) / 180;
  const a = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos((latitude1 * Math.PI) / 180)
      * Math.cos((latitude2 * Math.PI) / 180)
      * Math.sin(longitudeDelta / 2) ** 2;
  return earthRadiusM * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
}

// Route refresh, GPS recording, sync acknowledgements and logout all share this
// queue. Internal Stored calls stay inside the current operation rather than
// enqueueing themselves, so nested reads cannot deadlock the queue.
let storageWork: Promise<void> = Promise.resolve();
function serializeStoreOperation<Args extends unknown[], Result>(operation: (...args: Args) => Promise<Result>) {
  return (...args: Args): Promise<Result> => {
    const next = storageWork.then(() => operation(...args));
    storageWork = next.then(() => {}, () => {});
    return next;
  };
}

async function withNavigationTransaction(db: SQLite.SQLiteDatabase, task: (transaction: SQLite.SQLiteDatabase) => Promise<void>) {
  // All public store access is serialized, so a transaction on this connection
  // cannot include unrelated queries. Reuse its busy timeout and foreign keys.
  await db.withTransactionAsync(() => task(db));
}

export const saveBootstrap = serializeStoreOperation(saveBootstrapStored);
export const getBootstrapCursor = serializeStoreOperation(getBootstrapCursorStored);
export const getActivePack = serializeStoreOperation(getActivePackStored);
export const getLocalTeam = serializeStoreOperation(getLocalTeamStored);
export const getCachedCenterRoute = serializeStoreOperation(getCachedCenterRouteStored);
export const saveCachedCenterRoute = serializeStoreOperation(saveCachedCenterRouteStored);
export const getLocalNavigationSnapshot = serializeStoreOperation(getLocalNavigationSnapshotStored);
export const queueOutboxEvent = serializeStoreOperation(queueOutboxEventStored);
export const getPendingOutboxEvents = serializeStoreOperation(getPendingOutboxEventsStored);
export const acknowledgeOutbox = serializeStoreOperation(acknowledgeOutboxStored);
export const createLocalTrip = serializeStoreOperation(createLocalTripStored);
export const getLocalTrip = serializeStoreOperation(getLocalTripStored);
export const addLocalTripPoint = serializeStoreOperation(addLocalTripPointStored);
export const completeLocalTrip = serializeStoreOperation(completeLocalTripStored);
export const updateLocalTrip = serializeStoreOperation(updateLocalTripStored);
export const getLocalTripPoints = serializeStoreOperation(getLocalTripPointsStored);
export const pendingEventCount = serializeStoreOperation(pendingEventCountStored);
export const navigationMetadata = serializeStoreOperation(navigationMetadataStored);
export const activeLocalTrip = serializeStoreOperation(activeLocalTripStored);
export const clearNavigationStorage = serializeStoreOperation(clearNavigationStorageStored);

// Keep each account's outbox and trips in its own database. Existing installations
// retain the legacy database, including any events that have not synced yet.
async function selectNavigationAccountStored(userId: string) {
  const owner = await navigationMetadataStored('owner');
  if (owner && String(owner) !== String(userId)) {
    const db = await database();
    await db.closeAsync();
    databasePromise = null;
    const legacyOwner = await getStoredItem('resq_navigation_legacy_owner');
    if (!legacyOwner && !(await getStoredItem('resq_navigation_database'))) {
      await setStoredItem('resq_navigation_legacy_owner', String(owner));
    }
    const originalOwner = legacyOwner || String(owner);
    const name = originalOwner === String(userId) ? databaseName
      : `resq_navigation_${Array.from(String(userId)).map((char) => char.codePointAt(0)!.toString(16)).join('_')}.db`;
    await setStoredItem('resq_navigation_database', name);
  }
  await navigationMetadataStored('owner', String(userId));
}
export const selectNavigationAccount = serializeStoreOperation(selectNavigationAccountStored);
