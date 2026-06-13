import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatTile, StatusBadge } from './RescuerUI';

type DashboardProps = {
  overview: any;
  onOpenMap: () => void;
  onOpenReport: () => void;
  onStatusChange: (assignmentId: number, status: string) => void;
};

export function RescuerDashboardScreen({
  overview,
  onOpenMap,
  onOpenReport,
  onStatusChange,
}: DashboardProps) {
  const profile = overview?.profile || {};
  const responder = profile.responder || {};
  const activeEvent = overview?.active_event;
  const summary = overview?.summary || {};
  const assignments = overview?.assignments || [];
  const activeAssignment = assignments.find((item: any) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(item.status_key)
  );

  return (
    <View style={styles.stack}>
      <View style={styles.identityCard}>
        <View style={styles.identityTop}>
          <View style={styles.identityText}>
            <Text style={styles.kicker}>Field console</Text>
            <Text style={styles.name}>{responder.full_name || profile.user?.full_name || 'Responder'}</Text>
            <Text style={styles.meta}>
              {responder.responder_code || profile.user?.username || 'Mobile account'} ·{' '}
              {responder.team_name || 'No team assigned'}
            </Text>
          </View>
          <View style={styles.statusWrap}>
            <StatusBadge label={responder.duty_status || 'Stand-by'} tone={responder.duty_status || 'neutral'} />
          </View>
        </View>

        {activeEvent ? (
          <View style={styles.eventStrip}>
            <Ionicons name="warning-outline" size={18} color={palette.warning} />
            <View style={styles.eventCopy}>
              <Text style={styles.eventTitle}>{activeEvent.name}</Text>
              <Text style={styles.eventMeta}>
                {activeEvent.type} · {activeEvent.severity} · {formatDate(activeEvent.started_at)}
              </Text>
            </View>
          </View>
        ) : (
          <View style={styles.eventStrip}>
            <Ionicons name="shield-checkmark-outline" size={18} color={palette.safe} />
            <View style={styles.eventCopy}>
              <Text style={styles.eventTitle}>No active disaster event</Text>
              <Text style={styles.eventMeta}>Stand by for HQ dispatch or monitoring instructions.</Text>
            </View>
          </View>
        )}
      </View>

      <View style={styles.statsRow}>
        <StatTile label="Assigned" value={summary.total_assignments || 0} tone="neutral" />
        <StatTile label="Active" value={summary.active_assignments || 0} tone="en_route" />
        <StatTile label="Done" value={summary.completed_assignments || 0} tone="completed" />
      </View>

      {activeAssignment ? (
        <View style={styles.card}>
          <SectionHeader title="Current assignment" action={<StatusBadge label={activeAssignment.status_label} tone={activeAssignment.status_key} />} />
          <Text style={styles.assignmentTitle}>{activeAssignment.assigned_area || activeAssignment.household_id || 'Assigned area'}</Text>
          <Text style={styles.assignmentMeta}>
            {activeAssignment.event_name} · {activeAssignment.priority_level || 'medium'} priority
          </Text>
          {activeAssignment.dispatch_notes ? (
            <Text style={styles.note}>{activeAssignment.dispatch_notes}</Text>
          ) : null}
          <View style={styles.buttonGrid}>
            {activeAssignment.status_key === 'dispatched' ? (
              <ActionButton
                label="Accept"
                icon="checkmark-outline"
                tone="light"
                onPress={() => onStatusChange(activeAssignment.assignment_id, 'accepted')}
              />
            ) : null}
            {activeAssignment.status_key === 'accepted' ? (
              <ActionButton
                label="En route"
                icon="navigate-outline"
                tone="light"
                onPress={() => onStatusChange(activeAssignment.assignment_id, 'en_route')}
              />
            ) : null}
            <ActionButton
              label="Complete"
              icon="flag-outline"
              disabled={!['en_route', 'on_scene'].includes(activeAssignment.status_key)}
              onPress={() => onStatusChange(activeAssignment.assignment_id, 'completed')}
            />
          </View>
        </View>
      ) : (
        <EmptyState
          icon="radio-outline"
          title="No active assignment"
        />
      )}

      <View style={styles.card}>
        <SectionHeader title="Mission queue" />
        {assignments.length === 0 ? (
          <EmptyState icon="file-tray-outline" title="No assignments yet" />
        ) : (
          assignments.map((assignment: any) => (
            <Pressable key={assignment.assignment_id} style={styles.queueItem} onPress={onOpenMap}>
              <View style={styles.queueIcon}>
                <Ionicons name="trail-sign-outline" size={18} color={palette.navActive} />
              </View>
              <View style={styles.queueText}>
                <Text style={styles.queueTitle}>{assignment.assigned_area || assignment.household_id || 'Assigned location'}</Text>
                <Text style={styles.queueMeta}>
                  {assignment.assignment_code || `Assignment ${assignment.assignment_id}`} · {formatDate(assignment.assigned_at)}
                </Text>
              </View>
              <StatusBadge label={assignment.status_label} tone={assignment.status_key} />
            </Pressable>
          ))
        )}
      </View>

      <View style={styles.quickRow}>
        <Pressable style={styles.quickLink} onPress={onOpenMap}>
          <Ionicons name="map-outline" size={18} color={palette.navActive} />
          <Text style={styles.quickText}>Open map</Text>
        </Pressable>
        <Pressable style={styles.quickLink} onPress={onOpenReport}>
          <Ionicons name="create-outline" size={18} color={palette.navActive} />
          <Text style={styles.quickText}>Send field report</Text>
        </Pressable>
      </View>
    </View>
  );
}

function formatDate(value?: string) {
  return formatPhilippineDateTime(value);
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  identityCard: {
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.nav,
    ...shadow,
  },
  identityTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  identityText: {
    flex: 1,
    minWidth: 0,
  },
  statusWrap: {
    maxWidth: 120,
    flexShrink: 0,
    alignItems: 'flex-end',
  },
  kicker: {
    color: palette.navMuted,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  name: {
    marginTop: 4,
    color: '#fff',
    fontSize: 22,
    fontWeight: '900',
  },
  meta: {
    marginTop: 3,
    color: palette.navMuted,
    fontSize: 13,
    fontWeight: '700',
  },
  eventStrip: {
    marginTop: spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: '#ffffff12',
  },
  eventCopy: {
    flex: 1,
  },
  eventTitle: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '900',
  },
  eventMeta: {
    marginTop: 2,
    color: palette.navMuted,
    fontSize: 12,
    fontWeight: '700',
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  assignmentTitle: {
    color: palette.text,
    fontSize: 20,
    fontWeight: '900',
  },
  assignmentMeta: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
  },
  note: {
    borderRadius: radius.md,
    padding: spacing.md,
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 19,
    backgroundColor: palette.secondary,
  },
  buttonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  queueItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  queueIcon: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  queueText: {
    flex: 1,
  },
  queueTitle: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
  queueMeta: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  quickRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  quickLink: {
    flex: 1,
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  quickText: {
    color: palette.nav,
    fontSize: 13,
    fontWeight: '900',
  },
});
