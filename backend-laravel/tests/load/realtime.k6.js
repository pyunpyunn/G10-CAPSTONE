import http from 'k6/http';
import ws from 'k6/ws';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Counter, Rate, Trend } from 'k6/metrics';

if (__ENV.TEST_ENV_ACK !== 'staging') throw new Error('Set TEST_ENV_ACK=staging and use isolated test accounts.');
const credentials = new SharedArray('credentials', () => JSON.parse(open(__ENV.CREDENTIALS_FILE || './credentials.json')));
const admins = credentials.filter((record) => ['admin', 'super_admin'].includes(record.role));
const reporters = credentials.filter((record) => record.role === 'household' && record.memberId);
if (!admins.length) throw new Error('At least one staging admin token is required.');
const api = __ENV.API_URL || 'http://127.0.0.1:8000/api/v1';
const socketUsers = Number(__ENV.SOCKET_USERS || 10);
const duration = __ENV.DURATION || '1m';
const writeReports = __ENV.WRITE_REPORTS === '1';
if (writeReports && !reporters.length) throw new Error('Staging household tokens and member IDs are required for writes.');
const subscribed = new Rate('private_subscription_success');
const replayed = new Rate('report_replay_success');
const socketLag = new Trend('socket_delivery_ms');
const received = new Counter('operation_events_received');

export const options = {
  scenarios: {
    sockets: { executor: 'ramping-vus', exec: 'sockets', startVUs: 0,
      stages: [{ duration: __ENV.RAMP_DURATION || '30s', target: socketUsers }, { duration, target: socketUsers }, { duration: '15s', target: 0 }] },
    api: { executor: 'constant-arrival-rate', exec: 'reports', rate: Number(__ENV.REPORTS_PER_SECOND || 2),
      timeUnit: '1s', duration, preAllocatedVUs: Number(__ENV.API_VUS || 10), maxVUs: Number(__ENV.MAX_API_VUS || 100) },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<2000'],
    private_subscription_success: ['rate>0.99'],
    checks: ['rate>0.99'],
    dropped_iterations: ['count==0'],
    ...(writeReports ? { report_replay_success: ['rate>0.99'], socket_delivery_ms: ['p(95)<2000'], operation_events_received: ['count>0'] } : {}),
  },
};

export function sockets() {
  const admin = admins[(__VU - 1) % admins.length];
  const headers = { Authorization: `Bearer ${admin.token}`, 'Content-Type': 'application/json', Accept: 'application/json' };
  let authorized = false;
  let lastRefetch = 0;
  let lastBatch;
  const origin = __ENV.FRONTEND_ORIGIN || api.replace(/\/api\/v1\/?$/, '');
  const response = ws.connect(`${__ENV.WS_URL || 'ws://127.0.0.1:8090'}/app/${__ENV.REVERB_APP_KEY}?protocol=7&client=js&version=8.4.0&flash=false`, { headers: { Origin: origin } }, (socket) => {
    socket.on('message', (raw) => {
      const message = JSON.parse(raw);
      if (message.event === 'pusher:connection_established') {
        const socketId = JSON.parse(message.data).socket_id;
        const channel = 'private-operations.households';
        const auth = http.post(`${api}/broadcasting/auth`, JSON.stringify({ socket_id: socketId, channel_name: channel }), { headers, tags: { name: 'socket-auth' } });
        if (check(auth, { 'private auth succeeds': (r) => r.status === 200 })) {
          socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: auth.json('auth') } }));
        }
      }
      if (message.event === 'pusher_internal:subscription_succeeded') authorized = true;
      if (message.event === 'pusher:ping') socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
      if (message.event === 'operations.changed') {
        const event = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
        if (event.batch_id === lastBatch) return;
        lastBatch = event.batch_id;
        received.add(1);
        socketLag.add(Math.max(0, Date.now() - event.emitted_at_ms));
        // Optional: model the API refetch fan-out, not just idle socket capacity.
        if (__ENV.SOCKET_REFETCH === '1' && Date.now() - lastRefetch >= 1250) {
          lastRefetch = Date.now();
          http.get(`${api}/households?per_page=1`, { headers, tags: { name: 'socket-refetch' } });
        }
      }
    });
    socket.on('error', () => subscribed.add(false));
    socket.setTimeout(() => { subscribed.add(authorized); socket.close(); }, 60000);
  });
  check(response, { 'websocket connected': (r) => r && r.status === 101 });
}

export function reports() {
  if (!writeReports) {
    const admin = admins[(__VU - 1) % admins.length];
    const response = http.get(`${api}/dashboard`, { headers: { Authorization: `Bearer ${admin.token}`, Accept: 'application/json' }, tags: { name: 'dashboard-read' } });
    check(response, { 'dashboard read succeeds': (r) => r.status === 200 });
    return;
  }
  const reporter = reporters[(__VU + __ITER) % reporters.length];
  const path = `${api}/household/members/${reporter.memberId}/status`;
  const payload = JSON.stringify({ status_key: __ITER % 2 ? 'safe' : 'unsafe', notes: 'Staging load test' });
  const headers = { Authorization: `Bearer ${reporter.token}`, 'Content-Type': 'application/json', Accept: 'application/json',
    'Idempotency-Key': `load-${__ENV.RUN_ID || 'run'}-${__VU}-${__ITER}-report` };
  const response = http.post(path, payload, { headers, tags: { name: 'member-report' } });
  if (check(response, { 'report saved': (r) => [200, 201].includes(r.status) })) {
    const retry = http.post(path, payload, { headers, tags: { name: 'report-retry' } });
    replayed.add(check(retry, { 'retry replays same report': (r) => r.headers['Idempotency-Replayed'] === 'true' && r.body === response.body }));
  } else replayed.add(false);
  sleep(0.01);
}
