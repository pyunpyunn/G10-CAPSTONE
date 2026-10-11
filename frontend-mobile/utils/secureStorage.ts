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

const rememberedPasswordKey = 'resqperation.remembered.credentials';

export async function getRememberedCredentials(): Promise<{ login: string; password: string } | null> {
  // The web fallback for token storage must never receive a password.
  if (Platform.OS === 'web') return null;
  const value = await SecureStore.getItemAsync(rememberedPasswordKey);
  if (!value) return null;
  try {
    const saved = JSON.parse(value);
    return typeof saved.login === 'string' && typeof saved.password === 'string' ? saved : null;
  } catch { return null; }
}

export async function saveRememberedCredentials(login: string, password: string, remember: boolean) {
  if (Platform.OS === 'web') return;
  if (!remember) {
    await SecureStore.deleteItemAsync(rememberedPasswordKey);
    return;
  }
  await SecureStore.setItemAsync(rememberedPasswordKey, JSON.stringify({ login: login.trim(), password }), {
    keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  });
}
