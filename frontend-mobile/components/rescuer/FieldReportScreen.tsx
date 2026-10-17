import { statusColor } from '@/constants/statusPalette';
import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getAssignedHousehold } from '@/api/rescuer';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type FieldReportProps = {
  reports: any[];
  assignmentId: number;
  householdId: string;
  onSubmitReport: (payload: any) => Promise<void>;
};

const incidentOptions = [
  { key: 'flood', label: 'Flood' },
  { key: 'fire', label: 'Fire' },
  { key: 'earthquake', label: 'Earthquake' },
  { key: 'landslide', label: 'Landslide' },
  { key: 'typhoon_strong_winds', label: 'Typhoon / strong winds' },
  { key: 'other', label: 'Other' },
];

const damageOptions = [
  { key: 'no_damage', label: 'None' },
  { key: 'minor_damage', label: 'Minor' },
  { key: 'major_damage', label: 'Major' },
  { key: 'completely_destroyed', label: 'Destroyed' },
  { key: 'not_yet_assessed', label: 'Not assessed' },
];

const uncheckedStatuses = new Set(['unknown', 'unchecked', 'unreported']);

export function FieldReportScreen({ reports, assignmentId, householdId, onSubmitReport }: FieldReportProps) {
  const [household, setHousehold] = useState<any>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [memberStatusOptions, setMemberStatusOptions] = useState<any[]>([]);
  const [loadingHousehold, setLoadingHousehold] = useState(Boolean(assignmentId));
  const [householdError, setHouseholdError] = useState('');
  const [incidentType, setIncidentType] = useState('');
  const [otherIncidentType, setOtherIncidentType] = useState('');
  const [propertyDamage, setPropertyDamage] = useState('');
  const [deathCount, setDeathCount] = useState('');
  const [injuredCount, setInjuredCount] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [reportSubmitted, setReportSubmitted] = useState(false);
  const idempotencyKey = useRef('');
  const alreadySubmitted = reportSubmitted || reports.some((report) => String(report.assignment_id) === String(assignmentId));
  const membersAssessed = members.length > 0 && members.every((member) => Boolean(member.statusKey) && !uncheckedStatuses.has(member.statusKey));

  useEffect(() => {
    let isCurrent = true;
    setHousehold(null);
    setMembers([]);
    setMemberStatusOptions([]);
    setHouseholdError('');
    setSubmitError('');
    setReportSubmitted(false);
    setIncidentType('');
    setOtherIncidentType('');
    setPropertyDamage('');
    setDeathCount('');
    setInjuredCount('');
    setNotes('');
    idempotencyKey.current = `field-report-${assignmentId}`;

    if (!assignmentId || !householdId) {
      setHouseholdError('Select an active household assignment before opening a field report.');
      setLoadingHousehold(false);
      return () => { isCurrent = false; };
    }

    setLoadingHousehold(true);
    getAssignedHousehold(assignmentId)
      .then((data) => {
        if (!isCurrent) return;
        const statusOptions = data.member_status_options || [];
        setHousehold(data.household);
        setMemberStatusOptions(statusOptions);
        setMembers((data.members || []).map((member: any) => {
          const savedKey = member.member_status?.key || '';
          const savedOption = statusOptions.find((option: any) => option.key === savedKey);
          return {
            id: String(member.member_id),
            name: member.name || 'Unnamed member',
            isHouseholdHead: member.is_household_head,
            statusKey: savedOption && !uncheckedStatuses.has(savedKey) ? savedKey : '',
          };
        }));
      })
      .catch(() => {
        if (isCurrent) setHouseholdError('Unable to load the assigned household and members. Refresh the assignment and try again.');
      })
      .finally(() => {
        if (isCurrent) setLoadingHousehold(false);
      });

    return () => { isCurrent = false; };
  }, [assignmentId, householdId]);

  function updateMemberStatus(memberId: string, statusKey: string) {
    setMembers((current) => current.map((member) => member.id === memberId ? { ...member, statusKey } : member));
  }

  async function handleSubmit() {
    if (alreadySubmitted || submitting) return;
    if (!membersAssessed || !incidentType || !propertyDamage) {
      Alert.alert('Report details required', 'Assess every member, then select an incident type and property damage level.');
      return;
    }
    const counts = [deathCount, injuredCount];
    if (counts.some((value) => value.trim() === '' || !/^\d+$/.test(value.trim()))) {
      Alert.alert('Counts required', 'Enter a non-negative whole number for deaths and injuries.');
      return;
    }
    if (incidentType === 'other' && !otherIncidentType.trim()) {
      Alert.alert('Incident type required', 'Describe the incident type.');
      return;
    }

    setSubmitError('');
    setSubmitting(true);

    try {
      await onSubmitReport({
        assignment_id: assignmentId,
        household_id: String(household.household_id),
        idempotency_key: idempotencyKey.current,
        incident_type: incidentType,
        incident_type_other: incidentType === 'other' ? otherIncidentType.trim() : undefined,
        property_damage: propertyDamage,
        death_count: Number(deathCount),
        injured_count: Number(injuredCount),
        notes: notes.trim() || undefined,
        members: members.map((member) => ({ member_id: member.id, status_key: member.statusKey })),
      });
      setReportSubmitted(true);
    } catch (error: any) {
      setSubmitError(error?.response?.data?.message || 'The field report could not be saved. Check your connection and retry.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Household field report" />
        {loadingHousehold ? <Text style={styles.helperText}>Loading assigned household…</Text> : null}
        {householdError ? <Text style={styles.errorText}>{householdError}</Text> : null}
        {household ? (
          <View style={styles.householdSummary}>
            <Text style={styles.householdName}>{household.household_name || household.household_code || household.household_id}</Text>
            <Text style={styles.helperText}>{household.household_id} · {household.address || household.purok || 'Address unavailable'}</Text>
          </View>
        ) : null}

        {members.map((member) => (
          <View key={member.id} style={styles.memberCard}>
            <Text style={styles.memberName}>{member.name}{member.isHouseholdHead ? ' · Head' : ''}</Text>
            <View style={styles.statusRow}>
              {memberStatusOptions.filter((option) => !uncheckedStatuses.has(option.key)).map((option) => (
                <Pressable
                  key={option.key}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: member.statusKey === option.key }}
                  style={[styles.statusChip, { borderColor: statusColor(option.key) }, member.statusKey === option.key && { backgroundColor: statusColor(option.key) }]}
                  onPress={() => updateMemberStatus(member.id, option.key)}
                >
                  <Text style={[styles.statusChipText, { color: statusColor(option.key) }, member.statusKey === option.key && styles.statusChipTextActive]}>
                    {option.label}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>
        ))}

        <Text style={styles.sectionLabel}>Incident type</Text>
        <View style={styles.statusRow}>
          {incidentOptions.map((option) => (
            <Pressable key={option.key} style={[styles.statusChip, incidentType === option.key && styles.selectedOption]} onPress={() => setIncidentType(option.key)}>
              <Text style={[styles.statusChipText, incidentType === option.key && styles.statusChipTextActive]}>{option.label}</Text>
            </Pressable>
          ))}
        </View>
        {incidentType === 'other' ? (
          <TextInput style={styles.input} value={otherIncidentType} onChangeText={setOtherIncidentType} placeholder="Describe incident type" placeholderTextColor="#7d8da0" maxLength={255} />
        ) : null}

        <Text style={styles.sectionLabel}>Property damage</Text>
        <View style={styles.statusRow}>
          {damageOptions.map((option) => (
            <Pressable key={option.key} style={[styles.statusChip, propertyDamage === option.key && styles.selectedOption]} onPress={() => setPropertyDamage(option.key)}>
              <Text style={[styles.statusChipText, propertyDamage === option.key && styles.statusChipTextActive]}>{option.label}</Text>
            </Pressable>
          ))}
        </View>

        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={deathCount} onChangeText={setDeathCount} placeholder="Deaths" placeholderTextColor="#7d8da0" keyboardType="number-pad" />
          <TextInput style={[styles.input, styles.flexInput]} value={injuredCount} onChangeText={setInjuredCount} placeholder="Injured" placeholderTextColor="#7d8da0" keyboardType="number-pad" />
        </View>
        <TextInput style={[styles.input, styles.textArea]} value={notes} onChangeText={setNotes} placeholder="Notes for HQ (optional)" placeholderTextColor="#7d8da0" multiline textAlignVertical="top" maxLength={2000} />
        {submitError ? <Text style={styles.errorText}>{submitError}</Text> : null}
        {alreadySubmitted ? <Text style={styles.successText}>A field report is saved for this assignment.</Text> : null}
        <ActionButton
          label={alreadySubmitted ? 'Report saved' : submitting ? 'Submitting…' : 'Submit to HQ'}
          icon={alreadySubmitted ? 'checkmark-circle-outline' : 'send-outline'}
          disabled={submitting || loadingHousehold || !household || alreadySubmitted || members.length === 0}
          onPress={handleSubmit}
        />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Submitted reports" />
        {reports.length === 0 ? (
          <EmptyState icon="document-text-outline" title="No field reports yet" />
        ) : (
          reports.map((report) => (
            <View key={report.report_id || report.status_log_id} style={styles.reportRow}>
              <View style={styles.reportIcon}>
                <Ionicons name="document-text-outline" size={18} color={palette.navActive} />
              </View>
              <View style={styles.reportText}>
                <Text style={styles.reportTitle}>
                  {report.household_head || report.household_code || report.household_id}
                </Text>
                <Text style={styles.reportMeta}>
                  {report.household_id} · {formatDate(report.submitted_at)}
                </Text>
              </View>
              <StatusBadge label={report.status_label} tone={report.status_key} />
            </View>
          ))
        )}
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
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  statusRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  statusChip: {
    minHeight: 38,
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.card,
  },
  statusChipActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  statusChipText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '900',
  },
  statusChipTextActive: {
    color: '#fff',
  },
  selectedOption: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  sectionLabel: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  helperText: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  errorText: {
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '800',
  },
  successText: {
    color: palette.safe,
    fontSize: 12,
    fontWeight: '800',
  },
  householdSummary: {
    gap: 3,
    borderLeftWidth: 3,
    borderLeftColor: palette.navActive,
    paddingLeft: spacing.md,
  },
  householdName: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
  },
  memberCard: {
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  memberName: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  input: {
    minHeight: 46,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
    backgroundColor: '#fff',
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  flexInput: {
    flex: 1,
  },
  textArea: {
    minHeight: 96,
    paddingTop: spacing.md,
  },
  reportRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  reportIcon: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  reportText: {
    flex: 1,
  },
  reportTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  reportMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
});
