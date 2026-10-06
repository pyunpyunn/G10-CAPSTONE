import { useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.web';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdBadge, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type RouteProps = {
  geotag: any;
  evacuationCenters: any[];
};

export function HouseholdRouteScreen({ geotag, evacuationCenters }: RouteProps) {
  const centers = useMemo(() => evacuationCenters || [], [evacuationCenters]);
  const [selectedId, setSelectedId] = useState('');
  const [originMode, setOriginMode] = useState<'geotag' | 'live'>('geotag');
  const [livePoint, setLivePoint] = useState<{ latitude: number; longitude: number } | null>(null);
  const [roadRoute, setRoadRoute] = useState<{ coordinates: { latitude: number; longitude: number }[]; distance_km: number; duration_min: number } | null>(null);
  const [routeLoading, setRouteLoading] = useState(false);
  const [routeError, setRouteError] = useState('');
  const selectedCenter = useMemo(
    () => centers.find((center) => String(center.evacuation_center_id) === selectedId) || centers[0] || null,
    [centers, selectedId]
  );
  const householdPoint = geotag && Number.isFinite(Number(geotag.latitude)) && Number.isFinite(Number(geotag.longitude))
    ? { latitude: Number(geotag.latitude), longitude: Number(geotag.longitude) }
    : null;
  const selectedPoint = selectedCenter && Number.isFinite(Number(selectedCenter.latitude)) && Number.isFinite(Number(selectedCenter.longitude))
    ? { latitude: Number(selectedCenter.latitude), longitude: Number(selectedCenter.longitude) }
    : null;
  const originPoint = originMode === 'live' ? livePoint : householdPoint;
  const originLatitude = originPoint?.latitude;
  const originLongitude = originPoint?.longitude;
  const centerLatitude = selectedPoint?.latitude;
  const centerLongitude = selectedPoint?.longitude;

  useEffect(() => {
    let cancelled = false;

    async function loadRoute() {
      if (originLatitude === undefined || originLongitude === undefined || centerLatitude === undefined || centerLongitude === undefined) {
        setRoadRoute(null);
        return;
      }

      setRouteLoading(true);
      setRouteError('');

      try {
        const start = `${originLongitude},${originLatitude}`;
        const end = `${centerLongitude},${centerLatitude}`;
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`);
        const data = await response.json();
        const route = data.routes?.[0];

        if (!response.ok || !route) throw new Error('No route');

        if (!cancelled) {
          setRoadRoute({
            distance_km: Number((route.distance / 1000).toFixed(2)),
            duration_min: Math.max(1, Math.round(route.duration / 60)),
            coordinates: route.geometry.coordinates.map((point: number[]) => ({ latitude: point[1], longitude: point[0] })),
          });
        }
      } catch {
        if (!cancelled) {
          setRoadRoute(null);
          setRouteError('Could not load a road route. Check your connection and try again.');
        }
      } finally {
        if (!cancelled) setRouteLoading(false);
      }
    }

    loadRoute();
    return () => { cancelled = true; };
  }, [originLatitude, originLongitude, centerLatitude, centerLongitude]);

  async function useLiveGps() {
    setRouteError('');

    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        setRouteError('Allow browser location access to route from live GPS.');
        return;
      }

      const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      setLivePoint({ latitude: current.coords.latitude, longitude: current.coords.longitude });
      setOriginMode('live');
    } catch {
      setRouteError('Unable to get your current location. Use your household geotag instead.');
    }
  }

  const markers = [
    ...(originPoint ? [{ ...originPoint, label: originMode === 'live' ? 'Live GPS location' : 'Your household', color: palette.navActive }] : []),
    ...(selectedCenter && selectedPoint ? [{ ...selectedPoint, label: selectedCenter.name, description: selectedCenter.address || selectedCenter.center_type, color: palette.safe }] : []),
  ];
  const routes = roadRoute ? [{ id: 'household-evacuation-route', label: 'Route to evacuation center', coordinates: roadRoute.coordinates, color: palette.safe }] : [];

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <HouseholdSection
          title="Evacuation route"
          action={<HouseholdBadge label={selectedCenter ? 'Route ready' : 'No center'} tone={selectedCenter ? 'info' : 'neutral'} />}
        />
        <View style={styles.originSwitch}>
          <Pressable style={[styles.originOption, originMode === 'geotag' && styles.originOptionActive]} onPress={() => setOriginMode('geotag')} accessibilityRole="radio" accessibilityState={{ selected: originMode === 'geotag' }}>
            <Text style={[styles.originText, originMode === 'geotag' && styles.originTextActive]}>Household geotag</Text>
          </Pressable>
          <Pressable style={[styles.originOption, originMode === 'live' && styles.originOptionActive]} onPress={useLiveGps} accessibilityRole="radio" accessibilityState={{ selected: originMode === 'live' }}>
            <Text style={[styles.originText, originMode === 'live' && styles.originTextActive]}>Use live GPS</Text>
          </Pressable>
        </View>
        <MobileLeafletMap markers={markers} routes={routes} center={originPoint || selectedPoint || { latitude: 10.3157, longitude: 123.8854 }} />
        <Text style={styles.routeSummary}>
          {routeLoading ? 'Finding road route...' : roadRoute ? `${roadRoute.distance_km} km · ${roadRoute.duration_min} min by road` : 'Select a geotagged origin and evacuation center.'}
        </Text>
        {routeError ? <Text style={styles.routeError}>{routeError}</Text> : null}
        {!householdPoint ? (
          <HouseholdEmpty icon="location-outline" title="No household geotag yet" />
        ) : null}
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Selected center" />
        {selectedCenter ? (
          <View style={styles.selectedBox}>
            <Text style={styles.centerTitle}>{selectedCenter.name}</Text>
            <Text style={styles.centerMeta}>{selectedCenter.address || selectedCenter.center_type}</Text>
          </View>
        ) : (
          <HouseholdEmpty icon="business-outline" title="No evacuation centers encoded" />
        )}
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Evacuation centers" />
        {centers.length === 0 ? (
          <HouseholdEmpty icon="business-outline" title="No evacuation centers encoded" />
        ) : centers.map((center) => {
          const isSelected = String(center.evacuation_center_id) === String(selectedCenter?.evacuation_center_id);

          return (
            <Pressable
              key={center.evacuation_center_id}
              style={[styles.centerRow, isSelected && styles.centerRowActive]}
              onPress={() => setSelectedId(String(center.evacuation_center_id))}
            >
              <View style={styles.rowText}>
                <Text style={styles.centerTitle}>{center.name}</Text>
                <Text style={styles.centerMeta}>{center.address || center.center_type || 'Evacuation center'}</Text>
              </View>
              <HouseholdBadge label={center.status || 'Active'} tone={center.status || 'active'} />
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: spacing.md },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  selectedBox: { borderRadius: radius.md, padding: spacing.md, backgroundColor: palette.secondary },
  originSwitch: { flexDirection: 'row', gap: 4, borderRadius: radius.md, padding: 4, backgroundColor: palette.secondary },
  originOption: { flex: 1, alignItems: 'center', borderRadius: radius.sm, paddingVertical: 9 },
  originOptionActive: { backgroundColor: palette.card },
  originText: { color: palette.textSoft, fontSize: 12, fontWeight: '800' },
  originTextActive: { color: palette.nav, fontWeight: '900' },
  routeSummary: { color: palette.safe, fontSize: 12, fontWeight: '900' },
  routeError: { color: palette.unsafe, fontSize: 12, fontWeight: '800' },
  centerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  centerRowActive: { borderRadius: radius.md, padding: spacing.sm, backgroundColor: palette.secondary },
  rowText: { flex: 1 },
  centerTitle: { color: palette.text, fontSize: 14, fontWeight: '900' },
  centerMeta: { marginTop: 3, color: palette.textSoft, fontSize: 12, fontWeight: '700' },
});