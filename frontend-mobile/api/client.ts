import axios, { CanceledError, create, isAxiosError, isCancel } from 'axios';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { isPrivateIpv4, normalizeApiServerUrl as normalizeServerUrl } from '@/utils/normalizeApiServerUrl';
import { installReduxTransport, resetApiState } from '@/state/apiStore';

const tokenKey = 'resqperation_mobile_token';
const rescuerDeviceUuidKey = 'resq_rescuer_device_uuid';
const serverUrlKey = 'resqperation_server_url';
const serverHealthCacheMs = 60_000;
const serverProbeTimeoutMs = 4_000;
const apiRequestTimeoutMs = 20_000;

type FailoverRequestConfig = {
  _resqFailoverAttempted?: boolean;
  _resqForcedServerUrl?: string;
};

const serverSelections = new Map<string, Promise<string>>();
let serverSelectionGeneration = 0;
let activeServerUrl: string | null = null;
let activeServerCheckedAt = 0;

export async function getApiServerUrl() {
  const stored = await getStoredItem(serverUrlKey);
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
      if (stored && !stored.includes('127.0.0.1') && !stored.includes('localhost')) {
        await deleteStoredItem(serverUrlKey);
        return getApiBaseUrl();
      }
    }
  }
  return stored || getApiBaseUrl();
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
  const isLocal = isLocalhostUrl(value) || (Platform.OS === 'web' && typeof window !== 'undefined' && ['127.0.0.1', 'localhost'].includes(window.location.hostname));
  return normalizeServerUrl(value, isLocal ? false : !__DEV__, Platform.OS === 'android' && !__DEV__);
}

function getApiBaseUrl() {
  const configuredUrl = process.env.EXPO_PUBLIC_API_BASE_URL;

  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
      if (configuredUrl && isLocalhostUrl(configuredUrl)) return configuredUrl;
      return 'http://127.0.0.1:8000/api/v1';
    }
    return configuredUrl || `${window.location.origin}/api/v1`;
  }

  if (configuredUrl) {
    return configuredUrl;
  }

  const expoHost = getExpoHost();

  if (expoHost) {
    return `http://${expoHost}:8000/api/v1`;
  }

  return 'http://127.0.0.1:8000/api/v1';
}

async function getApiServerCandidates() {
  const fallbacks = (process.env.EXPO_PUBLIC_API_FALLBACK_URLS || '')
    .split(',')
    .map((url) => url.trim())
    .filter(Boolean)
    .map((url) => normalizeApiServerUrl(url));
  const baseUrl = getApiBaseUrl();
  const configured = [
    ...(baseUrl ? [normalizeApiServerUrl(baseUrl)] : []),
    ...fallbacks,
  ];

  const uniqueConfigured = [...new Set(configured)];
  const orderedConfigured = Platform.OS === 'android' && !__DEV__
    ? [...uniqueConfigured].sort((left, right) => routePriority(left) - routePriority(right))
    : uniqueConfigured;
  let savedUrl = (await getStoredItem(serverUrlKey))?.trim();
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
      if (savedUrl && !savedUrl.includes('127.0.0.1') && !savedUrl.includes('localhost')) {
        await deleteStoredItem(serverUrlKey);
        savedUrl = undefined;
      }
    }
  }
  let normalizedSavedUrl: string | null = null;
  if (savedUrl) {
    try {
      normalizedSavedUrl = normalizeApiServerUrl(savedUrl);
    } catch (error) {
      if (Platform.OS !== 'android' || __DEV__) throw error;
      console.warn('Ignoring an invalid saved API URL in the Android release; configured HTTPS routes will be used.');
    }
  }
  if (Platform.OS === 'android' && !__DEV__) return orderedConfigured;
  if (!normalizedSavedUrl) return orderedConfigured;
  return [normalizedSavedUrl, ...orderedConfigured.filter((url) => url !== normalizedSavedUrl)];
}

function routePriority(url: string) {
  const hostname = new URL(url).hostname.toLowerCase();
  if (isNgrokHostname(hostname)) return 0;
  if (isPrivateIpv4(hostname) && !isTailscaleIpv4(hostname)) return 1;
  if (hostname.endsWith('.ts.net') || isTailscaleIpv4(hostname)) return 2;
  return 3;
}

