import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.native';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  onSendLocation: (assignmentId: number, payload: any) => Promise<void>;
};

const defaultRegion = {
  latitude: 10.3157,
  longitude: 123.8854,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function RescueMapScreen({ assignments, evacuationCenters = [], activeAssignment, onSendLocation }: RescueMapProps) {
  const watcher = useRef<Location.LocationSubscription | null>(null);
  const [tracking, setTracking] = useState(false);
  const [position, setPosition] = useState<any>(null);
  const [lastUpdated, setLastUpdated] = useState('');
  const [locError, setLocError] = useState('');
  const [syncing, setSyncing] = useState(false);
  const [showEvacuationCenters, setShowEvacuationCenters] = useState(true);
  const [showAssignedRoutes, setShowAssignedRoutes] = useState(true);

  useEffect(() => {
    return () => {
      watcher.current?.remove();
    };
  }, []);

  async function startTracking() {
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
  }

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

  const activeAssignments = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(String(item.status_key || '').replaceAll('-', '_').toLowerCase())
  );
  const mappedAssignments = activeAssignments.filter((item) => item.latitude && item.longitude);
  const visibleCenters = showEvacuationCenters
    ? evacuationCenters.filter((center) => center.latitude && center.longitude)
    : [];
  const urgentCount = activeAssignments.filter((item) => item.priority_level === 'urgent').length;
  const activeCount = activeAssignments.length;
  const mapMarkers = [
    ...visibleCenters.map((center) => ({
      latitude: center.latitude,
      longitude: center.longitude,
      label: center.name,
      description: `Evacuation center · ${center.status || 'Active'}`,
      color: palette.safe,
    })),
    ...(showAssignedRoutes ? mappedAssignments.map((assignment) => ({
      latitude: assignment.latitude,
      longitude: assignment.longitude,
      label: assignment.assigned_area || assignment.household_id || 'Dispatch destination',
      description: assignment.status_label || 'Active dispatch',
      color: assignment.priority_level === 'urgent' ? palette.unsafe : palette.evacuated,
    })) : []),
    ...(position ? [{ ...position, label: 'My live GPS', description: tracking ? 'Location tracking active' : '', color: palette.navActive }] : []),
  ];
  const mapRoutes = showAssignedRoutes ? mappedAssignments.flatMap((assignment) => {
    const route = assignment.route || {};
    const planned = normalizeCoordinates(route.coordinates);
    const trail = normalizeCoordinates(route.trail_coordinates);

    return [
      ...(planned.length > 1 ? [{
        id: `dispatch-${assignment.assignment_id}`,
        label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · planned route`,
        coordinates: planned,
        color: assignment.priority_level === 'urgent' ? palette.unsafe : palette.evacuated,
      }] : []),
      ...(trail.length > 1 ? [{
        id: `dispatch-trail-${assignment.assignment_id}`,
        label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · GPS trail`,
        coordinates: trail,
        color: palette.navActive,
      }] : []),
    ];
  }) : [];

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

        <View style={styles.mapFilters}>
          <Pressable
            style={[styles.mapFilter, showEvacuationCenters && styles.mapFilterActive]}
            onPress={() => setShowEvacuationCenters((value) => !value)}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: showEvacuationCenters }}
          >
            <Ionicons name="business-outline" size={16} color={showEvacuationCenters ? palette.navActive : palette.textSoft} />
            <Text style={styles.mapFilterText}>Evacuation centers</Text>
            {showEvacuationCenters ? <Ionicons name="checkmark-circle" size={16} color={palette.safe} /> : null}
          </Pressable>
          <Pressable
            style={[styles.mapFilter, showAssignedRoutes && styles.mapFilterActive]}
            onPress={() => setShowAssignedRoutes((value) => !value)}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: showAssignedRoutes }}
          >
            <Ionicons name="git-branch-outline" size={16} color={showAssignedRoutes ? palette.navActive : palette.textSoft} />
            <Text style={styles.mapFilterText}>Assigned dispatch routes</Text>
            {showAssignedRoutes ? <Ionicons name="checkmark-circle" size={16} color={palette.safe} /> : null}
          </Pressable>
        </View>

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

        <MobileLeafletMap markers={mapMarkers} routes={mapRoutes} center={position || defaultRegion} />

        {mappedAssignments.length === 0 ? (
          <EmptyState
            icon="location-outline"
            title="No mapped assignments"
          />
        ) : null}
      </View>

      <View style={styles.card}>
        <SectionHeader title="Active dispatches" />
        {activeAssignments.length === 0 ? (
          <Text style={styles.smallText}>No active dispatch assignments.</Text>
        ) : (
          activeAssignments.map((assignment) => (
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

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  mapFilters: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  mapFilter: {
    minHeight: 40,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.card,
  },
  mapFilterActive: { backgroundColor: palette.secondary, borderColor: palette.navActive },
  mapFilterText: { color: palette.text, fontSize: 12, fontWeight: '800' },
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

function normalizeCoordinates(points: any[] = []) {
  return points
    .map((point) => ({
      latitude: Number(point.latitude ?? point.lat ?? point[0]),
      longitude: Number(point.longitude ?? point.lng ?? point[1]),
    }))
    .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude));
}
