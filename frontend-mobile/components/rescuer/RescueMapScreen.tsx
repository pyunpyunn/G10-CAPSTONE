import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import MapView, { Circle, Marker, Polyline } from 'react-native-maps';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  activeAssignment: any;
  onSendLocation: (assignmentId: number, payload: any) => Promise<void>;
};

const defaultRegion = {
  latitude: 10.2898,
  longitude: 123.879,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function RescueMapScreen({ assignments, activeAssignment, onSendLocation }: RescueMapProps) {
  const watcher = useRef<Location.LocationSubscription | null>(null);
  const autoStartedAssignment = useRef<number | null>(null);
  const [tracking, setTracking] = useState(false);
  const [position, setPosition] = useState<any>(null);
  const [lastUpdated, setLastUpdated] = useState('');
  const [locError, setLocError] = useState('');
  const [syncing, setSyncing] = useState(false);
  const routeCoordinates = useMemo(() => normalizeRoute(activeAssignment?.route?.coordinates), [activeAssignment?.route?.coordinates]);
  const trailCoordinates = useMemo(() => normalizeRoute(activeAssignment?.route?.trail_coordinates), [activeAssignment?.route?.trail_coordinates]);
  const activeTarget = activeAssignment?.latitude && activeAssignment?.longitude
    ? {
        latitude: Number(activeAssignment.latitude),
        longitude: Number(activeAssignment.longitude),
      }
    : null;
  const mapRegion = position
    ? { ...position, latitudeDelta: 0.018, longitudeDelta: 0.018 }
    : routeCoordinates[0]
      ? { ...routeCoordinates[0], latitudeDelta: 0.025, longitudeDelta: 0.025 }
      : activeTarget
        ? { ...activeTarget, latitudeDelta: 0.025, longitudeDelta: 0.025 }
        : defaultRegion;

  useEffect(() => {
    return () => {
      watcher.current?.remove();
    };
  }, []);

  const startTracking = useCallback(async () => {
    if (tracking) {
      return;
    }

    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      setLocError('Location permission denied. Enable location access to sync your field position.');
      return;
    }

    setLocError('');
    setTracking(true);

    watcher.current = await Location.watchPositionAsync(
      {
        accuracy: Location.Accuracy.High,
        timeInterval: 5000,
        distanceInterval: 3,
      },
      async (location) => {
        const nextPosition = {
          latitude: location.coords.latitude,
          longitude: location.coords.longitude,
          accuracy_m: location.coords.accuracy,
        };

        setPosition(nextPosition);
        setLastUpdated(formatPhilippineTime());

        if (activeAssignment?.assignment_id) {
          try {
            setSyncing(true);
            await onSendLocation(activeAssignment.assignment_id, nextPosition);
            setLocError('');
          } catch {
            setLocError('GPS is active, but the latest location could not be saved to the backend.');
          } finally {
            setSyncing(false);
          }
        }
      }
    );
  }, [activeAssignment?.assignment_id, onSendLocation, tracking]);

  useEffect(() => {
    const shouldAutoTrack = activeAssignment?.assignment_id
      && ['accepted', 'en_route'].includes(activeAssignment.status_key)
      && autoStartedAssignment.current !== activeAssignment.assignment_id;

    if (shouldAutoTrack) {
      autoStartedAssignment.current = activeAssignment.assignment_id;
      startTracking();
    }
  }, [activeAssignment?.assignment_id, activeAssignment?.status_key, startTracking]);

  function stopTracking() {
    watcher.current?.remove();
    watcher.current = null;
    setTracking(false);
    setPosition(null);
    setLastUpdated('');
  }

  function handleTrackPress() {
    if (tracking) {
      stopTracking();
      return;
    }

    if (!activeAssignment?.assignment_id) {
      Alert.alert('No active assignment', 'You can open the map, but location sync needs an active dispatch assignment.');
    }

    startTracking();
  }

  const mappedAssignments = assignments.filter((item) => item.latitude && item.longitude);
  const urgentCount = assignments.filter((item) => item.priority_level === 'urgent').length;
  const activeCount = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(item.status_key)
  ).length;

  return (
    <View style={styles.stack}>
      <View style={styles.summaryRow}>
        <StatusBadge label={`${assignments.length} assigned`} tone="neutral" />
        <StatusBadge label={`${activeCount} active`} tone="en_route" />
        <StatusBadge label={`${urgentCount} urgent`} tone={urgentCount > 0 ? 'urgent' : 'neutral'} />
      </View>

      <View style={styles.card}>
        <SectionHeader
          title="Barangay rescue map"
          action={
            <ActionButton
              label={tracking ? 'Stop' : 'Track Me'}
              icon={tracking ? 'stop-outline' : 'navigate-outline'}
              tone={tracking ? 'danger' : 'primary'}
              onPress={handleTrackPress}
            />
          }
        />

        {locError ? (
          <View style={styles.errorStrip}>
            <Ionicons name="alert-circle-outline" size={18} color={palette.unsafe} />
            <Text style={styles.errorText}>{locError}</Text>
          </View>
        ) : null}

        {tracking ? (
          <View style={styles.syncStrip}>
            <Ionicons name="radio-outline" size={18} color={palette.evacuated} />
            <Text style={styles.syncText}>
              {syncing ? 'Syncing location...' : `Tracking active${lastUpdated ? ` · ${lastUpdated}` : ''}`}
            </Text>
          </View>
        ) : null}

        <MapView style={styles.map} region={mapRegion}>
          {routeCoordinates.length >= 2 ? (
            <Polyline coordinates={routeCoordinates} strokeColor={palette.evacuated} strokeWidth={5} />
          ) : null}

          {trailCoordinates.length >= 2 ? (
            <Polyline coordinates={trailCoordinates} strokeColor={palette.safe} strokeWidth={3} />
          ) : null}

          {mappedAssignments.map((assignment) => (
            <Marker
              key={assignment.assignment_id}
              coordinate={{
                latitude: Number(assignment.latitude),
                longitude: Number(assignment.longitude),
              }}
              title={assignment.assigned_area || assignment.household_id || 'Assignment'}
              description={assignment.status_label}
              pinColor={assignment.priority_level === 'urgent' ? palette.unsafe : palette.evacuated}
            />
          ))}

          {activeTarget ? (
            <Marker
              coordinate={activeTarget}
              title="Dispatch destination"
              description={activeAssignment?.assigned_area || activeAssignment?.household_id}
              pinColor={palette.unsafe}
            />
          ) : null}

          {position ? (
            <>
              <Marker coordinate={position} title="My location" pinColor={palette.navActive} />
              <Circle
                center={position}
                radius={position.accuracy_m || 30}
                strokeColor="#2a5a9055"
                fillColor="#2a5a9018"
              />
            </>
          ) : null}
        </MapView>

        {mappedAssignments.length === 0 ? (
          <EmptyState
            icon="location-outline"
            title="No mapped assignments"
          />
        ) : null}

        {activeAssignment ? (
          <View style={styles.routeInfo}>
            <Ionicons name="navigate-outline" size={18} color={palette.evacuated} />
            <Text style={styles.routeInfoText}>
              {routeCoordinates.length >= 2
                ? `${activeAssignment.route?.distance_km || '-'} km · ${activeAssignment.route?.duration_min || '-'} min road route`
                : activeTarget
                  ? 'Waiting for road route. Tap Track Me or Accept/En route with GPS enabled.'
                  : 'This assignment has no household geotag target yet.'}
            </Text>
          </View>
        ) : null}
      </View>

      <View style={styles.card}>
        <SectionHeader title="Map queue" />
        {assignments.length === 0 ? (
          <Text style={styles.smallText}>No dispatch assignments yet.</Text>
        ) : (
          assignments.map((assignment) => (
            <View key={assignment.assignment_id} style={styles.assignmentRow}>
              <View style={styles.rowIcon}>
                <Ionicons name="pin-outline" size={17} color={palette.navActive} />
              </View>
              <View style={styles.rowText}>
                <Text style={styles.rowTitle}>{assignment.assigned_area || assignment.household_id || 'Assigned location'}</Text>
                <Text style={styles.rowMeta}>
                  {assignment.latitude && assignment.longitude ? 'Geotag available' : 'No household geotag yet'}
                </Text>
              </View>
              <StatusBadge label={assignment.status_label} tone={assignment.status_key} />
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function normalizeRoute(points: any[] = []) {
  return points
    .map((point) => ({
      latitude: Number(point.latitude ?? point.lat ?? point[0]),
      longitude: Number(point.longitude ?? point.lng ?? point[1]),
    }))
    .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude));
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  errorStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: '#96202012',
  },
  errorText: {
    flex: 1,
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '800',
  },
  syncStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: palette.secondary,
  },
  syncText: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  map: {
    height: 340,
    overflow: 'hidden',
    borderRadius: radius.md,
  },
  routeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: palette.secondary,
  },
  routeInfoText: {
    flex: 1,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  smallText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
  },
  assignmentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  rowIcon: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
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
