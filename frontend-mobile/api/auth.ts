import { api, clearToken, saveToken } from './client';

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

export async function loginMobile(login: string, password: string, remember = false) {
  const response = await api.post<LoginResponse>('/auth/login', {
    login,
    password,
    device_name: 'resqperation-mobile',
  });

  await saveToken(response.data.token, remember);

  return response.data.user;
}

export async function getRecoveryQuestions(login: string) {
  const response = await api.get('/auth/password-recovery/questions', { params: { login } });
  return response.data.data;
}

export async function resetMobilePassword(payload: Record<string, string>) {
  const response = await api.post('/auth/password-recovery/reset', payload);
  return response.data;
}

export async function logoutMobile() {
  try {
    await api.post('/auth/logout');
  } finally {
    await clearToken();
  }
}
