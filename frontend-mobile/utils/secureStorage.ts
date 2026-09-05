import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

export async function getStoredItem(key: string) {
  if (Platform.OS === 'web') {
    return getLocalStorage()?.getItem(key) ?? null;
  }

  return SecureStore.getItemAsync(key);
}

export async function setStoredItem(key: string, value: string) {
  if (Platform.OS === 'web') {
    getLocalStorage()?.setItem(key, value);
    return;
  }

  await SecureStore.setItemAsync(key, value);
}

export async function getStoredJson<T>(key: string): Promise<T | null> {
  const value = await getStoredItem(key);

  if (!value) {
    return null;
  }

  try {
    return JSON.parse(value) as T;
  } catch {
    return null;
  }
}

export async function setStoredJson(key: string, value: unknown) {
  await setStoredItem(key, JSON.stringify(value));
}

export async function deleteStoredItem(key: string) {
  if (Platform.OS === 'web') {
    getLocalStorage()?.removeItem(key);
    return;
  }

  await SecureStore.deleteItemAsync(key);
}

function getLocalStorage() {
  if (typeof localStorage === 'undefined') {
    return null;
  }

  return localStorage;
}