function isTailscaleIpv4(hostname: string) {
  const parts = hostname.split('.').map(Number);
  if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) return false;
  return parts[0] === 100 && parts[1] >= 64 && parts[1] <= 127;
}

function ngrokHeaders(url: string) {
  return isNgrokHostname(new URL(url).hostname.toLowerCase())
    ? { 'ngrok-skip-browser-warning': '1' }
    : {};
}

function isNgrokHostname(hostname: string) {
  return /(^|\.)ngrok(-free)?\.(dev|app|io)$/.test(hostname);
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
    const routeFailures: string[] = [];
    try {
      let url: string | null = null;
      for (const candidate of candidates) {
        try {
          const response = await axios.get(`${candidate}/health/ready`, {
            timeout: serverProbeTimeoutMs,
            headers: { Accept: 'application/json', ...ngrokHeaders(candidate) },
          });
          if (response.data?.status !== 'ready') throw new Error('Server is not ready.');
          url = candidate;
          break;
        } catch (error) {
          if (isCancel(error)) throw error;
          if (isAxiosError(error)) {
            if (error.response?.status === 503) databaseUnavailable = true;
            const reason = error.response?.status
              ? `HTTP ${error.response.status}`
              : error.code || 'network error';
            routeFailures.push(`${new URL(candidate).hostname}: ${reason}`);
          } else {
            routeFailures.push(`${new URL(candidate).hostname}: ${error instanceof Error ? error.message : 'unreachable'}`);
          }
        }
      }
      if (!url) throw new Error('No configured API route is ready.');
      if (generation !== serverSelectionGeneration) throw new CanceledError('The server configuration changed.');
      activeServerUrl = url;
      activeServerCheckedAt = Date.now();
      api.defaults.baseURL = url;
      return url;
    } catch (error) {
      if (isCancel(error)) throw error;
      activeServerUrl = null;
      activeServerCheckedAt = 0;
      const message = databaseUnavailable
        ? 'The API is reachable, but its database is not ready. Check the Laravel server database.'
        : routeFailures.length
          ? `Could not reach the configured API route: ${routeFailures.join('; ')}.`
          : 'No API route is configured for this release.';
      const failure = new Error(message);
      Object.assign(failure, {
        userMessage: databaseUnavailable
          ? message
          : `${message} Confirm the phone can open the configured API health URL and that the Ngrok tunnel is running.`,
      });
      throw failure;
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

function canRetryOnAnotherServer(config: FailoverRequestConfig & { method?: string; url?: string }) {
  const method = String(config.method || 'get').toLowerCase();
  if (['get', 'head', 'options'].includes(method)) return true;
  if (method !== 'post') return false;

  const route = String(config.url || '').split('?')[0].replace(/^\/+|\/+$/g, '');
  return ['auth/login', 'mobile/login'].includes(route);
}

export const api = create({
  baseURL: getApiBaseUrl(),
  timeout: apiRequestTimeoutMs,
  headers: {
    Accept: 'application/json',
  },
});

installReduxTransport(api);

api.interceptors.request.use(async (config) => {
  const failoverConfig = config as typeof config & FailoverRequestConfig;
  const serverUrl = failoverConfig._resqForcedServerUrl || await selectReadyServer();
  config.baseURL = serverUrl;
  const trusted = new URL(normalizeApiServerUrl(serverUrl));
  const destination = new URL(api.getUri(config));
  if (destination.origin !== trusted.origin || destination.username || destination.password) {
    throw new CanceledError('Blocked a request outside the selected API server.');
  }
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
    const currentUrl = config?.baseURL;
    if (!error.response && config && !config._resqFailoverAttempted) {
      activeServerUrl = null;
      activeServerCheckedAt = 0;
      if (canRetryOnAnotherServer(config)) {
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
      error.userMessage = 'The app cannot reach the API. Check the phone internet connection and confirm the laptop, Laravel API, and Ngrok tunnel are running.';
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
  const { clearMobileSnapshots } = await import('@/utils/mobileSnapshot');
  await clearMobileSnapshots().catch(() => {});
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
