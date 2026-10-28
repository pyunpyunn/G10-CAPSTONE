import { saveViewState } from '@/state/apiStore';
import { api, clearToken, saveToken } from './client';
import { logoutPushIdentity } from '@/utils/pushNotifications';
import { getMobileDeviceUuid } from '@/utils/mobileDeviceRegistration';
import axios from 'axios';
import { Platform } from 'react-native';
import { randomUUID } from 'expo-crypto';
import { deleteStoredItem, getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { setMobileSnapshotIdentity } from '@/utils/mobileSnapshot';
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
  household_id?: string;
  member_id?: string;
  owner_selection_required?: boolean;
  role: Role;
};

type LoginResponse = {
  token: string;
  user: AuthUser;
  owner_selection_required?: boolean;
  owner_options?: any;
};

const sessionRoleKey = 'resqperation_mobile_role';
const ownerRequiredKey = 'resq_household_owner_required';

export async function householdOwnerSelectionRequired() {
  return await getStoredItem(ownerRequiredKey) === '1';
}
const mobileRoles = ['household_resident', 'rescuer', 'tl', 'tm'];

export async function restoreMobileSession(): Promise<string | null> {
  if (Platform.OS === 'web') return null;
  const token = await getStoredItem('resqperation_mobile_token');
  if (!token) return null;

  const cachedRole = await getStoredItem(sessionRoleKey);
  try {
    const response = await api.get('/auth/me', { timeout: 8000 });
    const role = response.data?.data?.role?.role_key || response.data?.role?.role_key;
    if (mobileRoles.includes(role)) {
      await setStoredItem(sessionRoleKey, role);
      const user = response.data?.data || response.data;
      await setStoredItem(ownerRequiredKey, user.owner_selection_required ? '1' : '0');
      await setMobileSnapshotIdentity(
        role,
        String(user.user_id || ''),
        user.household_id ? String(user.household_id) : undefined,
        api.defaults.baseURL || '',
        true,
      );
      return role;
    }
    await clearToken();
    await deleteStoredItem(sessionRoleKey);
    await deleteStoredItem(ownerRequiredKey);
    return null;
  } catch (error: any) {
    if (error.response?.status === 401) {
      await clearToken();
      await deleteStoredItem(sessionRoleKey);
      await deleteStoredItem(ownerRequiredKey);
      return null;
    }

    if (mobileRoles.includes(cachedRole || '')) {
      return cachedRole;
    }

    await clearToken();
    await deleteStoredItem(sessionRoleKey);
    await deleteStoredItem(ownerRequiredKey);
    return null;
  }
}

export async function loginMobile(login: string, password: string) {
  const householdDeviceUuid = await getMobileDeviceUuid('household_resident');
  const response = await api.post<LoginResponse>('/auth/login', {
    login,
    password,
    device_name: 'resqperation-mobile',
    device_uuid: householdDeviceUuid,
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
    await setStoredItem(sessionRoleKey, response.data.user.role?.role_key || '');
  }
  await saveToken(token);
  await setStoredItem(ownerRequiredKey, response.data.user.owner_selection_required ? '1' : '0');
  await setMobileSnapshotIdentity(
    response.data.user.role?.role_key || '',
    String(response.data.user.user_id),
    response.data.user.household_id ? String(response.data.user.household_id) : undefined,
    api.defaults.baseURL || '',
  );
  if (response.data.owner_options) saveViewState('mobile:household-owner', response.data.owner_options);

  return response.data.user;
}

async function acceptHouseholdSession(data: LoginResponse) {
  await saveToken(data.token);
  await setStoredItem(sessionRoleKey, 'household_resident');
  await setStoredItem(ownerRequiredKey, data.user.owner_selection_required ? '1' : '0');
  await setMobileSnapshotIdentity(
    data.user.role?.role_key || 'household_resident',
    String(data.user.user_id),
    data.user.household_id ? String(data.user.household_id) : undefined,
    api.defaults.baseURL || '',
  );
  if (data.owner_options) saveViewState('mobile:household-owner', data.owner_options);
  if (Platform.OS !== 'web') await selectNavigationAccount(String(data.user.user_id));
  return data.user;
}

export async function getHouseholdOwnerOptions() {
  return (await api.get('/household/device-owner/options')).data.data;
}

export async function selectHouseholdDeviceOwner(memberId: string) {
  const response = await api.post<LoginResponse>('/household/device-owner/select', { member_id: memberId });
  return acceptHouseholdSession(response.data);
}

export async function resetHouseholdDeviceOwner() {
  const device_uuid = await getMobileDeviceUuid('household_resident');
  const response = await api.post<LoginResponse>('/household/device-owner/reset', { device_uuid });
  return acceptHouseholdSession(response.data);
}

export async function logoutMobile() {
  const token = await getStoredItem('resqperation_mobile_token');
  if (token && api.defaults.baseURL) {
    void axios.post(`${api.defaults.baseURL}/auth/logout`, {}, {
      headers: { Authorization: `Bearer ${token}` },
      timeout: 3000,
    }).catch(() => {
      console.warn('Could not revoke the server token during local logout.');
    });
  }

  await Promise.all([
    clearToken(),
    deleteStoredItem(sessionRoleKey),
    deleteStoredItem(ownerRequiredKey),
  ]);

  if (Platform.OS !== 'web') {
    void (async () => {
      try {
        const { stopRescuerTracking } = await import('@/utils/rescuerLiveLocation');
        await stopRescuerTracking();
      } catch {
        console.warn('Could not stop rescuer location tracking during logout.');
      }
      try {
        const { endNavigationTrip, suspendNavigationSync } = await import('@/utils/navigationRuntime');
        await suspendNavigationSync();
        await endNavigationTrip({ sync: false });
      } catch {
        console.warn('Could not finish local navigation cleanup during logout.');
      }
    })();
  }
  void logoutPushIdentity().catch(() => {
    console.warn('Could not clear the push notification identity during logout.');
  });
}

export async function lookupHouseholdRecoveryContact(login: string) {
  return (await api.post<{ masked_contact_number: string }>('/auth/password-recovery/household/lookup', {
    login: login.trim(),
  })).data;
}

export async function validateHouseholdRecoveryContact(login: string, contact_number: string) {
  return (await api.post('/auth/password-recovery/household/validate-contact', {
    login: login.trim(),
    contact_number: contact_number.trim(),
  })).data;
}

export async function sendHouseholdRecoveryCode(login: string, contact_number: string) {
  return (await api.post('/auth/password-recovery/household/send-code', {
    login: login.trim(),
    contact_number: contact_number.trim(),
  }, { timeout: 30000 })).data;
}

export async function resetHouseholdPassword(payload: {
  login: string;
  contact_number: string;
  code: string;
  password: string;
  password_confirmation: string;
}) {
  return (await api.post<{ household_id: string; account_identifier: string }>('/auth/password-recovery/household/reset', payload)).data;
}
