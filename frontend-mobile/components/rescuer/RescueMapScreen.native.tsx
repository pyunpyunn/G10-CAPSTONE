import { statusColor } from '@/constants/statusPalette';
import { useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useMapGps } from '@/hooks/useMapGps';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.native';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { ActionButton, EmptyState, SectionHeader } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  selectedAssignment?: any;
  showNavigationButton?: boolean;
  onOpenNavigation?: () => void;
};

const defaultRegion = {
  latitude: 10.3157,
  longitude: 123.8854,
  latitudeDelta: 0.035,
  longitudeDelta: 0.035,
};

export function RescueMapScreen({
  assignments,
  evacuationCenters = [],
  activeAssignment,
  selectedAssignment,
  showNavigationButton = false,
  onOpenNavigation,
}: RescueMapProps) {
  const isFocused = useIsFocused();
  const [tracking, setTracking] = useState(true);
  const { position, error: gpsError } = useMapGps(tracking, !activeAssignment?.assignment_id);
  const [showEvacuationCenters, setShowEvacuationCenters] = useState(true);
  const [showAssignedRoutes, setShowAssignedRoutes] = useState(true);
  const [expandedLayer, setExpandedLayer] = useState<'centers' | 'routes' | null>(null);

  function stopTracking() {
    setTracking(false);
  }

  function handleTrackPress() {
    if (tracking) {
      stopTracking();
      return;
    }

    if (!activeAssignment?.assignment_id) {
      Alert.alert('No active assignment', 'You can open the map, but location sync needs an active dispatch assignment.');
    }

    setTracking(true);
  }

  const activeAssignments = useMemo(() => assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(String(item.status_key || '').replaceAll('-', '_').toLowerCase())
  ), [assignments]);
  const focusedAssignment = selectedAssignment || activeAssignment;
  const mappedAssignments = useMemo(() => activeAssignments.filter((item) => item.latitude && item.longitude), [activeAssignments]);
  const visibleCenters = useMemo(() => showEvacuationCenters
    ? evacuationCenters.filter((center) => center.latitude && center.longitude)
    : [], [evacuationCenters, showEvacuationCenters]);
  const mapMarkers = useMemo(() => [
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
      color: statusColor(assignment.status_key),
    })) : []),
  ], [visibleCenters, mappedAssignments, showAssignedRoutes]);
  const mapRoutes = useMemo(() => showAssignedRoutes ? mappedAssignments.flatMap((assignment) => {
      const route = assignment.route || {};
      const planned = normalizeCoordinates(route.coordinates);
      const trail = normalizeCoordinates(route.trail_coordinates);

      return [
        ...(planned.length > 1 ? [{
          id: `dispatch-${assignment.assignment_id}`,
          label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · planned route`,
          coordinates: planned,
          color: statusColor(assignment.status_key),
        }] : []),
        ...(trail.length > 1 ? [{
          id: `dispatch-trail-${assignment.assignment_id}`,
          label: `${assignment.assigned_area || assignment.household_id || 'Dispatch'} · GPS trail`,
          coordinates: trail,
          color: palette.navActive,
        }] : []),
      ];
    }) : [], [mappedAssignments, showAssignedRoutes]);

  return (
    <View style={styles.stack}>
      {showNavigationButton && onOpenNavigation ? (
        <Pressable
          style={({ pressed }) => [styles.navigationButton, pressed && styles.navigationButtonPressed]}
          onPress={onOpenNavigation}
          accessibilityRole="button"
        >
          <Ionicons name="navigate-outline" size={20} color="#fff" />
          <Text style={styles.navigationButtonText}>View directions / Offline navigation</Text>
        </Pressable>
      ) : null}
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
          <View style={styles.layerDropdown}>
            <Pressable
              style={styles.layerHeader}
              onPress={() => setExpandedLayer((value) => value === 'centers' ? null : 'centers')}
              accessibilityRole="button"
              accessibilityState={{ expanded: expandedLayer === 'centers' }}
            >
              <Ionicons name="business-outline" size={18} color={palette.navActive} />
              <Text style={styles.layerTitle}>Evacuation centers</Text>
              <Text style={styles.layerCount}>{evacuationCenters.length}</Text>
              <Ionicons name={expandedLayer === 'centers' ? 'chevron-up' : 'chevron-down'} size={18} color={palette.textSoft} />
            </Pressable>
            {expandedLayer === 'centers' ? (
              <View style={styles.layerContent}>
                <Pressable
                  style={styles.layerToggle}
                  onPress={() => setShowEvacuationCenters((value) => !value)}
                  accessibilityRole="checkbox"
                  accessibilityState={{ checked: showEvacuationCenters }}
                >
                  <Text style={styles.layerMeta}>Show on map</Text>
                  <Ionicons name={showEvacuationCenters ? 'checkbox' : 'square-outline'} size={20} color={palette.navActive} />
                </Pressable>
                {evacuationCenters.length ? evacuationCenters.map((center: any, index: number) => (
                  <View key={String(center.center_id || center.evacuation_center_id || center.name || index)} style={styles.layerItem}>
                    <Text style={styles.rowTitle}>{center.name || center.center_name || 'Evacuation center'}</Text>
                    <Text style={styles.rowMeta}>{center.status_label || center.status || 'Available'}</Text>
                  </View>
                )) : <Text style={styles.smallText}>No evacuation centers available.</Text>}
              </View>
            ) : null}
          </View>
          <View style={styles.layerDropdown}>
            <Pressable
              style={styles.layerHeader}
              onPress={() => setExpandedLayer((value) => value === 'routes' ? null : 'routes')}
              accessibilityRole="button"
              accessibilityState={{ expanded: expandedLayer === 'routes' }}
            >
              <Ionicons name="git-branch-outline" size={18} color={palette.navActive} />
              <Text style={styles.layerTitle}>Assigned dispatch routes</Text>
              <Text style={styles.layerCount}>{activeAssignments.length}</Text>
              <Ionicons name={expandedLayer === 'routes' ? 'chevron-up' : 'chevron-down'} size={18} color={palette.textSoft} />
            </Pressable>
            {expandedLayer === 'routes' ? (
              <View style={styles.layerContent}>
                <Pressable
                  style={styles.layerToggle}
                  onPress={() => setShowAssignedRoutes((value) => !value)}
                  accessibilityRole="checkbox"
                  accessibilityState={{ checked: showAssignedRoutes }}
                >
                  <Text style={styles.layerMeta}>Show on map</Text>
                  <Ionicons name={showAssignedRoutes ? 'checkbox' : 'square-outline'} size={20} color={palette.navActive} />
                </Pressable>
                {activeAssignments.length ? activeAssignments.map((assignment: any) => (
                  <View key={assignment.assignment_id} style={styles.layerItem}>
                    <Text style={styles.rowTitle}>{assignment.assigned_area || assignment.household_id || 'Assigned location'}</Text>
                  </View>
                )) : <Text style={styles.smallText}>No active dispatch routes.</Text>}
              </View>
            ) : null}
          </View>
        </View>

        {gpsError ? (
          <View style={styles.errorStrip}>
            <Ionicons name="alert-circle-outline" size={18} color={palette.unsafe} />
            <Text style={styles.errorText}>{gpsError}</Text>
          </View>
        ) : null}

        {isFocused ? (
          <MobileLeafletMap
            markers={mapMarkers}
            routes={mapRoutes}
            followPosition={tracking ? position : null}
            center={(focusedAssignment?.latitude && focusedAssignment?.longitude
              ? { latitude: Number(focusedAssignment.latitude), longitude: Number(focusedAssignment.longitude) }
              : defaultRegion)}
          />
        ) : null}

        {mappedAssignments.length === 0 ? (
          <EmptyState
            icon="location-outline"
            title="No mapped assignments"
          />
        ) : null}
      </View>

    </View>
  );
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  navigationButton: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.nav,
  },
  navigationButtonPressed: {
    opacity: 0.85,
  },
  navigationButtonText: {
    color: palette.navText,
    fontWeight: '800',
  },
  mapFilters: {
    gap: spacing.xs,
  },
  layerDropdown: {
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  layerHeader: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.secondary,
  },
  layerTitle: { flex: 1, color: palette.text, fontSize: 13, fontWeight: '800' },
  layerCount: { color: palette.textSoft, fontSize: 12, fontWeight: '800' },
  layerContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.sm },
  layerToggle: {
    minHeight: 40,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
  },
  layerMeta: { color: palette.textSoft, fontSize: 12, fontWeight: '700' },
  layerItem: { paddingTop: spacing.sm },
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
  smallText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
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
