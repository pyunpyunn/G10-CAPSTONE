import { api, clearToken, saveToken } from './client';
import { logoutPushIdentity } from '@/utils/pushNotifications';
import { getMobileDeviceUuid } from '@/utils/mobileDeviceRegistration';
import axios from 'axios';
import { Platform } from 'react-native';
import { randomUUID } from 'expo-crypto';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { selectNavigationAccount } from '@/utils/navigationStore';

type Role = {
  role_key: string;
  role_name: string;
  base_role_key?: string;
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

  if (['rescuer', 'tl', 'tm'].includes(response.data.user.role?.role_key) || response.data.user.role?.base_role_key === 'rescuer') {
    const accountDeviceKey = `resq_device_account_${String(response.data.user.user_id)}`;
    let deviceUuid = await getStoredItem(accountDeviceKey) || await getMobileDeviceUuid('rescuer');
    const bindDevice = (uuid: string) => api.post<{ token: string }>('/mobile/login', { login, password, device_uuid: uuid });
    let mobileResponse;
    try {
      mobileResponse = await bindDevice(deviceUuid);
    } catch (error: any) {
      if (error.response?.status !== 409 || error.response?.data?.message !== 'This device is already registered to another account.') throw error;
      // A shared phone keeps a separate server registration for each account.
      deviceUuid = `rescuer-${randomUUID()}`;
      mobileResponse = await bindDevice(deviceUuid);
    }
    await setStoredItem(accountDeviceKey, deviceUuid);
    await setStoredItem('resq_rescuer_device_uuid', deviceUuid);
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
    await selectNavigationAccount(String(response.data.user.user_id));
  }
  await saveToken(token);

  return response.data.user;
}

export async function logoutMobile() {
  if (Platform.OS !== 'web') {
    const { stopRescuerTracking } = await import('@/utils/rescuerLiveLocation');
    await stopRescuerTracking().catch(() => {});
    const { endNavigationTrip, suspendNavigationSync } = await import('@/utils/navigationRuntime');
    // Stop recording before revoking the session. The account's saved outbox is
    // retained if the phone is offline and resumes after this account signs in.
    try { await suspendNavigationSync(); await endNavigationTrip({ sync: false }); } catch { /* Keep saved events. */ }
  }
  try {
    await api.post('/auth/logout', {}, { timeout: 5000 });
  } catch {
    // Local logout must also work when the server is unreachable.
  } finally {
    try {
      await logoutPushIdentity().catch(() => {});
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
