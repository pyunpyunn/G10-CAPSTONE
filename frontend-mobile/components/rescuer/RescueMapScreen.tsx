import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type RescueMapProps = {
  assignments: any[];
  activeAssignment: any;
  onSendLocation: (assignmentId: number, payload: any) => Promise<void>;
};

export function RescueMapScreen({ assignments, activeAssignment }: RescueMapProps) {
  const mappedAssignments = assignments.filter((item) => item.latitude && item.longitude);
  const urgentCount = assignments.filter((item) => item.priority_level === 'urgent').length;
  const activeCount = assignments.filter((item) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(item.status_key)
  ).length;
  const hasActiveTarget = Boolean(activeAssignment?.latitude && activeAssignment?.longitude);
  const hasRoute = Array.isArray(activeAssignment?.route?.coordinates) && activeAssignment.route.coordinates.length >= 2;

  return (
    <View style={styles.stack}>
      <View style={styles.summaryRow}>
        <StatusBadge label={`${assignments.length} assigned`} tone="neutral" />
        <StatusBadge label={`${activeCount} active`} tone="en_route" />
        <StatusBadge label={`${urgentCount} urgent`} tone={urgentCount > 0 ? 'urgent' : 'neutral'} />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Barangay rescue map" />
        <View style={styles.webMapFallback}>
          <Ionicons name="map-outline" size={30} color={palette.navActive} />
          <Text style={styles.fallbackTitle}>Native map available on mobile</Text>
          <Text style={styles.fallbackText}>
            Open this screen in Expo Go on Android or iPhone to view assignment pins and sync live GPS.
          </Text>
          {activeAssignment?.assignment_id ? (
            <StatusBadge label="Active dispatch ready" tone="en_route" />
          ) : (
            <StatusBadge label="No active dispatch" tone="neutral" />
          )}
        </View>

        {mappedAssignments.length === 0 ? (
          <EmptyState icon="location-outline" title="No mapped assignments" />
        ) : null}

        {activeAssignment ? (
          <View style={styles.routeInfo}>
            <Ionicons name="navigate-outline" size={18} color={palette.evacuated} />
            <Text style={styles.routeInfoText}>
              {hasRoute
                ? `${activeAssignment.route?.distance_km || '-'} km · ${activeAssignment.route?.duration_min || '-'} min road route`
                : hasActiveTarget
                  ? 'Waiting for road route. Open this screen on mobile with GPS enabled.'
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
  webMapFallback: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    minHeight: 240,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.lg,
    backgroundColor: palette.secondary,
  },
  fallbackTitle: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
    textAlign: 'center',
  },
  fallbackText: {
    maxWidth: 320,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
    lineHeight: 18,
    textAlign: 'center',
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
