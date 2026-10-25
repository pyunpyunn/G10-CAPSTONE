import axios from 'axios';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { installReduxTransport, resetApiState } from '@/state/apiStore';

const tokenKey = 'resqperation_mobile_token';
const rescuerDeviceUuidKey = 'resq_rescuer_device_uuid';
const serverUrlKey = 'resqperation_server_url';

export async function getApiServerUrl() {
  return (await getStoredItem(serverUrlKey)) || getApiBaseUrl();
}

export async function setApiServerUrl(value: string) {
  const parsed = new URL(value.trim());
  if (!['https:', 'http:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error('Enter a server URL beginning with https://, without passwords or query parameters.');
  }
  let path = parsed.pathname.replace(/\/+$/, '');
  if (!path) path = '/api/v1';
  if (!path.endsWith('/api/v1')) throw new Error('The server address must end with /api/v1.');
  const next = `${parsed.origin}${path}`;
  if (next === await getApiServerUrl()) return next;
  if (Platform.OS !== 'web') {
    const navigation = await import('@/utils/navigationStore');
    if (await navigation.activeLocalTrip() || await navigation.pendingEventCount()) {
      throw new Error('End your trip and sync saved events before changing servers.');
    }
    await navigation.clearNavigationStorage();
  }
  const { logoutPushIdentity } = await import('@/utils/pushNotifications');
  await logoutPushIdentity();
  await clearToken();
  await setStoredItem(serverUrlKey, next);
  api.defaults.baseURL = next;
  return next;
}

function getApiBaseUrl() {
  const configuredUrl = process.env.EXPO_PUBLIC_API_BASE_URL;

  if (Platform.OS === 'web' && configuredUrl && isLocalhostUrl(configuredUrl)) {
    return configuredUrl;
  }

  if (configuredUrl && !isLocalhostUrl(configuredUrl)) {
    return configuredUrl;
  }

  if (!__DEV__ && Platform.OS !== 'web') return '';

  const expoHost = getExpoHost();

  if (expoHost) {
    return `http://${expoHost}:8000/api/v1`;
  }

  return configuredUrl || 'http://127.0.0.1:8000/api/v1';
}

function isLocalhostUrl(url: string) {
  return url.includes('127.0.0.1') || url.includes('localhost');
}

function getExpoHost() {
  const constants = Constants as any;
  const hostUri =
    Constants.expoConfig?.hostUri ||
    constants.manifest2?.extra?.expoClient?.hostUri ||
    constants.manifest?.debuggerHost;

  return typeof hostUri === 'string' ? hostUri.split(':')[0] : '';
}

export const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 12000,
  headers: {
    Accept: 'application/json',
  },
});

installReduxTransport(api);

api.interceptors.request.use(async (config) => {
  const serverUrl = await getApiServerUrl();
  if (!serverUrl) throw new Error('Set the server address on the sign-in screen first.');
  config.baseURL = serverUrl;
  api.defaults.baseURL = serverUrl;
  if (/\.ngrok(-free)?\.(dev|app|io)(\/|$)/.test(serverUrl)) config.headers['ngrok-skip-browser-warning'] = '1';
  const token = await getStoredItem(tokenKey);
  const deviceUuid = await getStoredItem(rescuerDeviceUuidKey);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  if (deviceUuid) {
    config.headers['X-Device-UUID'] = deviceUuid;
  }

  return config;
});

api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) resetApiState();
    if (error.code === 'ECONNABORTED') {
      error.userMessage = 'The server took too long to respond. Check Wi-Fi, Laravel, and the database connection.';
    } else if (!error.response) {
      error.userMessage = 'The app cannot reach the server. Check the API URL and make sure Laravel is running.';
    }

    return Promise.reject(error);
  }
);

export async function saveToken(token: string) {
  resetApiState();
  await setStoredItem(tokenKey, token);
}

export async function clearToken() {
  resetApiState();
  await deleteStoredItem(tokenKey);
}

export function storageUrl(path?: string | null) {
  if (!path) {
    return '';
  }

  const apiBase = String(api.defaults.baseURL || '').replace(/\/api\/v1\/?$/, '');
  const cleanPath = path.replace(/^\/+/, '');

  return `${apiBase}/storage/${cleanPath}`;
}
