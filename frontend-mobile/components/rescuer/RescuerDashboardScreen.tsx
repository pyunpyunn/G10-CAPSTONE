import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useState } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatTile, StatusBadge } from './RescuerUI';

type DashboardProps = {
  overview: any;
  onOpenMap: (assignment?: any) => void;
  onOpenReport: (assignment?: any) => void;
  onStatusChange: (assignmentId: number, status: string) => void;
};

export function RescuerDashboardScreen({
  overview,
  onOpenMap,
  onOpenReport,
  onStatusChange,
}: DashboardProps) {
  const [isQuickLinkHovered, setIsQuickLinkHovered] = useState(false);
  const [isQuickLinkPressed, setIsQuickLinkPressed] = useState(false);
  const [selectedAssignmentId, setSelectedAssignmentId] = useState<number | null>(null);
  const profile = overview?.profile || {};
  const responder = profile.responder || {};
  const activeEvent = overview?.active_event;
  const summary = overview?.summary || {};
  const assignments = overview?.assignments || [];
  const activeAssignment = assignments.find((item: any) =>
    ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(item.status_key)
  );
  const activeEventId = overview?.active_event?.event_id;
  const hasSubmittedFieldReport = !activeAssignment?.household_id || (overview?.field_reports || []).some((report: any) =>
    String(report.assignment_id) === String(activeAssignment.assignment_id)
    && String(report.household_id) === String(activeAssignment.household_id)
    && (!activeEventId || String(report.event_id) === String(activeEventId))
  );

  return (
    <View style={styles.stack}>
      <View style={styles.identityCard}>
        <View style={styles.identityTop}>
          <View style={styles.identityText}>
            <Text style={styles.kicker}>Emergency event</Text>
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
          <View style={[styles.eventStrip, styles.activeEventStrip]}>
            <Ionicons name="warning" size={24} color={palette.warning} />
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
          {activeAssignment.destination_label ? (
            <Text style={styles.destinationText}>{activeAssignment.destination_label}</Text>
          ) : null}
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
            {activeAssignment.household_id ? (
              <>
                <ActionButton
                  label="Map"
                  icon="map-outline"
                  tone="light"
                  onPress={() => onOpenMap(activeAssignment)}
                />
                <ActionButton
                  label="Field Report"
                  icon="document-text-outline"
                  tone="light"
                  onPress={() => onOpenReport(activeAssignment)}
                />
              </>
            ) : null}
            <ActionButton
              label="Complete"
              icon="flag-outline"
              disabled={!['en_route', 'on_scene'].includes(activeAssignment.status_key) || !hasSubmittedFieldReport}
              onPress={() => onStatusChange(activeAssignment.assignment_id, 'completed')}
            />
          </View>
          {activeAssignment.household_id && !hasSubmittedFieldReport ? (
            <Pressable style={styles.reportRequiredButton} onPress={() => onOpenReport(activeAssignment)}>
              <Ionicons name="document-text-outline" size={16} color={palette.navActive} />
              <Text style={styles.reportRequiredText}>Submit field report to enable completion</Text>
            </Pressable>
          ) : null}
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
            <View key={assignment.assignment_id} style={styles.queueEntry}>
              <Pressable
                style={styles.queueItem}
                onPress={() => setSelectedAssignmentId((current) =>
                  current === assignment.assignment_id ? null : assignment.assignment_id
                )}
              >
                <View style={styles.queueIcon}>
                  <Ionicons name="trail-sign-outline" size={18} color={palette.navActive} />
                </View>
                <View style={styles.queueText}>
                  <Text style={styles.queueTitle}>{assignment.assigned_area || assignment.household_id || 'Assigned location'}</Text>
                  <Text style={styles.queueMeta}>
                    {assignment.destination_label || assignment.assignment_code || `Assignment ${assignment.assignment_id}`} · {formatDate(assignment.assigned_at)}
                  </Text>
                  <Text style={styles.queueMeta}>Household {assignment.household_id || 'not linked'}</Text>
                </View>
                <StatusBadge label={assignment.status_label} tone={assignment.status_key} />
              </Pressable>
              {selectedAssignmentId === assignment.assignment_id
                && assignment.household_id
                && ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key) ? (
                <View style={styles.assignmentActions}>
                  <ActionButton
                    label="Map"
                    icon="map-outline"
                    tone="light"
                    onPress={() => onOpenMap(assignment)}
                  />
                  <ActionButton
                    label="Field Report"
                    icon="document-text-outline"
                    onPress={() => onOpenReport(assignment)}
                  />
                </View>
              ) : null}
              {selectedAssignmentId === assignment.assignment_id
                && !['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key) ? (
                <Text style={styles.queueMeta}>This assignment is no longer active.</Text>
              ) : null}
              {selectedAssignmentId === assignment.assignment_id
                && !assignment.household_id
                && ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key) ? (
                <Text style={styles.queueMeta}>This assignment has no linked household.</Text>
              ) : null}
            </View>
          ))
        )}
      </View>

      <View style={styles.quickRow}>
        <Pressable
          style={[styles.quickLink, (isQuickLinkHovered || isQuickLinkPressed) && styles.quickLinkActive]}
          onPress={() => onOpenMap(activeAssignment)}
          onHoverIn={() => setIsQuickLinkHovered(true)}
          onHoverOut={() => setIsQuickLinkHovered(false)}
          onPressIn={() => setIsQuickLinkPressed(true)}
          onPressOut={() => setIsQuickLinkPressed(false)}
        >
          <Ionicons name="map-outline" size={18} color={palette.navActive} />
          <Text style={styles.quickText}>Open map</Text>
        </Pressable>
        {activeAssignment?.household_id ? (
          <Pressable
            style={[styles.quickLink, (isQuickLinkHovered || isQuickLinkPressed) && styles.quickLinkActive]}
            onPress={() => onOpenReport(activeAssignment)}
            onHoverIn={() => setIsQuickLinkHovered(true)}
            onHoverOut={() => setIsQuickLinkHovered(false)}
            onPressIn={() => setIsQuickLinkPressed(true)}
            onPressOut={() => setIsQuickLinkPressed(false)}
          >
            <Ionicons name="create-outline" size={18} color={palette.navActive} />
            <Text style={styles.quickText}>Send field report</Text>
          </Pressable>
        ) : null}
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
  meta: {
    marginTop: 6,
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
  activeEventStrip: {
    borderWidth: 1,
    borderColor: `${palette.warning}88`,
    backgroundColor: `${palette.warning}20`,
  },
  eventCopy: {
    flex: 1,
  },
  eventTitle: {
    color: '#fff',
    fontSize: 18,
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
  destinationText: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '800',
    lineHeight: 18,
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
  reportRequiredButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    paddingVertical: spacing.xs,
  },
  reportRequiredText: {
    color: palette.navActive,
    fontSize: 12,
    fontWeight: '800',
  },
  queueItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  queueEntry: {
    gap: spacing.sm,
  },
  assignmentActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingLeft: 46,
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
  quickLinkActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.secondary,
    shadowColor: palette.navActive,
    shadowOpacity: 0.15,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
  },
  quickText: {
    color: palette.nav,
    fontSize: 13,
    fontWeight: '900',
  },
});
