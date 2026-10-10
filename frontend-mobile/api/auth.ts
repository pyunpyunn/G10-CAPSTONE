import { api, clearToken, saveToken } from './client';
import { logoutPushIdentity } from '@/utils/pushNotifications';

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

  await saveToken(response.data.token);

  return response.data.user;
}

export async function logoutMobile() {
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
