import { Platform } from 'react-native';
import { api, getAuthenticatedImageSource } from './client';
import { submitReport } from './reportSubmission';
import { stopAcceptedDispatchAlarm } from '@/utils/dispatchAlarm';

export type RescuerOverview = {
  profile: {
    user: any;
    responder: any;
  };
  active_event: any;
  recent_alerts: any[];
  summary: {
    total_assignments: number;
    active_assignments: number;
    completed_assignments: number;
    urgent_assignments: number;
  };
  assignments: any[];
  evacuation_centers: any[];
  field_reports: any[];
  resource_requests: any[];
  status_options: any[];
  category_options: any[];
};

export async function getRescuerOverview(refresh = false) {
  const config = { timeout: 30000, cache: !refresh };
  const response = await api.get<{ data: RescuerOverview }>('/rescuer/overview', config);
  return response.data.data;
}

export async function getAssignedHousehold(assignmentId: number) {
  const response = await api.get(`/rescuer/assignments/${assignmentId}/household`);
  return response.data.data;
}

export async function getAssignedMemberProfilePhoto(assignmentId: number, memberId: string) {
  return getAuthenticatedImageSource(
    `/rescuer/assignments/${assignmentId}/members/${encodeURIComponent(memberId)}/profile-photo`,
  );
}

export async function getRescuerProfile() {
  const response = await api.get('/rescuer/profile');
  return response.data.data;
}

export async function updateRescuerProfile(payload: any) {
  const response = await api.patch('/rescuer/profile', payload);
  return response.data.data;
}

export async function updateAssignmentStatus(
  assignmentId: number,
  status: string,
  payload: any = {}
) {
  const response = await submitReport('patch', `/rescuer/assignments/${assignmentId}/status`, {
    status,
    ...payload,
  });
  await stopAcceptedDispatchAlarm(assignmentId, status);

  return response.data.data;
}

export async function sendAssignmentLocation(assignmentId: number, payload: any) {
  const response = await api.post(`/rescuer/assignments/${assignmentId}/location`, payload);
  return response.data;
}

export async function createFieldReport(payload: any) {
  const response = await submitReport('post', '/rescuer/field-reports', payload);
  return response.data;
}

export async function createResourceRequest(payload: any) {
  const response = await submitReport('post', '/rescuer/resource-requests', payload);
  return response.data;
}

export async function cancelResourceRequest(requestId: string) {
  const response = await api.patch(`/rescuer/resource-requests/${requestId}/cancel`);
  return response.data;
}

export async function getRadioFeed(params: any = {}) {
  const response = await api.get('/rescuer/radio', { params });
  return response.data.data;
}

export async function startRadioTransmission(payload: any) {
  const response = await api.post('/rescuer/radio/start', payload);
  return response.data.data;
}

export async function heartbeatRadioTransmission(payload: any) {
  const response = await api.post('/rescuer/radio/heartbeat', payload);
  return response.data.data;
}

export async function stopRadioTransmission(payload: any) {
  const response = await api.post('/rescuer/radio/stop', payload);
  return response.data.data;
}

export async function uploadRadioClip(payload: any) {
  const formData = new FormData();
  formData.append('channel', payload.channel || 'team');

  if (payload.transmission_id) {
    formData.append('transmission_id', String(payload.transmission_id));
  }

  if (payload.assignment_id) {
    formData.append('assignment_id', String(payload.assignment_id));
  }

  formData.append('duration_seconds', String(payload.duration_seconds || 0));

  if (Platform.OS === 'web') {
    const blobResponse = await fetch(payload.uri);
    const rawBlob = await blobResponse.blob();
    const cleanMime = (rawBlob.type || payload.type || 'audio/webm').split(';')[0].trim().toLowerCase();
    const ext = cleanMime.includes('webm')
      ? 'webm'
      : cleanMime.includes('ogg')
        ? 'ogg'
        : cleanMime.includes('wav')
          ? 'wav'
          : cleanMime.includes('mpeg') || cleanMime.includes('mp3')
            ? 'mp3'
            : cleanMime.includes('aac')
              ? 'aac'
              : 'm4a';
    const fileName = `resqperation-ptt.${ext}`;
    const audioFile = typeof File !== 'undefined'
      ? new File([rawBlob], fileName, { type: cleanMime || 'audio/webm' })
      : new Blob([rawBlob], { type: cleanMime || 'audio/webm' });

    formData.append('audio', audioFile as any, fileName);

    const response = await api.post('/rescuer/radio/clip', formData, {
      timeout: 30000,
    });

    return response.data.data;
  }

  formData.append('audio', {
    uri: payload.uri,
    name: payload.name || 'resqperation-ptt.m4a',
    type: payload.type || 'audio/mp4',
  } as any);

  const response = await api.post('/rescuer/radio/clip', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    timeout: 30000,
  });

  return response.data.data;
}

export async function sendRadioSignal(payload: any) {
  const response = await api.post('/rescuer/radio/signal', payload);
  return response.data.data;
}

export async function getRescuerRequestLocations(): Promise<{ locations?: any[]; barangay?: string }> {
  try {
    const response = await api.get('/rescuer/request-locations');
    return response.data?.data ?? response.data ?? { locations: [], barangay: 'the barangay' };
  } catch {
    return { locations: [], barangay: 'the barangay' };
  }
}

export async function getRescuerDispatchHistory(page = 1): Promise<any> {
  return (await api.get('/rescuer/assignments/history', { params: { page } })).data.data;
}
