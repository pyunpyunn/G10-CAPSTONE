import { api } from './client';

export type RescuerOverview = {
  profile: {
    user: any;
    responder: any;
  };
  active_event: any;
  summary: {
    total_assignments: number;
    active_assignments: number;
    completed_assignments: number;
    urgent_assignments: number;
  };
  assignments: any[];
  field_reports: any[];
  resource_requests: any[];
  status_options: any[];
  category_options: any[];
};

export async function getRescuerOverview() {
  const response = await api.get<{ data: RescuerOverview }>('/rescuer/overview');
  return response.data.data;
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
  const response = await api.patch(`/rescuer/assignments/${assignmentId}/status`, {
    status,
    ...payload,
  });

  return response.data.data;
}

export async function sendAssignmentLocation(assignmentId: number, payload: any) {
  const response = await api.post(`/rescuer/assignments/${assignmentId}/location`, payload);
  return response.data;
}

export async function createFieldReport(payload: any) {
  const response = await api.post('/rescuer/field-reports', payload);
  return response.data;
}

export async function createResourceRequest(payload: any) {
  const response = await api.post('/rescuer/resource-requests', payload);
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

  if (payload.assignment_id) {
    formData.append('assignment_id', String(payload.assignment_id));
  }

  formData.append('duration_seconds', String(payload.duration_seconds || 0));
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
