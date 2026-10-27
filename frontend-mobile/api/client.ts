import axios, { CanceledError, create, isAxiosError, isCancel } from 'axios';
import { firstSuccessful } from '@/utils/networkScheduling';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { installReduxTransport, resetApiState } from '@/state/apiStore';

const tokenKey = 'resqperation_mobile_token';
const rescuerDeviceUuidKey = 'resq_rescuer_device_uuid';
const serverUrlKey = 'resqperation_server_url';
const serverHealthCacheMs = 60_000;
const serverProbeTimeoutMs = 2_500;

type FailoverRequestConfig = {
  _resqFailoverAttempted?: boolean;
  _resqForcedServerUrl?: string;
};

const serverSelections = new Map<string, Promise<string>>();
let serverSelectionGeneration = 0;
let activeServerUrl: string | null = null;
let activeServerCheckedAt = 0;

export async function getApiServerUrl() {
  return (await getStoredItem(serverUrlKey)) || getApiBaseUrl();
}

export async function setApiServerUrl(value: string) {
  const next = normalizeApiServerUrl(value);
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
  serverSelectionGeneration++;
  serverSelections.clear();
  activeServerUrl = null;
  activeServerCheckedAt = 0;
  api.defaults.baseURL = next;
  return next;
}

function normalizeApiServerUrl(value: string) {
  const parsed = new URL(value.trim());
  if (!['https:', 'http:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error('Enter a server URL beginning with https://, without passwords or query parameters.');
  }
  let path = parsed.pathname.replace(/\/+$/, '');
  if (!path) path = '/api/v1';
  if (!path.endsWith('/api/v1')) throw new Error('The server address must end with /api/v1.');
  return `${parsed.origin}${path}`;
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
  if (Platform.OS === 'web' && typeof window !== 'undefined') return `${window.location.origin}/api/v1`;

  const expoHost = getExpoHost();

  if (expoHost) {
    return `http://${expoHost}:8000/api/v1`;
  }

  return configuredUrl || 'http://127.0.0.1:8000/api/v1';
}

async function getApiServerCandidates() {
  const configured = (process.env.EXPO_PUBLIC_API_FALLBACK_URLS || '')
    .split(',')
    .map((url) => url.trim())
    .filter(Boolean)
    .map((url) => normalizeApiServerUrl(url));
  const baseUrl = getApiBaseUrl();
  if (baseUrl) {
    configured.push(normalizeApiServerUrl(baseUrl));
  }

  const uniqueConfigured = [...new Set(configured)];
  const savedUrl = await getStoredItem(serverUrlKey);
  if (!savedUrl || uniqueConfigured.includes(savedUrl)) return uniqueConfigured;
  return [savedUrl, ...uniqueConfigured];
}

function ngrokHeaders(url: string) {
  return /\.ngrok(-free)?\.(dev|app|io)(\/|$)/.test(url)
    ? { 'ngrok-skip-browser-warning': '1' }
    : {};
}

async function selectReadyServer(forceCheck = false, excludedUrls: string[] = []): Promise<string> {
  if (!forceCheck && activeServerUrl && Date.now() - activeServerCheckedAt < serverHealthCacheMs) return activeServerUrl;
  const key = [...excludedUrls].sort().join('|');
  const existing = serverSelections.get(key);
  if (existing) return existing;
  const generation = serverSelectionGeneration;
  const selection = (async () => {
    const excluded = new Set(excludedUrls);
    const candidates = (await getApiServerCandidates()).filter((url) => !excluded.has(url));
    let databaseUnavailable = false;
    try {
      const url: string = await firstSuccessful(candidates.map((candidate) => async (signal: AbortSignal) => {
        try {
          const response = await axios.get(`${candidate}/health/ready`, {
            timeout: serverProbeTimeoutMs, signal,
            headers: { Accept: 'application/json', ...ngrokHeaders(candidate) },
          });
          if (response.data?.status !== 'ready') throw new Error('Server is not ready.');
          return candidate;
        } catch (error) {
          if (isAxiosError(error) && error.response?.status === 503) databaseUnavailable = true;
          throw error;
        }
      }));
      if (generation !== serverSelectionGeneration) throw new CanceledError('The server configuration changed.');
      activeServerUrl = url;
      activeServerCheckedAt = Date.now();
      api.defaults.baseURL = url;
      return url;
    } catch (error) {
      if (isCancel(error)) throw error;
      activeServerUrl = null;
      activeServerCheckedAt = 0;
      throw new Error(databaseUnavailable
        ? 'The API is reachable, but its database is not ready. Check the Laravel server database.'
        : 'No configured API route is reachable. Check the connection and try again.');
    }
  })();
  serverSelections.set(key, selection);
  const cleanup = () => { if (serverSelections.get(key) === selection) serverSelections.delete(key); };
  selection.then(cleanup, cleanup);
  return selection;
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

export const api = create({
  baseURL: getApiBaseUrl(),
  timeout: 12000,
  headers: {
    Accept: 'application/json',
  },
});

installReduxTransport(api);

api.interceptors.request.use(async (config) => {
  const failoverConfig = config as typeof config & FailoverRequestConfig;
  const serverUrl = failoverConfig._resqForcedServerUrl || await selectReadyServer();
  config.baseURL = serverUrl;
  api.defaults.baseURL = serverUrl;
  Object.assign(config.headers, ngrokHeaders(serverUrl));
  const [token, deviceUuid] = await Promise.all([getStoredItem(tokenKey), getStoredItem(rescuerDeviceUuidKey)]);

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
    if (isCancel(error)) return Promise.reject(error);
    if (error.response?.status === 401) resetApiState();
    const config = error.config as (typeof error.config & FailoverRequestConfig) | undefined;
    const method = String(config?.method || 'get').toLowerCase();
    const currentUrl = config?.baseURL;
    if (!error.response && config && !config._resqFailoverAttempted) {
      activeServerUrl = null;
      activeServerCheckedAt = 0;
      if (['get', 'head', 'options'].includes(method)) {
        return selectReadyServer(true, currentUrl ? [currentUrl] : [])
          .then((serverUrl) => {
            config._resqFailoverAttempted = true;
            config._resqForcedServerUrl = serverUrl;
            config.baseURL = serverUrl;
            return api.request(config);
          })
          .catch(() => Promise.reject(error));
      }
    }
    if (error.code === 'ECONNABORTED') {
      error.userMessage = 'The server timed out. The app will check the other configured connection routes on the next request.';
    } else if (!error.response) {
      error.userMessage = 'The app cannot reach the server. Check ngrok, Tailscale, the local network, and that Laravel is running.';
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
