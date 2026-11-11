import { api } from './client';
import { Platform } from 'react-native';

export type HouseholdProfilePhotoAsset = {
  uri: string;
  fileName?: string | null;
  mimeType?: string | null;
  file?: Blob;
};

export type HouseholdOverview = {
  profile: {
    user: any;
    household: any;
  };
  setup: {
    is_setup_complete: boolean;
    has_geotag: boolean;
    has_device: boolean;
    address?: any;
  };
  active_event: any;
  recent_alerts?: { broadcast_id: number | string; title: string; message: string; estimated_duration?: string | null; sent_at: string | null }[];
  member_status: any;
  current_status: any;
  status_options: any[];
  status_history: any[];
  members: any[];
  devices: any[];
  geotag: any;
  sitio_options: { sitio_id: number; sitio_name: string }[];
  evacuation_centers: any[];
  trusted: {
    is_available: boolean;
    pin_configured: boolean;
    households: any[];
    incoming_requests: any[];
    notifications?: any[];
  };
  qr: any;
};

export async function getHouseholdOverview(refresh = false) {
  const config = { timeout: 30000, cache: !refresh };
  const response = await api.get<{ data: HouseholdOverview }>('/household/overview', config);
  return response.data.data;
}

export async function completeHouseholdSetup(payload: any) {
  const response = await api.post('/household/setup', payload);
  return response.data;
}

export async function uploadHouseholdProfilePhoto(asset: HouseholdProfilePhotoAsset) {
  const formData = new FormData();
  const fileName = asset.fileName || 'profile-photo.jpg';
  if (Platform.OS === 'web') {
    const blob: Blob = asset.file ?? await fetch(asset.uri).then((response) => response.blob());
    formData.append('photo', blob, fileName);
  } else {
    formData.append('photo', {
      uri: asset.uri,
      name: fileName,
      type: asset.mimeType || 'image/jpeg',
    } as any);
  }
  const response = await api.post('/household/profile-photo', formData, {
    timeout: 30000,
  });
  return response.data.data.user;
}

export async function previewHouseholdSetupLocation(latitude: number, longitude: number) {
  const response = await api.post('/household/setup/location', { latitude, longitude });
  return response.data.data;
}

export async function updateHouseholdDeviceLocation(payload: any) {
  const response = await api.post('/household/device-location', payload);
  return response.data;
}

export async function saveHouseholdGeotag(payload: any) {
  return (await api.put('/household/geotag', payload)).data;
}

export async function updateHouseholdMember(memberId: string, payload: any) {
  const response = await api.patch(`/household/members/${memberId}`, payload);
  return response.data.data;
}

export async function saveHouseholdMemberStatus(memberId: string, payload: any, idempotencyKey?: string) {
  const config = idempotencyKey ? { headers: { 'Idempotency-Key': idempotencyKey } } : undefined;
  const response = await api.post(`/household/members/${memberId}/status`, payload, config);
  return response.data.data;
}

export async function saveTrustedHouseholdMemberStatus(connectionId: string, memberId: string, payload: any) {
  const response = await api.post(`/household/trusted-households/${connectionId}/members/${memberId}/status`, payload);
  return response.data.data;
}

export async function saveHouseholdStatus(payload: any, idempotencyKey?: string) {
  const config = idempotencyKey ? { headers: { 'Idempotency-Key': idempotencyKey } } : undefined;
  const response = await api.post('/household/status', payload, config);
  return response.data;
}

export async function getHouseholdQr() {
  const response = await api.get('/household/qr');
  return response.data.data;
}

export async function saveTrustedPin(payload: {
  pin: string;
  pin_confirmation: string;
  current_pin?: string;
  current_password?: string;
}) {
  const response = await api.put('/household/trusted-pin', payload);
  return response.data;
}

export async function verifyTrustedPin(pin: string) {
  const response = await api.post('/household/trusted-pin/verify', { pin });
  return response.data;
}

export async function verifyTrustedHouseholdPin(connectionId: string, pin: string) {
  const response = await api.post(`/household/trusted-households/${connectionId}/pin/verify`, { pin });
  return response.data;
}

export async function lookupTrustedHousehold(householdId: string) {
  const response = await api.get(`/household/trusted-households/lookup/${householdId}`);
  return response.data.data;
}

export async function createTrustedHousehold(payload: any) {
  const response = await api.post('/household/trusted-households', payload);
  return response.data;
}

export async function deleteTrustedHousehold(connectionId: string) {
  const response = await api.delete(`/household/trusted-households/${connectionId}`);
  return response.data;
}

export async function respondToTrustedHousehold(connectionId: string, decision: 'accept' | 'reject') {
  const response = await api.patch(`/household/trusted-households/${connectionId}`, { decision });
  return response.data;
}
