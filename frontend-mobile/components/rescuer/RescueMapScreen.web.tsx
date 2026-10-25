import { statusColor } from '@/constants/statusPalette';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.web';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { SectionHeader } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  selectedAssignment?: any;
  onSendLocation: (assignmentId: number, payload: any) => Promise<void>;
  showNavigationButton?: boolean;
  onOpenNavigation?: () => void;
};

export function RescueMapScreen({
  assignments,
  evacuationCenters = [],
  activeAssignment,
  showNavigationButton = false,
  onOpenNavigation,
}: RescueMapProps) {
  const [showEvacuationCenters, setShowEvacuationCenters] = useState(true);
  const [showAssignedRoutes, setShowAssignedRoutes] = useState(true);
  const [expandedLayer, setExpandedLayer] = useState<'centers' | 'routes' | null>(null);
  const activeAssignments = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(String(item.status_key || '').replaceAll('-', '_').toLowerCase())
  );
  const mappedAssignments = activeAssignments.filter((item) => item.latitude && item.longitude);
  const visibleCenters = showEvacuationCenters
    ? evacuationCenters.filter((center) => center.latitude && center.longitude)
    : [];
  const markers = [
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
  ];
  const routes = showAssignedRoutes ? mappedAssignments.flatMap((assignment) => {
    const planned = normalizeCoordinates(assignment.route?.coordinates);
    const trail = normalizeCoordinates(assignment.route?.trail_coordinates);

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
  }) : [];

  return (
    <View style={styles.stack}>
      {showNavigationButton && onOpenNavigation ? (
        <Pressable style={styles.navigationButton} onPress={onOpenNavigation} accessibilityRole="button">
          <Text style={styles.navigationButtonText}>View directions / Offline navigation</Text>
        </Pressable>
      ) : null}

      <View style={styles.card}>
        <SectionHeader title="Barangay rescue map" />
        <View style={styles.filters}>
          <View style={styles.layerDropdown}>
            <Pressable style={styles.layerHeader} onPress={() => setExpandedLayer((value) => value === 'centers' ? null : 'centers')} accessibilityRole="button">
              <Text style={styles.filterText}>Evacuation centers ({evacuationCenters.length})</Text>
              <Text style={styles.chevron}>{expandedLayer === 'centers' ? '−' : '+'}</Text>
            </Pressable>
            {expandedLayer === 'centers' ? (
              <View style={styles.layerContent}>
                <Pressable style={styles.layerToggle} onPress={() => setShowEvacuationCenters((value) => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: showEvacuationCenters }}>
                  <Text style={styles.filterText}>Show on map</Text>
                  <Text style={styles.filterText}>{showEvacuationCenters ? 'Shown' : 'Hidden'}</Text>
                </Pressable>
                {evacuationCenters.map((center, index) => (
                  <Text key={String(center.center_id || center.evacuation_center_id || center.name || index)} style={styles.rowTitle}>
                    {center.name || center.center_name || 'Evacuation center'}
                  </Text>
                ))}
                {evacuationCenters.length === 0 ? <Text style={styles.rowMeta}>No evacuation centers available.</Text> : null}
              </View>
            ) : null}
          </View>
          <View style={styles.layerDropdown}>
            <Pressable style={styles.layerHeader} onPress={() => setExpandedLayer((value) => value === 'routes' ? null : 'routes')} accessibilityRole="button">
              <Text style={styles.filterText}>Assigned dispatch routes ({activeAssignments.length})</Text>
              <Text style={styles.chevron}>{expandedLayer === 'routes' ? '−' : '+'}</Text>
            </Pressable>
            {expandedLayer === 'routes' ? (
              <View style={styles.layerContent}>
                <Pressable style={styles.layerToggle} onPress={() => setShowAssignedRoutes((value) => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: showAssignedRoutes }}>
                  <Text style={styles.filterText}>Show on map</Text>
                  <Text style={styles.filterText}>{showAssignedRoutes ? 'Shown' : 'Hidden'}</Text>
                </Pressable>
                {activeAssignments.map((assignment) => (
                  <Text key={assignment.assignment_id} style={styles.rowTitle}>
                    {assignment.assigned_area || assignment.household_id || 'Assigned location'}
                  </Text>
                ))}
                {activeAssignments.length === 0 ? <Text style={styles.rowMeta}>No dispatch routes.</Text> : null}
              </View>
            ) : null}
          </View>
        </View>
        <MobileLeafletMap markers={markers} routes={routes} center={activeAssignment?.latitude && activeAssignment?.longitude ? { latitude: Number(activeAssignment.latitude), longitude: Number(activeAssignment.longitude) } : undefined} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: spacing.md },
  navigationButton: {
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.nav,
  },
  navigationButtonText: { color: palette.navText, fontWeight: '800' },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  filters: { gap: spacing.xs },
  layerDropdown: {
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  layerHeader: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.md,
    backgroundColor: palette.secondary,
  },
  layerContent: { gap: spacing.sm, padding: spacing.md },
  layerToggle: { flexDirection: 'row', justifyContent: 'space-between', paddingBottom: spacing.sm, borderBottomWidth: 1, borderBottomColor: palette.border },
  chevron: { color: palette.textSoft, fontSize: 18, fontWeight: '900' },
  filterText: { color: palette.text, fontSize: 12, fontWeight: '800' },
  rowTitle: { color: palette.text, fontSize: 13, fontWeight: '900' },
  rowMeta: { marginTop: 2, color: palette.textSoft, fontSize: 12, fontWeight: '700' },
});

function normalizeCoordinates(points: any[] = []) {
  return points
    .map((point) => ({
      latitude: Number(point.latitude ?? point.lat ?? point[0]),
      longitude: Number(point.longitude ?? point.lng ?? point[1]),
    }))
    .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude));
}