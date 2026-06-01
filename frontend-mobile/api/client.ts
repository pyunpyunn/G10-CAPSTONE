import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

const tokenKey = 'resqperation_mobile_token';

export const api = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync(tokenKey);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export async function saveToken(token: string) {
  await SecureStore.setItemAsync(tokenKey, token);
}

export async function clearToken() {
  await SecureStore.deleteItemAsync(tokenKey);
}
