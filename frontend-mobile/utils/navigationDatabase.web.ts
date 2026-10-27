import type { SQLiteDatabase } from 'expo-sqlite';

export async function openDatabaseAsync(_name: string): Promise<SQLiteDatabase> {
  throw new Error('Offline trip storage requires the Android or iOS app.');
}
