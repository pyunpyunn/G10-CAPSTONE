import axios from 'axios';
import Constants from 'expo-constants';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';

const tokenKey = 'resqperation_mobile_token';

function getApiBaseUrl() {
  const configuredUrl = process.env.EXPO_PUBLIC_API_BASE_URL;

  if (configuredUrl && !isLocalhostUrl(configuredUrl)) {
    return configuredUrl;
  }

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

api.interceptors.request.use(async (config) => {
  const token = await getStoredItem(tokenKey);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.code === 'ECONNABORTED') {
      error.userMessage = 'The server took too long to respond. Check Wi-Fi, Laravel, and the database connection.';
    } else if (!error.response) {
      error.userMessage = 'The app cannot reach the server. Check the API URL and make sure Laravel is running.';
    }

    return Promise.reject(error);
  }
);

export async function saveToken(token: string) {
  await setStoredItem(tokenKey, token);
}

export async function clearToken() {
  await deleteStoredItem(tokenKey);
}
