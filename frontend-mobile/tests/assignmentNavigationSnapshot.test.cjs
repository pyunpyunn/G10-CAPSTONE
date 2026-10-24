const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const context = { exports: {} };
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/assignmentNavigationSnapshot.ts'), 'utf8'),
  { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, context);
const { assignmentNavigationSnapshot } = context.exports;
const points = [{ latitude: 10.29, longitude: 123.88 }, { latitude: 10.292, longitude: 123.882 }];
const assignment = { assignment_id: 12, status_key: 'accepted', household_id: 'HH-A',
  route_notes: { assigned_household_ids: ['HH-A'] },
  route: { household_id: 'HH-A', coordinates: points, distance_km: 1.2, duration_min: 3, steps: [{ instruction: 'Turn right' }] } };
const overview = { profile: { responder: { team_id: 7 } }, assignments: [assignment] };

test('accepted dashboard assignment and its actual road geometry are available without device bootstrap', () => {
  const result = assignmentNavigationSnapshot(overview);
  assert.equal(result.dispatches[0].status, 'accepted');
  assert.equal(result.routes[0].assignment_id, 12);
  assert.equal(result.routes[0].distance_m, 1200);
  assert.equal(result.routes[0].duration_s, 180);
  assert.equal(result.routes[0].coordinates, points);
  assert.equal(result.routes[0].steps[0].instruction, 'Turn right');
});

test('live dispatch road route takes precedence over team-base route; walking cache is retained', () => {
  const result = assignmentNavigationSnapshot(overview, { routes: [
    { household_id: 'HH-A', profile: 'auto', polyline6: 'old' },
    { household_id: 'HH-A', profile: 'pedestrian', polyline6: 'walking' },
    { household_id: 'HH-OTHER', profile: 'auto', polyline6: 'not-assigned' },
  ] });
  assert.equal(result.routes.length, 2);
  assert.equal(result.routes[0].coordinates, points);
  assert.equal(result.routes[1].profile, 'pedestrian');
});

test('closed or mismatched household routes never overwrite active directions', () => {
  const result = assignmentNavigationSnapshot({ ...overview, assignments: [assignment,
    { ...assignment, assignment_id: 13, status_key: 'completed' },
    { ...assignment, assignment_id: 14, household_id: 'HH-B' },
    { ...assignment, assignment_id: 15, household_id: null, route_notes: { assigned_household_ids: [] } },
  ] });
  assert.equal(result.routes.length, 1);
  assert.equal(result.routes[0].assignment_id, 12);
});
