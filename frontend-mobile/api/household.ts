import { api } from './client';

export type HouseholdOverview = {
  profile: {
    user: any;
    household: any;
  };
  setup: {
    is_setup_complete: boolean;
    has_geotag: boolean;
    has_device: boolean;
  };
  active_event: any;
  current_status: any;
  status_options: any[];
  status_history: any[];
  members: any[];
  devices: any[];
  geotag: any;
  evacuation_centers: any[];
  trusted: {
    is_available: boolean;
    households: any[];
  };
  qr: any;
};

export async function getHouseholdOverview() {
  const response = await api.get<{ data: HouseholdOverview }>('/household/overview');
  return response.data.data;
}

export async function completeHouseholdSetup(payload: any) {
  const response = await api.post('/household/setup', payload);
  return response.data;
}

export async function updateHouseholdDeviceLocation(payload: any) {
  const response = await api.post('/household/device-location', payload);
  return response.data;
}

export async function updateHouseholdMember(memberId: string, payload: any) {
  const response = await api.patch(`/household/members/${memberId}`, payload);
  return response.data.data;
}

export async function saveHouseholdStatus(payload: any) {
  const response = await api.post('/household/status', payload);
  return response.data;
}

export async function getHouseholdQr() {
  const response = await api.get('/household/qr');
  return response.data.data;
}

export async function lookupTrustedHousehold(householdId: string) {
  const response = await api.get(`/household/trusted-households/lookup/${householdId}`);
  return response.data.data;
}

export async function createTrustedHousehold(payload: any) {
  const response = await api.post('/household/trusted-households', payload);
  return response.data;
}
