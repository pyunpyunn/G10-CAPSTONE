const test = require('node:test');
const assert = require('node:assert/strict');
const ts = require('typescript');
const fs = require('node:fs');
const vm = require('node:vm');
const output = ts.transpileModule(fs.readFileSync(require('node:path').join(__dirname, '../utils/routeGeometry.ts'), 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
}).outputText;
const context = { exports: {} }; vm.runInNewContext(output, context);
const { decodePolyline6, distanceMeters, routeProgress, bearingDegrees, downsampleRouteCoordinates } = context.exports;

test('precision-six geometry decodes longitude first and rejects truncated data', () => {
  const decoded = decodePolyline6('_izlhA~rlgdF_{geC~ywl@_kwzCn`{nI');
  assert.equal(decoded[0][0], -120.2); assert.equal(decoded[0][1], 38.5);
  assert.equal(decoded[2][0], -126.453); assert.equal(decoded[2][1], 43.252);
  assert.throws(() => decodePolyline6('_'));
});
test('a one kilometer path measures correctly and snaps to a segment midpoint', () => {
  const points = [[123.88, 10.29], [123.88, 10.298993216]];
  assert.ok(Math.abs(distanceMeters(points[0], points[1]) - 1000) < 1);
  const progress = routeProgress(points, [123.8801, 10.294496608]);
  assert.ok(Math.abs(progress.remainingM - 500) < 1);
  assert.ok(progress.offRouteM > 10 && progress.offRouteM < 12);
  assert.equal(progress.segment, 0);
  assert.ok(Math.abs(bearingDegrees(points[0], points[1])) < 0.01);
});
test('progress clamps at route ends and detects an off-route fix', () => {
  const points = [[123.88, 10.29], [123.88, 10.30]];
  assert.equal(routeProgress(points, [123.88, 10.31]).remainingM, 0);
  assert.ok(routeProgress(points, [123.89, 10.295]).offRouteM > 1000);
  assert.throws(() => routeProgress([], [0, 0]));
});
test('route display downsampling preserves endpoints and bounds rendered geometry', () => {
  const points = Array.from({ length: 5000 }, (_, index) => [123 + index / 100000, 10 + index / 100000]);
  const sampled = downsampleRouteCoordinates(points, 1200);
  assert.equal(sampled.length, 1200);
  assert.deepEqual(sampled[0], points[0]);
  assert.deepEqual(sampled.at(-1), points.at(-1));
  assert.equal(downsampleRouteCoordinates(points, 6000), points);
});
