import { useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.native';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdBadge, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type RouteProps = {
  geotag: any;
  evacuationCenters: any[];
};

const defaultRegion = {
  latitude: 10.3157,
  longitude: 123.8854,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function HouseholdRouteScreen({ geotag, evacuationCenters }: RouteProps) {
  const centers = useMemo(() => evacuationCenters || [], [evacuationCenters]);
  const [selectedId, setSelectedId] = useState<string>('');
  const [originMode, setOriginMode] = useState<'geotag' | 'live'>('geotag');
  const [livePoint, setLivePoint] = useState<{ latitude: number; longitude: number } | null>(null);
  const [roadRoute, setRoadRoute] = useState<{ coordinates: { latitude: number; longitude: number }[]; distance_km: number; duration_min: number } | null>(null);
  const [routeLoading, setRouteLoading] = useState(false);
  const [routeError, setRouteError] = useState('');

  useEffect(() => {
    if (!selectedId && centers.length) {
      setSelectedId(String(centers[0].evacuation_center_id));
    }
  }, [centers, selectedId]);

  const householdPoint = geotag && Number.isFinite(Number(geotag.latitude)) && Number.isFinite(Number(geotag.longitude))
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

  useEffect(() => {
    let cancelled = false;

    async function loadRoute() {
      if (originLatitude === undefined || originLongitude === undefined || centerLatitude === undefined || centerLongitude === undefined) {
        setRoadRoute(null);
        setRouteError('');
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

        if (!response.ok || !route) {
          throw new Error('No road route is available for these locations.');
        }

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
  const distanceLabel = roadRoute
    ? `${roadRoute.distance_km} km · ${roadRoute.duration_min} min by road`
    : routeLoading
      ? 'Finding road route...'
      : 'Route distance unavailable';

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <HouseholdSection
          title="Evacuation route"
          action={<HouseholdBadge label={selectedCenter ? 'Route ready' : 'No center'} tone={selectedCenter ? 'info' : 'neutral'} />}
        />

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

        <MobileLeafletMap markers={mapMarkers} routes={mapRoutes} center={originPoint || selectedPoint || defaultRegion} />
        {routeError ? <Text style={styles.routeError}>{routeError}</Text> : null}

        {!householdPoint ? (
          <HouseholdEmpty
            icon="location-outline"
            title="No household geotag yet"
          />
        ) : null}
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Selected center" />
        {selectedCenter ? (
          <View style={styles.selectedBox}>
            <View style={styles.centerIcon}>
              <Ionicons name="business-outline" size={20} color={palette.navActive} />
            </View>
            <View style={styles.centerText}>
              <Text style={styles.centerTitle}>{selectedCenter.name}</Text>
              <Text style={styles.centerMeta}>{selectedCenter.address || selectedCenter.center_type}</Text>
              <Text style={styles.distanceText}>{distanceLabel}</Text>
            </View>
          </View>
        ) : (
          <HouseholdEmpty icon="business-outline" title="No evacuation centers encoded" />
        )}
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Evacuation centers" />
        {centers.length === 0 ? (
          <HouseholdEmpty icon="business-outline" title="No evacuation centers encoded" />
        ) : (
          centers.map((center) => {
            const isSelected = String(center.evacuation_center_id) === selectedId;

            return (
              <Pressable
                key={center.evacuation_center_id}
                style={[styles.centerRow, isSelected && styles.centerRowActive]}
                onPress={() => setSelectedId(String(center.evacuation_center_id))}
              >
                <View style={styles.rowIcon}>
                  <Ionicons name={isSelected ? 'navigate-outline' : 'business-outline'} size={17} color={palette.navActive} />
                </View>
                <View style={styles.rowText}>
                  <Text style={styles.rowTitle}>{center.name}</Text>
                  <Text style={styles.rowMeta}>
                    {center.vacancy !== null && center.vacancy !== undefined
                      ? `${center.vacancy} slots available`
                      : 'Capacity not encoded'}
                  </Text>
                </View>
                <HouseholdBadge label={center.status || 'Active'} tone={center.status || 'active'} />
              </Pressable>
            );
          })
        )}
      </View>
    </View>
  );
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
  selectedBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  centerIcon: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  centerText: {
    flex: 1,
  },
  centerTitle: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
  },
  centerMeta: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  distanceText: {
    marginTop: 5,
    color: palette.safe,
    fontSize: 12,
    fontWeight: '900',
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
