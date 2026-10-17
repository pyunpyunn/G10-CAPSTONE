import { statusColor } from './statusPalette'

export function pointReady(item) {
  return item?.latitude !== null && item?.latitude !== undefined && item?.latitude !== ''
    && item?.longitude !== null && item?.longitude !== undefined && item?.longitude !== ''
    && Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude))
    && Math.abs(Number(item.latitude)) <= 90 && Math.abs(Number(item.longitude)) <= 180
}
export function siteColor(site) {
  if (site.capacity == null || !Number(site.capacity) || site.occupancy == null) return statusColor('unchecked')
  const ratio = Number(site.occupancy) / Number(site.capacity)
  return statusColor(ratio >= 1 || ratio > .85 ? 'unsafe' : ratio > .6 ? 'warning' : 'safe')
}
