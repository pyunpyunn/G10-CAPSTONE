export type Coordinate = [number, number];
export type RouteStep = { instruction: string; distance_m: number; maneuver_type: number; polyline_index: number };

export function downsampleRouteCoordinates(points: Coordinate[], maxPoints = 1200): Coordinate[] {
  if (points.length <= maxPoints || maxPoints < 2) return points;
  const sampled: Coordinate[] = [];
  const step = (points.length - 1) / (maxPoints - 1);
  for (let index = 0; index < maxPoints; index++) {
    sampled.push(points[Math.round(index * step)]);
  }
  return sampled;
}

export function decodePolyline6(encoded: string): Coordinate[] {
  let index = 0, latitude = 0, longitude = 0;
  const points: Coordinate[] = [];
  function read() {
    let result = 0, shift = 0, value: number;
    do {
      if (index >= encoded.length || shift > 30) throw new Error('Invalid cached route geometry.');
      value = encoded.charCodeAt(index++) - 63;
      if (value < 0 || value > 63) throw new Error('Invalid cached route geometry.');
      result |= (value & 31) << shift;
      shift += 5;
    } while (value >= 32);
    return result & 1 ? ~(result >>> 1) : result >>> 1;
  }
  while (index < encoded.length) {
    latitude += read(); longitude += read();
    const point: Coordinate = [longitude / 1e6, latitude / 1e6];
    if (Math.abs(point[0]) > 180 || Math.abs(point[1]) > 90) throw new Error('Invalid cached route coordinate.');
    points.push(point);
  }
  return points;
}

export function distanceMeters(a: Coordinate, b: Coordinate) {
  const rad = Math.PI / 180;
  const h = Math.sin((b[1] - a[1]) * rad / 2) ** 2
    + Math.cos(a[1] * rad) * Math.cos(b[1] * rad) * Math.sin((b[0] - a[0]) * rad / 2) ** 2;
  return 12742000 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)));
}

export function bearingDegrees(a: Coordinate, b: Coordinate) {
  const r = Math.PI / 180, delta = (b[0] - a[0]) * r;
  return (Math.atan2(Math.sin(delta) * Math.cos(b[1] * r),
    Math.cos(a[1] * r) * Math.sin(b[1] * r) - Math.sin(a[1] * r) * Math.cos(b[1] * r) * Math.cos(delta)) / r + 360) % 360;
}

export function routeProgress(points: Coordinate[], position: Coordinate) {
  if (points.length < 2) throw new Error('Route needs at least two coordinates.');
  let nearest = Infinity, alongM = 0, totalM = 0;
  let snapped = points[0], segment = 0;
  const cumulative = [0];
  const scale = Math.cos(position[1] * Math.PI / 180);
  for (let i = 0; i < points.length - 1; i++) {
    const a = points[i], b = points[i + 1];
    const dx = (b[0] - a[0]) * scale, dy = b[1] - a[1];
    const denominator = dx * dx + dy * dy;
    const t = denominator ? Math.max(0, Math.min(1, (((position[0] - a[0]) * scale * dx) + (position[1] - a[1]) * dy) / denominator)) : 0;
    const candidate: Coordinate = [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
    const distance = distanceMeters(position, candidate), length = distanceMeters(a, b);
    if (distance < nearest) { nearest = distance; snapped = candidate; segment = i; alongM = totalM + length * t; }
    totalM += length;
    cumulative.push(totalM);
  }
  return { snapped, segment, offRouteM: nearest, alongM, remainingM: totalM - alongM, totalM, cumulative };
}
