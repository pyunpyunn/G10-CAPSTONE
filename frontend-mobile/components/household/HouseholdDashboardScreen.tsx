import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdSection, statusColor } from './HouseholdUI';

type DashboardProps = {
  overview: any;
  pendingStatus: string;
  editingStatus: boolean;
  showHistory: boolean;
  viewingTrusted: any;
  onSelectStatus: (status: string) => void;
  onSaveStatus: () => void;
  onEditStatus: () => void;
  onToggleHistory: () => void;
  onOpenQr: () => void;
  onAddTrusted: () => void;
  onOpenTrusted: (household: any) => void;
  onSaveMemberStatus?: (memberId: string, status: string) => Promise<void>;
  onBackFamily: () => void;
};

export function HouseholdDashboardScreen({
  overview,
  pendingStatus,
  editingStatus,
  showHistory,
  viewingTrusted,
  onSelectStatus,
  onSaveStatus,
  onEditStatus,
  onToggleHistory,
  onOpenQr,
  onAddTrusted,
  onOpenTrusted,
  onSaveMemberStatus,
  onBackFamily,
}: DashboardProps) {
  const activeEvent = overview.active_event;
  const currentStatus = overview.current_status;
  const members = viewingTrusted ? trustedMembers(viewingTrusted) : overview.members || [];
  const trusted = overview.trusted?.households || [];
  const statusOptions = overview.status_options?.length ? overview.status_options : defaultStatusOptions;
  const trustedStatus = viewingTrusted?.current_status || null;

  return (
    <View style={styles.stack}>
      {activeEvent ? (
        <View style={styles.eventCard}>
          <View style={styles.eventTop}>
            <HouseholdBadge label="Disaster active" tone="danger" />
          <Text style={styles.eventDate}>{formatDate(activeEvent.started_at)}</Text>
          </View>
          <Text style={styles.eventTitle}>{activeEvent.name}</Text>
          <Text style={styles.eventMeta}>{activeEvent.type} · {activeEvent.severity}</Text>
          {activeEvent.message ? <Text style={styles.eventBody}>{activeEvent.message}</Text> : null}
        </View>
      ) : (
        <View style={styles.standbyCard}>
          <HouseholdBadge label="No current disaster" tone="safe" />
        </View>
      )}

      {activeEvent ? (
        <View style={styles.card}>
          <HouseholdSection
            title="Household status"
            action={
              <Pressable style={styles.historyButton} onPress={onToggleHistory}>
                <Ionicons name="time-outline" size={18} color={palette.navActive} />
              </Pressable>
            }
          />
          <View style={styles.lastSaved}>
            <Text style={styles.lastSavedLabel}>Last saved status</Text>
            <Text style={styles.lastSavedValue}>
              {currentStatus ? `${currentStatus.status_label} · ${currentStatus.last_saved_label}` : 'No saved status yet'}
            </Text>
          </View>

          <View style={styles.statusGrid}>
            {statusOptions.map((status: any) => {
              const isSelected = pendingStatus === status.key;
              const locked = !editingStatus && Boolean(currentStatus);

              return (
                <Pressable
                  key={status.key}
                  style={[
                    styles.statusButton,
                    { borderColor: statusColor(status.key) },
                    isSelected && editingStatus && { backgroundColor: statusColor(status.key) },
                    locked && styles.statusLocked,
                  ]}
                  onPress={() => editingStatus && onSelectStatus(status.key)}
                  disabled={!editingStatus}
                >
                  <Ionicons
                    name={statusIcon(status.key)}
                    size={20}
                    color={isSelected && editingStatus ? '#fff' : statusColor(status.key)}
                  />
                  <Text style={[styles.statusText, isSelected && editingStatus && styles.statusTextActive]}>
                    {status.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          <View style={styles.actionRow}>
            {editingStatus || !currentStatus ? (
              <HouseholdButton label="Save update" icon="save-outline" onPress={onSaveStatus} />
            ) : (
              <HouseholdButton label="Edit status" icon="create-outline" tone="light" onPress={onEditStatus} />
            )}
            <HouseholdButton label="Evacuation QR" icon="qr-code-outline" tone="light" onPress={onOpenQr} />
          </View>

          {showHistory ? (
            <View style={styles.historyBox}>
              {(overview.status_history || []).length === 0 ? (
                <Text style={styles.muted}>No status history yet.</Text>
              ) : (
                overview.status_history.map((log: any) => (
                  <View key={log.status_log_id} style={styles.historyRow}>
                    <HouseholdBadge label={log.status_label} tone={log.status_key} />
                    <Text style={styles.historyText}>{log.submitted_label}</Text>
                  </View>
                ))
              )}
            </View>
          ) : null}
        </View>
      ) : null}

      <View style={styles.card}>
        <HouseholdSection
          title={viewingTrusted ? `${viewingTrusted.family_name} household` : 'Family members'}
          action={
            viewingTrusted ? (
              <HouseholdButton label="Back" icon="arrow-back-outline" tone="light" onPress={onBackFamily} />
            ) : (
              <HouseholdButton label="QR" icon="qr-code-outline" tone="light" onPress={onOpenQr} />
            )
          }
        />
        {viewingTrusted && activeEvent ? (
          <View style={styles.trustedStatusBox}>
            <View>
              <Text style={styles.lastSavedLabel}>Trusted household status</Text>
              <Text style={styles.lastSavedValue}>
                {trustedStatus ? `${trustedStatus.status_label} · ${trustedStatus.last_saved_label}` : 'No status saved yet'}
              </Text>
            </View>
            <HouseholdBadge
              label={trustedStatus ? trustedStatus.status_label : 'Unchecked'}
              tone={trustedStatus?.status_key || 'neutral'}
            />
          </View>
        ) : null}
        {members.length === 0 ? (
          <HouseholdEmpty icon="people-outline" title="No members listed" />
        ) : (
          members.map((member: any) => (
            <MemberRow
              key={member.member_id || member.name}
              member={member}
              activeEvent={activeEvent}
              statusOptions={statusOptions}
              canEditStatus={!viewingTrusted}
              onSaveMemberStatus={onSaveMemberStatus}
            />
          ))
        )}
      </View>

      {!viewingTrusted ? (
        <View style={styles.card}>
          <HouseholdSection
            title="Trusted households"
            action={<HouseholdButton label="Add" icon="add-outline" tone="light" onPress={onAddTrusted} />}
          />
          <TrustedHouseholdList
            overview={overview}
            trusted={trusted}
            activeEvent={activeEvent}
            onOpenTrusted={onOpenTrusted}
          />
        </View>
      ) : null}
    </View>
  );
}

export function HouseholdTrustedScreen({
  overview,
  viewingTrusted,
  onOpenQr,
  onAddTrusted,
  onOpenTrusted,
  onBackFamily,
}: Pick<
  DashboardProps,
  'overview' | 'viewingTrusted' | 'onOpenQr' | 'onAddTrusted' | 'onOpenTrusted' | 'onBackFamily'
>) {
  const activeEvent = overview.active_event;
  const members = viewingTrusted ? trustedMembers(viewingTrusted) : [];
  const trustedStatus = viewingTrusted?.current_status || null;

  if (viewingTrusted) {
    return (
      <View style={styles.stack}>
        <View style={styles.card}>
          <HouseholdSection
            title={`${viewingTrusted.family_name} household`}
            action={<HouseholdButton label="Back" icon="arrow-back-outline" tone="light" onPress={onBackFamily} />}
          />
          {activeEvent ? (
            <View style={styles.trustedStatusBox}>
              <View>
                <Text style={styles.lastSavedLabel}>Trusted household status</Text>
                <Text style={styles.lastSavedValue}>
                  {trustedStatus ? `${trustedStatus.status_label} · ${trustedStatus.last_saved_label}` : 'No status saved yet'}
                </Text>
              </View>
              <HouseholdBadge
                label={trustedStatus ? trustedStatus.status_label : 'Unchecked'}
                tone={trustedStatus?.status_key || 'neutral'}
              />
            </View>
          ) : null}
          {members.length === 0 ? (
            <HouseholdEmpty icon="people-outline" title="No members listed" />
          ) : (
            members.map((member: any) => (
              <MemberRow
                key={member.member_id || member.name}
                member={member}
                activeEvent={activeEvent}
                statusOptions={defaultStatusOptions}
                canEditStatus={false}
              />
            ))
          )}
        </View>
      </View>
    );
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <HouseholdSection
          title="Trusted households"
          action={<HouseholdButton label="Add" icon="add-outline" tone="light" onPress={onAddTrusted} />}
        />
        <TrustedHouseholdList
          overview={overview}
          trusted={overview.trusted?.households || []}
          activeEvent={activeEvent}
          onOpenTrusted={onOpenTrusted}
        />
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Evacuation QR" />
        <HouseholdButton label="Open QR" icon="qr-code-outline" onPress={onOpenQr} />
      </View>
    </View>
  );
}

function TrustedHouseholdList({
  overview,
  trusted,
  activeEvent,
  onOpenTrusted,
}: {
  overview: any;
  trusted: any[];
  activeEvent: any;
  onOpenTrusted: (household: any) => void;
}) {
  if (!overview.trusted?.is_available) {
    return (
      <HouseholdEmpty
        icon="lock-closed-outline"
        title="Trusted household storage not ready"
      />
    );
  }

  if (trusted.length === 0) {
    return <HouseholdEmpty icon="home-outline" title="No trusted households yet" />;
  }

  return (
    <>
      {trusted.map((householdItem: any) => (
        <Pressable key={householdItem.connection_id} style={styles.trustedRow} onPress={() => onOpenTrusted(householdItem)}>
          <View style={styles.trustedIcon}>
            <Ionicons
              name={isTrustedValidated(householdItem) ? 'home-outline' : 'lock-closed-outline'}
              size={18}
              color={palette.navActive}
            />
          </View>
          <View style={styles.trustedText}>
            <Text style={styles.rowTitle}>{householdItem.family_name}</Text>
            <Text style={styles.rowMeta}>
              {householdItem.household_id} · {householdItem.reason || 'Trusted household request'}
            </Text>
            {activeEvent && householdItem.current_status ? (
              <Text style={styles.trustedStatusText}>
                {householdItem.current_status.status_label} · {householdItem.current_status.last_saved_label}
              </Text>
            ) : null}
          </View>
          <HouseholdBadge label={labelize(householdItem.validation_status)} tone={householdItem.validation_status} />
        </Pressable>
      ))}
    </>
  );
}

function MemberRow({
  member,
  activeEvent,
  statusOptions,
  canEditStatus = false,
  onSaveMemberStatus,
}: {
  member: any;
  activeEvent?: any;
  statusOptions: any[];
  canEditStatus?: boolean;
  onSaveMemberStatus?: (memberId: string, status: string) => Promise<void>;
}) {
  const device = member.device;
  const currentStatus = member.current_status || null;
  const [selectedStatus, setSelectedStatus] = useState(currentStatus?.status_key || 'safe');
  const [saving, setSaving] = useState(false);
  const batteryText = device?.battery_level === null || device?.battery_level === undefined
    ? 'Battery not sent'
    : `${device.battery_level}% battery`;
  const activityText = device?.is_active ? 'Active' : 'Inactive';
  const locationText = device?.last_location_label || 'No location yet';

  useEffect(() => {
    setSelectedStatus(currentStatus?.status_key || 'safe');
  }, [currentStatus?.status_key]);

  async function handleSave() {
    if (!member.member_id || !onSaveMemberStatus) {
      return;
    }

    setSaving(true);

    try {
      await onSaveMemberStatus(String(member.member_id), selectedStatus);
    } catch {
      // Parent screen already displays the API error.
    } finally {
      setSaving(false);
    }
  }

  return (
    <View style={styles.memberRow}>
      <View style={styles.memberAvatar}>
        <Text style={styles.memberInitial}>{String(member.name || 'H')[0]?.toUpperCase()}</Text>
      </View>
      <View style={styles.memberText}>
        <Text style={styles.rowTitle}>{member.name}</Text>
        <Text style={styles.rowMeta}>{member.relationship || 'Member'}</Text>
        <Text style={styles.deviceText}>
          {device
            ? `${batteryText} · ${activityText} · ${locationText}`
            : 'No device registered'}
        </Text>
        {activeEvent ? (
          <View style={styles.memberStatusBox}>
            <View style={styles.memberStatusTop}>
              <Text style={styles.memberStatusLabel}>Status</Text>
              <HouseholdBadge
                label={currentStatus?.status_label || 'Unchecked'}
                tone={currentStatus?.status_key || 'neutral'}
              />
            </View>
            {currentStatus?.submitted_label ? (
              <Text style={styles.memberStatusTime}>{currentStatus.submitted_label}</Text>
            ) : null}
            {canEditStatus ? (
              <>
                <View style={styles.memberStatusGrid}>
                  {statusOptions.map((status: any) => {
                    const isSelected = selectedStatus === status.key;

                    return (
                      <Pressable
                        key={status.key}
                        style={[
                          styles.memberStatusChoice,
                          { borderColor: statusColor(status.key) },
                          isSelected && { backgroundColor: statusColor(status.key) },
                        ]}
                        onPress={() => setSelectedStatus(status.key)}
                      >
                        <Text style={[styles.memberStatusChoiceText, isSelected && styles.memberStatusChoiceTextActive]}>
                          {status.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
                <HouseholdButton
                  label={saving ? 'Saving...' : 'Save member status'}
                  icon="save-outline"
                  tone="light"
                  disabled={saving}
                  onPress={handleSave}
                />
              </>
            ) : null}
          </View>
        ) : null}
      </View>
    </View>
  );
}

function trustedMembers(household: any) {
  if (household.members?.length) {
    const relationshipMap = new Map(
      (household.member_relationships || []).map((item: any) => [String(item.member_id), item.relationship_to_family])
    );

    return household.members.map((member: any) => ({
      ...member,
      relationship:
        relationshipMap.get(String(member.member_id)) ||
        member.relationship ||
        'Trusted household member',
    }));
  }

  const relationships = household.member_relationships || [];

  if (!relationships.length) {
    return [];
  }

  return relationships.map((item: any, index: number) => ({
    member_id: item.member_id || `trusted-${index}`,
    name: item.name || 'Trusted member',
    relationship: item.relationship_to_family || 'Trusted household member',
    device: null,
  }));
}

const defaultStatusOptions = [
  { key: 'safe', label: 'Safe' },
  { key: 'evacuated', label: 'Evacuated' },
  { key: 'unsafe', label: 'Unsafe' },
  { key: 'needs_help', label: 'Needs help' },
];

function isTrustedValidated(household: any) {
  return ['validated', 'approved'].includes(String(household.validation_status || '').toLowerCase());
}

function statusIcon(statusKey: string): keyof typeof Ionicons.glyphMap {
  if (statusKey === 'safe') return 'shield-checkmark-outline';
  if (statusKey === 'evacuated') return 'walk-outline';
  if (statusKey === 'unsafe') return 'warning-outline';
  return 'medical-outline';
}

function labelize(value?: string) {
  if (!value) return 'Pending';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value?: string) {
  return formatPhilippineDateTime(value, 'Date not recorded');
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  standbyCard: {
    gap: spacing.sm,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.nav,
    ...shadow,
  },
  eventCard: {
    gap: spacing.sm,
    borderLeftWidth: 5,
    borderLeftColor: palette.unsafe,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: '#fff5f5',
  },
  eventTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  eventDate: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  eventTitle: {
    color: palette.text,
    fontSize: 20,
    fontWeight: '900',
  },
  eventMeta: {
    color: palette.unsafe,
    fontSize: 13,
    fontWeight: '900',
  },
  eventBody: {
    color: palette.text,
    fontSize: 13,
    lineHeight: 19,
    fontWeight: '800',
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  historyButton: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
  },
  lastSaved: {
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  trustedStatusBox: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  lastSavedLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  lastSavedValue: {
    marginTop: 4,
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
  statusGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  statusButton: {
    width: '48%',
    minHeight: 76,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  statusLocked: {
    borderColor: palette.border,
    backgroundColor: palette.secondary,
    opacity: 0.9,
  },
  statusText: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  statusTextActive: {
    color: '#fff',
  },
  actionRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  historyBox: {
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  historyText: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  muted: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
  },
  memberRow: {
    flexDirection: 'row',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  memberAvatar: {
    width: 42,
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 21,
    backgroundColor: palette.secondary,
  },
  memberInitial: {
    color: palette.navActive,
    fontSize: 15,
    fontWeight: '900',
  },
  memberText: {
    flex: 1,
  },
  rowTitle: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
  rowMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  deviceText: {
    marginTop: 5,
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '700',
  },
  memberStatusBox: {
    gap: spacing.sm,
    marginTop: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: palette.secondary,
  },
  memberStatusTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  memberStatusLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  memberStatusTime: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '800',
  },
  memberStatusGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
  },
  memberStatusChoice: {
    minWidth: '47%',
    minHeight: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.card,
  },
  memberStatusChoiceText: {
    color: palette.text,
    fontSize: 11,
    fontWeight: '900',
  },
  memberStatusChoiceTextActive: {
    color: '#fff',
  },
  trustedRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  trustedIcon: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  trustedText: {
    flex: 1,
  },
  trustedStatusText: {
    marginTop: 5,
    color: palette.navActive,
    fontSize: 12,
    fontWeight: '900',
  },
});
