import { api } from './client';

export type PushRegistrationPayload = {
  device_uuid: string;
  device_name: string;
  platform: 'android' | 'ios';
  player_id: string | null;
  push_token?: string | null;
  push_provider: 'onesignal';
  one_signal_user_id?: string | null;
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
