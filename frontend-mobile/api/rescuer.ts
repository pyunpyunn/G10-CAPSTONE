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
