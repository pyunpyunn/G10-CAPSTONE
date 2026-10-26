import { api, clearToken, saveToken } from './client';
import { logoutPushIdentity } from '@/utils/pushNotifications';
import { getMobileDeviceUuid } from '@/utils/mobileDeviceRegistration';
import axios from 'axios';
import { Platform } from 'react-native';
import { clearNavigationStorage, navigationMetadata, pendingEventCount, activeLocalTrip } from '@/utils/navigationStore';

type Role = {
  role_key: string;
  role_name: string;
};

export type AuthUser = {
  user_id: string;
  full_name: string;
  username: string;
  role: Role;
};

type LoginResponse = {
  token: string;
  user: AuthUser;
};

export async function loginMobile(login: string, password: string) {
  const response = await api.post<LoginResponse>('/auth/login', {
    login,
    password,
    device_name: 'resqperation-mobile',
  });

  let token = response.data.token;

  if (response.data.user.role?.role_key === 'rescuer') {
    const deviceUuid = await getMobileDeviceUuid('rescuer');
    const mobileResponse = await api.post<{ token: string }>('/mobile/login', {
      login,
      password,
      device_uuid: deviceUuid,
    });
    token = mobileResponse.data.token;
    try {
      await axios.post(`${api.defaults.baseURL}/auth/logout`, {}, {
        headers: { Authorization: `Bearer ${response.data.token}` },
        timeout: 5000,
      });
    } catch {
      // The device-bound token is still valid if cleanup of the temporary login token fails.
    }
  }

  if (Platform.OS !== 'web') {
    const owner = await navigationMetadata('owner');
    if (owner && owner !== response.data.user.user_id && (await activeLocalTrip() || await pendingEventCount())) {
      throw new Error('Sign in to the previous account and sync its saved trip events before switching accounts.');
    }
    if (owner !== response.data.user.user_id) await clearNavigationStorage();
    await navigationMetadata('owner', response.data.user.user_id);
  }
  await saveToken(token);

  return response.data.user;
}

export async function logoutMobile() {
  if (Platform.OS !== 'web' && (await activeLocalTrip() || await pendingEventCount())) {
    throw new Error('End the active trip and sync saved trip events before logging out.');
  }
  try {
    await api.post('/auth/logout');
  } finally {
    try {
      await logoutPushIdentity();
    } finally {
      await clearToken();
    }
  }
}

export async function changePasswordWithOldPassword(payload: { login: string; current_password: string; password: string; password_confirmation: string }) {
  return (await api.post('/auth/password-recovery/reset', payload)).data;
}

export async function verifyPasswordChange(login: string, currentPassword?: string) {
  const payload: { login: string; current_password?: string } = { login: login.trim() };
  if (currentPassword !== undefined) payload.current_password = currentPassword;
  return (await api.post('/auth/password-recovery/verify', payload)).data;
}
