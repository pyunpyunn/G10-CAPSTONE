import { useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import MapView, { Circle, Marker, Polyline } from 'react-native-maps';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdBadge, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type RouteProps = {
  geotag: any;
  evacuationCenters: any[];
};

const defaultRegion = {
  latitude: 10.2898,
  longitude: 123.879,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function HouseholdRouteScreen({ geotag, evacuationCenters }: RouteProps) {
  const centers = useMemo(() => evacuationCenters || [], [evacuationCenters]);
  const [selectedId, setSelectedId] = useState<string>('');
  const [roadRoute, setRoadRoute] = useState<any>(null);
  const [routeLoading, setRouteLoading] = useState(false);

  useEffect(() => {
    if (!selectedId && centers.length) {
      setSelectedId(String(centers[0].evacuation_center_id));
    }
  }, [centers, selectedId]);

  const householdPoint = useMemo(() => (
    geotag
      ? {
          latitude: Number(geotag.latitude),
          longitude: Number(geotag.longitude),
        }
      : null
  ), [geotag]);

  const selectedCenter = useMemo(() => {
    return centers.find((center) => String(center.evacuation_center_id) === selectedId) || centers[0] || null;
  }, [centers, selectedId]);

  const selectedPoint = useMemo(() => (
    selectedCenter
      ? {
          latitude: Number(selectedCenter.latitude),
          longitude: Number(selectedCenter.longitude),
        }
      : null
  ), [selectedCenter]);

  const mapRegion = householdPoint
    ? { ...householdPoint, latitudeDelta: 0.025, longitudeDelta: 0.025 }
    : defaultRegion;

  useEffect(() => {
    let ignore = false;

    async function loadRoute() {
      if (!householdPoint || !selectedPoint) {
        setRoadRoute(null);
        return;
      }

      setRouteLoading(true);

      try {
        const route = await fetchRoadRoute(householdPoint, selectedPoint);

        if (!ignore) {
          setRoadRoute(route);
        }
      } catch {
        if (!ignore) {
          setRoadRoute(null);
        }
      } finally {
        if (!ignore) {
          setRouteLoading(false);
        }
      }
    }

    loadRoute();

    return () => {
      ignore = true;
    };
  }, [householdPoint, selectedPoint]);

  const routeLine = roadRoute?.coordinates?.length >= 2 ? roadRoute.coordinates : [];
  const distanceLabel = roadRoute
    ? `${roadRoute.distance_km} km · ${roadRoute.duration_min} min by road`
    : householdPoint && selectedPoint
      ? `${distanceKm(householdPoint, selectedPoint).toFixed(2)} km direct distance`
      : 'Route distance unavailable';

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <HouseholdSection
          title="Evacuation route"
          action={<HouseholdBadge label={selectedCenter ? 'Route ready' : 'No center'} tone={selectedCenter ? 'info' : 'neutral'} />}
        />

        <MapView style={styles.map} initialRegion={mapRegion}>
          {householdPoint ? (
            <>
              <Marker coordinate={householdPoint} title="Your household" description={geotag.location_label} pinColor={palette.navActive} />
              <Circle center={householdPoint} radius={geotag.accuracy_m || 35} strokeColor="#1f3e5a55" fillColor="#1f3e5a18" />
            </>
          ) : null}

          {centers.map((center) => (
            <Marker
              key={center.evacuation_center_id}
              coordinate={{
                latitude: Number(center.latitude),
                longitude: Number(center.longitude),
              }}
              title={center.name}
              description={center.address || center.center_type}
              pinColor={String(center.evacuation_center_id) === selectedId ? palette.safe : palette.evacuated}
            />
          ))}

          {routeLine.length >= 2 ? (
            <Polyline coordinates={routeLine} strokeColor={palette.safe} strokeWidth={5} />
          ) : null}
        </MapView>

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
              <Text style={styles.distanceText}>{routeLoading ? 'Loading road route...' : distanceLabel}</Text>
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

function distanceKm(start: { latitude: number; longitude: number }, end: { latitude: number; longitude: number }) {
  const earthRadiusKm = 6371;
  const latDistance = toRadians(end.latitude - start.latitude);
  const lonDistance = toRadians(end.longitude - start.longitude);
  const a =
    Math.sin(latDistance / 2) * Math.sin(latDistance / 2) +
    Math.cos(toRadians(start.latitude)) *
      Math.cos(toRadians(end.latitude)) *
      Math.sin(lonDistance / 2) *
      Math.sin(lonDistance / 2);

  return earthRadiusKm * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

function toRadians(value: number) {
  return (value * Math.PI) / 180;
}

async function fetchRoadRoute(
  start: { latitude: number; longitude: number },
  end: { latitude: number; longitude: number }
) {
  const startPoint = `${start.longitude},${start.latitude}`;
  const endPoint = `${end.longitude},${end.latitude}`;
  const response = await fetch(
    `https://router.project-osrm.org/route/v1/driving/${startPoint};${endPoint}?overview=full&geometries=geojson`
  );
  const data = await response.json();
  const route = data.routes?.[0];

  if (!route) {
    return null;
  }

  return {
    distance_km: Number((route.distance / 1000).toFixed(2)),
    duration_min: Math.max(1, Math.round(route.duration / 60)),
    coordinates: route.geometry.coordinates.map((point: number[]) => ({
      latitude: point[1],
      longitude: point[0],
    })),
  };
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
  map: {
    height: 340,
    overflow: 'hidden',
    borderRadius: radius.md,
  },
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
