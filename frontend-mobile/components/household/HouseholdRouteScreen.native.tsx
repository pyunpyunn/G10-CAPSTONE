import { useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.native';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdEmpty, HouseholdSection } from './HouseholdUI';
import { useMapGps } from '@/hooks/useMapGps';
import { RescuerSelect } from '@/components/rescuer/RescuerUI';

type RouteProps = {
  active?: boolean;
  geotag: any;
  evacuationCenters: any[];
};

const defaultRegion = {
  latitude: 10.3157,
  longitude: 123.8854,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function HouseholdRouteScreen({ geotag, evacuationCenters, active = true }: RouteProps) {
  const centers = useMemo(() => (evacuationCenters || []).filter((center) => {
    const status = String(center.status || 'active').trim().toLowerCase();
    return center.route_available !== false
      && center.vacancy != null && Number(center.vacancy) > 0
      && ['active', 'open', 'available'].includes(status)
      && center.latitude != null && center.longitude != null
      && Number.isFinite(Number(center.latitude)) && Math.abs(Number(center.latitude)) <= 90
      && Number.isFinite(Number(center.longitude)) && Math.abs(Number(center.longitude)) <= 180;
  }), [evacuationCenters]);
  const [selectedId, setSelectedId] = useState<string>('');
  const [originMode, setOriginMode] = useState<'geotag' | 'live'>('geotag');
  const [livePoint, setLivePoint] = useState<{ latitude: number; longitude: number } | null>(null);
  const [roadRoute, setRoadRoute] = useState<{ coordinates: { latitude: number; longitude: number }[]; distance_km: number; duration_min: number } | null>(null);
  const [routeLoading, setRouteLoading] = useState(false);
  const [routeError, setRouteError] = useState('');
  const successfulRoute = useRef<{ key: string; route: { coordinates: { latitude: number; longitude: number }[]; distance_km: number; duration_min: number } } | null>(null);
  const gps = useMapGps(active && originMode === 'live');

  useEffect(() => {
    if (!centers.some((center) => String(center.evacuation_center_id) === selectedId)) {
      setSelectedId(centers.length ? String(centers[0].evacuation_center_id) : '');
    }
  }, [centers, selectedId]);

  const householdPoint = geotag && geotag.latitude != null && geotag.longitude != null && Number.isFinite(Number(geotag.latitude)) && Number.isFinite(Number(geotag.longitude))
    ? {
        latitude: Number(geotag.latitude),
        longitude: Number(geotag.longitude),
      }
    : null;

  const selectedCenter = useMemo(() => {
    return centers.find((center) => String(center.evacuation_center_id) === selectedId) || centers[0] || null;
  }, [centers, selectedId]);

  const selectedPoint = selectedCenter
    ? {
        latitude: Number(selectedCenter.latitude),
        longitude: Number(selectedCenter.longitude),
      }
    : null;

  const originPoint = originMode === 'live' ? livePoint : householdPoint;
  const originLatitude = originPoint?.latitude;
  const originLongitude = originPoint?.longitude;
  const centerLatitude = selectedPoint?.latitude;
  const centerLongitude = selectedPoint?.longitude;
  const routeKey = JSON.stringify([originLatitude, originLongitude, centerLatitude, centerLongitude]);

  useEffect(() => {
    if (!active) return;
    let cancelled = false;
    const controller = new AbortController();

    async function loadRoute() {
      if (originLatitude === undefined || originLongitude === undefined || centerLatitude === undefined || centerLongitude === undefined) {
        setRoadRoute(null);
        setRouteError('');
        setRouteLoading(false);
        return;
      }

      const cachedRoute = successfulRoute.current;
      if (cachedRoute?.key === routeKey) {
        setRoadRoute(cachedRoute.route);
        setRouteError('');
        setRouteLoading(false);
        return;
      }

      setRoadRoute(null);
      setRouteLoading(true);
      setRouteError('');
      const timeout = setTimeout(() => controller.abort(), 15000);

      try {
        const start = `${originLongitude},${originLatitude}`;
        const end = `${centerLongitude},${centerLatitude}`;
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`, { signal: controller.signal });
        const data = await response.json();
        const route = data.routes?.[0];

        if (!response.ok || data.code !== 'Ok' || !route || !Array.isArray(route.geometry?.coordinates) || route.geometry.coordinates.length < 2) {
          throw new Error('No road route is available for these locations.');
        }

        if (!cancelled) {
          const nextRoute = {
            distance_km: Number((route.distance / 1000).toFixed(2)),
            duration_min: Math.max(1, Math.round(route.duration / 60)),
            coordinates: route.geometry.coordinates.map((point: number[]) => ({ latitude: point[1], longitude: point[0] })),
          };
          successfulRoute.current = { key: routeKey, route: nextRoute };
          setRoadRoute(nextRoute);
        }
      } catch {
        if (!cancelled) {
          setRoadRoute(null);
          setRouteError('Could not load a road route. Check your connection and try again.');
        }
      } finally {
        clearTimeout(timeout);
        if (!cancelled) setRouteLoading(false);
      }
    }

    loadRoute();
    return () => { cancelled = true; controller.abort(); };
  }, [active, originLatitude, originLongitude, centerLatitude, centerLongitude, routeKey]);

  async function useLiveGps() {
    setRouteError('');

    try {
      const permission = await Location.requestForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        setRouteError('Allow location access to route from your live GPS position.');
        return;
      }

      const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      setLivePoint({ latitude: current.coords.latitude, longitude: current.coords.longitude });
      setOriginMode('live');
    } catch {
      setRouteError('Unable to get your current location. Use your household geotag instead.');
    }
  }

  const mapMarkers = [
    ...(originPoint ? [{ ...originPoint, label: originMode === 'live' ? 'Live GPS location' : 'Your household', color: palette.navActive }] : []),
    ...(selectedPoint ? [{ ...selectedPoint, label: selectedCenter?.name || 'Evacuation center', color: palette.safe }] : []),
  ];
  const mapRoutes = roadRoute ? [{ id: 'household-evacuation-route', label: 'Route to evacuation center', coordinates: roadRoute.coordinates, color: palette.safe }] : [];
  const estimatedDistanceKm = originPoint && selectedPoint
    ? distanceBetween(originPoint, selectedPoint) * 1.3
    : null;
  const distanceKm = roadRoute?.distance_km ?? estimatedDistanceKm;
  const taxiMinutes = roadRoute?.duration_min
    ?? (distanceKm != null ? Math.max(1, Math.round((distanceKm / 20) * 60)) : null);
  const motorcycleMinutes = distanceKm != null ? Math.max(1, Math.round((distanceKm / 24) * 60)) : null;
  const walkingMinutes = distanceKm != null ? Math.max(1, Math.round((distanceKm / 4) * 60)) : null;

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <HouseholdSection title="Evacuation route" />

        <View style={styles.originSwitch} accessibilityRole="radiogroup">
          <Pressable
            style={[styles.originOption, originMode === 'geotag' && styles.originOptionActive]}
            onPress={() => setOriginMode('geotag')}
            accessibilityRole="radio"
            accessibilityState={{ selected: originMode === 'geotag' }}
          >
            <Text style={[styles.originOptionText, originMode === 'geotag' && styles.originOptionTextActive]}>Household geotag</Text>
          </Pressable>
          <Pressable
            style={[styles.originOption, originMode === 'live' && styles.originOptionActive]}
            onPress={useLiveGps}
            accessibilityRole="radio"
            accessibilityState={{ selected: originMode === 'live' }}
          >
            <Text style={[styles.originOptionText, originMode === 'live' && styles.originOptionTextActive]}>Use live GPS</Text>
          </Pressable>
        </View>

        {active ? (
          <MobileLeafletMap markers={mapMarkers} routes={mapRoutes} center={originPoint || selectedPoint || defaultRegion} followPosition={originMode === 'live' ? gps.position || livePoint : null} showFollowControl={false} />
        ) : null}
        {gps.error ? <Text style={styles.routeError}>{gps.error}</Text> : null}
        {routeError ? <Text style={styles.routeError}>{routeError}</Text> : null}
        {!householdPoint ? (
          <HouseholdEmpty
            icon="location-outline"
            title="No household geotag yet"
          />
        ) : null}
      </View>

      <View style={styles.selectedCard}>
        <View style={styles.selectedHeader}>
          <Text style={styles.selectedEyebrow}>SELECTED CENTER</Text>
        </View>
        {selectedCenter ? (
          <>
            <View style={styles.selectedMain}>
              <View style={styles.selectedTitleBlock}>
                <Text style={styles.centerTitle}>{selectedCenter.name}</Text>
                <Text style={styles.centerMeta}>{selectedCenter.address || 'Address unavailable'}</Text>
              </View>
              <Ionicons name="business-outline" size={24} color={palette.textSoft} />
            </View>
            <View style={styles.selectedDivider} />
            <View style={styles.selectedMetrics}>
              <CenterMetric
                icon="map-marker-distance"
                value={distanceKm == null ? '—' : `${roadRoute ? '' : '~'}${distanceKm.toFixed(2)} km`}
                label="Distance"
                borderRight
                borderBottom
              />
              <CenterMetric
                icon="taxi"
                value={taxiMinutes == null ? '—' : `~${taxiMinutes} min`}
                label="Taxi"
                borderBottom
              />
              <CenterMetric
                icon="motorbike"
                value={motorcycleMinutes == null ? '—' : `~${motorcycleMinutes} min`}
                label="Motorcycle"
                borderRight
              />
              <CenterMetric
                icon="walk"
                value={walkingMinutes == null ? '—' : `~${walkingMinutes} min`}
                label="Walk"
              />
            </View>
          </>
        ) : (
          <HouseholdEmpty icon="business-outline" title="No vacant evacuation centers available" />
        )}
      </View>

      {centers.length === 0 ? (
        <HouseholdEmpty icon="business-outline" title="No vacant evacuation centers available" />
      ) : (
        <View style={styles.selectorWrap}>
          <RescuerSelect
            title="Select evacuation center"
            placeholder="Vacant evacuation centers"
            options={centers.map((center) => ({
              key: String(center.evacuation_center_id),
              label: center.name || 'Evacuation center',
              icon: centerIcon(center),
              ...getCenterTravelMetrics(originPoint, center, String(center.evacuation_center_id) === selectedId
                ? { distanceKm, taxiMinutes, motorcycleMinutes, walkingMinutes, hasRoadRoute: !!roadRoute }
                : undefined),
            }))}
            value={selectedId}
            onChange={setSelectedId}
            accessibilityLabel="Choose evacuation center"
            showLabel={false}
            highlightSelectedOption
            triggerTone="nav"
            triggerLabel="Vacant evacuation centers"
            triggerVariant="trustedAction"
          />
        </View>
      )}
    </View>
  );
}

function CenterMetric({
  icon,
  value,
  label,
  borderRight = false,
  borderBottom = false,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  value: string;
  label: string;
  borderRight?: boolean;
  borderBottom?: boolean;
}) {
  return (
    <View style={styles.metric}>
      {borderRight ? <View style={styles.metricRightDivider} /> : null}
      {borderBottom ? <View style={styles.metricBottomDivider} /> : null}
      <MaterialCommunityIcons name={icon} size={17} color="#1B7A43" />
      <View style={styles.metricText}>
        <Text style={styles.metricValue}>{value}</Text>
        <Text style={styles.metricMeta}>{label}</Text>
      </View>
    </View>
  );
}

function centerIcon(center: any): keyof typeof MaterialCommunityIcons.glyphMap {
  const type = `${center.center_type || ''} ${center.name || ''}`.toLowerCase();

  if (type.includes('school')) return 'school';
  if (type.includes('barangay hall')) return 'town-hall';
  if (type.includes('gymnasium') || type.includes('covered court')) return 'home-city';

  return 'domain';
}

function getCenterTravelMetrics(
  origin: { latitude: number; longitude: number } | null,
  center: any,
  selectedMetrics?: {
    distanceKm: number | null;
    taxiMinutes: number | null;
    motorcycleMinutes: number | null;
    walkingMinutes: number | null;
    hasRoadRoute: boolean;
  },
) {
  if (!origin) return { travelTimes: [] };

  const latitude = Number(center.latitude);
  const longitude = Number(center.longitude);

  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return { travelTimes: [] };

  const estimatedDistanceKm = distanceBetween(origin, { latitude, longitude }) * 1.3;
  const distanceKm = selectedMetrics?.distanceKm ?? estimatedDistanceKm;
  const taxiMinutes = selectedMetrics?.taxiMinutes ?? Math.max(1, Math.round((estimatedDistanceKm / 20) * 60));
  const motorcycleMinutes = selectedMetrics?.motorcycleMinutes ?? Math.max(1, Math.round((estimatedDistanceKm / 24) * 60));
  const walkingMinutes = selectedMetrics?.walkingMinutes ?? Math.max(1, Math.round((estimatedDistanceKm / 4) * 60));

  return {
    travelDistanceKm: distanceKm,
    travelDistanceEstimated: selectedMetrics ? !selectedMetrics.hasRoadRoute : true,
    travelTimes: [
      { mode: 'taxi' as const, minutes: taxiMinutes },
      { mode: 'motorcycle' as const, minutes: motorcycleMinutes },
      { mode: 'walk' as const, minutes: walkingMinutes },
    ],
  };
}

function distanceBetween(start: { latitude: number; longitude: number }, end: { latitude: number; longitude: number }) {
  const toRadians = (degrees: number) => degrees * (Math.PI / 180);
  const latitudeDifference = toRadians(end.latitude - start.latitude);
  const longitudeDifference = toRadians(end.longitude - start.longitude);
  const value = Math.sin(latitudeDifference / 2) ** 2
    + Math.cos(toRadians(start.latitude))
    * Math.cos(toRadians(end.latitude))
    * Math.sin(longitudeDifference / 2) ** 2;

  return 6371 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  selectedCard: {
    gap: 0,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    borderRadius: 16,
    padding: 16,
    backgroundColor: '#FFFFFF',
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  selectedHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    marginBottom: 14,
  },
  selectedEyebrow: {
    color: '#64748B',
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.55,
  },
  selectedMain: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  selectedTitleBlock: {
    flex: 1,
  },
  selectedDivider: {
    height: 1,
    marginVertical: 15,
    backgroundColor: '#E2E8F0',
  },
  selectedMetrics: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'center',
  },
  metric: {
    position: 'relative',
    width: '50%',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 4,
  },
  metricRightDivider: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    width: 1,
    backgroundColor: '#E2E8F0',
  },
  metricBottomDivider: {
    position: 'absolute',
    right: 0,
    bottom: 0,
    left: 0,
    height: 1,
    backgroundColor: '#E2E8F0',
  },
  metricText: {
    gap: 1,
  },
  metricValue: {
    color: '#0F172A',
    fontSize: 14,
    fontWeight: '700',
  },
  metricMeta: {
    color: '#64748B',
    fontSize: 11,
  },
  selectorWrap: {
    marginTop: -2,
  },
  originSwitch: {
    flexDirection: 'row',
    gap: 4,
    borderRadius: radius.md,
    padding: 4,
    backgroundColor: palette.secondary,
  },
  originOption: { flex: 1, alignItems: 'center', borderRadius: radius.sm, paddingVertical: 9 },
  originOptionActive: { backgroundColor: palette.card },
  originOptionText: { color: palette.textSoft, fontSize: 12, fontWeight: '800' },
  originOptionTextActive: { color: palette.nav, fontWeight: '900' },
  routeError: { color: palette.unsafe, fontSize: 12, fontWeight: '800' },
  retryRoute: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 6, paddingVertical: 6 },
  retryRouteText: { color: palette.navActive, fontSize: 12, fontWeight: '900' },
  centerTitle: {
    color: '#0F172A',
    fontSize: 19,
    lineHeight: 24,
    fontWeight: '700',
  },
  centerMeta: {
    marginTop: 6,
    color: '#64748B',
    fontSize: 13,
    lineHeight: 18,
  },
  centerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  centerRowActive: {
    borderRadius: radius.md,
    borderTopWidth: 0,
    padding: spacing.sm,
    backgroundColor: palette.secondary,
  },
  rowIcon: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  rowText: {
    flex: 1,
  },
  rowTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  rowMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
});
