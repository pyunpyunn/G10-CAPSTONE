import { api } from './client';

export type PushRegistrationPayload = {
  device_uuid: string;
  device_name: string;
  platform: 'android' | 'ios';
  expo_push_token: string | null;
  battery_level?: number | null;
  notification_permission_status:
    | 'granted'
    | 'denied'
    | 'undetermined'
    | 'unavailable'
    | 'error';
};

export async function savePushRegistration(payload: PushRegistrationPayload) {
  const response = await api.post('/mobile/device-token', payload);

  return response.data.data;
}
