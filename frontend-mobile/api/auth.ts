import { api, saveToken } from './client';

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
