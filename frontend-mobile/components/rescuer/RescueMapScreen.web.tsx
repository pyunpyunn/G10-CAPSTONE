import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.web';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  onSendLocation: (assignmentId: number, payload: any) => Promise<void>;
};

export function RescueMapScreen({ assignments, evacuationCenters = [], activeAssignment }: RescueMapProps) {
  const [showEvacuationCenters, setShowEvacuationCenters] = useState(true);
  const [showAssignedRoutes, setShowAssignedRoutes] = useState(true);
  const activeAssignments = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(String(item.status_key || '').replaceAll('-', '_').toLowerCase())
  );
  const mappedAssignments = activeAssignments.filter((item) => item.latitude && item.longitude);
  const visibleCenters = showEvacuationCenters
    ? evacuationCenters.filter((center) => center.latitude && center.longitude)
    : [];
  const urgentCount = activeAssignments.filter((item) => item.priority_level === 'urgent').length;
  const activeCount = activeAssignments.length;
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
      color: assignment.priority_level === 'urgent' ? palette.unsafe : palette.evacuated,
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
        <StatusBadge label={`${assignments.length} assigned`} />
        <StatusBadge label={`${activeCount} active`} tone="en_route" />
        <StatusBadge label={`${urgentCount} urgent`} tone={urgentCount > 0 ? 'urgent' : 'neutral'} />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Barangay rescue map" />
        <View style={styles.filters}>
          <Pressable style={[styles.filter, showEvacuationCenters && styles.filterActive]} onPress={() => setShowEvacuationCenters((value) => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: showEvacuationCenters }}>
            <Text style={styles.filterText}>Evacuation centers</Text>
            {showEvacuationCenters ? <StatusBadge label="On" tone="safe" /> : <StatusBadge label="Off" />}
          </Pressable>
          <Pressable style={[styles.filter, showAssignedRoutes && styles.filterActive]} onPress={() => setShowAssignedRoutes((value) => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: showAssignedRoutes }}>
            <Text style={styles.filterText}>Assigned dispatch routes</Text>
            {showAssignedRoutes ? <StatusBadge label="On" tone="safe" /> : <StatusBadge label="Off" />}
          </Pressable>
        </View>
        <MobileLeafletMap markers={markers} routes={routes} center={activeAssignment?.latitude && activeAssignment?.longitude ? { latitude: Number(activeAssignment.latitude), longitude: Number(activeAssignment.longitude) } : undefined} />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Active dispatches" />
        {activeAssignments.length === 0 ? (
          <EmptyState icon="location-outline" title="No active dispatch assignments" />
        ) : activeAssignments.map((assignment) => (
          <View key={assignment.assignment_id} style={styles.assignmentRow}>
            <View style={styles.rowText}>
              <Text style={styles.rowTitle}>{assignment.assigned_area || assignment.household_id || 'Assigned location'}</Text>
              <Text style={styles.rowMeta}>
                {assignment.destination_label || (assignment.latitude && assignment.longitude ? 'Geotag available' : 'No household geotag yet')}
              </Text>
            </View>
            <StatusBadge label={assignment.status_label || 'Unknown'} tone={assignment.status_key} />
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: spacing.md },
  summaryRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  filters: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  filter: {
    minHeight: 40,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.card,
  },
  filterActive: { backgroundColor: palette.secondary, borderColor: palette.navActive },
  filterText: { color: palette.text, fontSize: 12, fontWeight: '800' },
  assignmentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  rowText: { flex: 1 },
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