import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getAssignedHousehold } from '@/api/rescuer';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, RescuerSelect, SectionHeader, StatusBadge } from './RescuerUI';

type FieldReportProps = {
  reports: any[];
  assignmentId: number;
  householdId: string;
  memberStatusOptions?: any[];
  onSubmitReport: (payload: any) => Promise<void>;
};

const noMemberStatusOptions: any[] = [];

const incidentTypeOptions = [
  { key: 'flood', label: 'Flood' },
  { key: 'fire', label: 'Fire' },
  { key: 'earthquake', label: 'Earthquake' },
  { key: 'landslide', label: 'Landslide' },
  { key: 'typhoon_strong_winds', label: 'Typhoon / Strong Winds' },
  { key: 'other', label: 'Other' },
];

const propertyDamageOptions = [
  { key: 'no_damage', label: 'No Damage' },
  { key: 'minor_damage', label: 'Minor Damage' },
  { key: 'major_damage', label: 'Major Damage' },
  { key: 'completely_destroyed', label: 'Completely Destroyed' },
  { key: 'not_yet_assessed', label: 'Not Yet Assessed' },
];

type HouseholdMemberEntry = {
  id: string;
  name: string;
  condition: string;
  isHouseholdHead: boolean;
};

export function FieldReportScreen({
  reports,
  assignmentId,
  householdId,
  memberStatusOptions = noMemberStatusOptions,
  onSubmitReport,
}: FieldReportProps) {
  const [household, setHousehold] = useState<any>(null);
  const [householdLoading, setHouseholdLoading] = useState(Boolean(householdId));
  const [householdLoadError, setHouseholdLoadError] = useState('');
  const [availableMemberStatuses, setAvailableMemberStatuses] = useState<any[]>(memberStatusOptions);
  const [submittedForAssignment, setSubmittedForAssignment] = useState<number | null>(null);
  const [members, setMembers] = useState<HouseholdMemberEntry[]>([]);
  const [incidentType, setIncidentType] = useState('');
  const [otherIncidentType, setOtherIncidentType] = useState('');
  const [propertyDamage, setPropertyDamage] = useState('');
  const [deathCount, setDeathCount] = useState('');
  const [injuredCount, setInjuredCount] = useState('');
  const [countError, setCountError] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [reportSubmitted, setReportSubmitted] = useState(false);
  const idempotencyKey = useRef(`field-report-${Date.now()}-${Math.random().toString(36).slice(2)}`);
  const householdHeadName = household?.household_head_name
    || members.find((member) => member.isHouseholdHead)?.name
    || household?.household_name
    || '';
  const householdAddress = household?.address || household?.location?.label || household?.purok || '';
  const membersAssessed = members.length > 0 && members.every((member) => Boolean(member.condition));
  const canShowReportDetails = membersAssessed
    && !householdLoading
    && !householdLoadError
    && Boolean(household);
  const alreadySubmitted = reportSubmitted
    || submittedForAssignment === assignmentId
    || reports.some((report) => String(report.assignment_id) === String(assignmentId));

  useEffect(() => {
    let isCurrent = true;
    setReportSubmitted(false);
    setSubmitError('');
    setMembers([]);
    setHousehold(null);
    setIncidentType('');
    setOtherIncidentType('');
    setPropertyDamage('');
    setDeathCount('');
    setInjuredCount('');
    setCountError('');
    setNotes('');
    idempotencyKey.current = `field-report-${assignmentId}-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    async function loadHousehold() {
      if (!householdId || !assignmentId) {
        setHousehold(null);
        setMembers([]);
        setHouseholdLoading(false);
        setHouseholdLoadError('Select an active household assignment before opening a field report.');
        return;
      }

      setHouseholdLoading(true);
      setHouseholdLoadError('');

      try {
        const data = await getAssignedHousehold(assignmentId);

        if (!isCurrent) return;

        setHousehold(data.household);
        setAvailableMemberStatuses(data.member_status_options || memberStatusOptions);
        setMembers((data.members || []).map((member: any) => {
          const rawStatusKey = String(member.member_status?.key || '');
          const statusOption = (data.member_status_options || memberStatusOptions).find((option: any) =>
            option.key === rawStatusKey
            || (rawStatusKey === 'needs-help' && ['needs_help', 'need_help', 'needs_assistance'].includes(option.key))
            || (rawStatusKey === 'safe' && ['safe', 'safe_at_home', 'active', 'returned'].includes(option.key))
            || (rawStatusKey === 'evacuated' && ['evacuated', 'relocated'].includes(option.key))
          );

          return {
          id: String(member.member_id),
          name: member.name || 'Unnamed member',
          condition: statusOption?.key === 'unknown' ? '' : (statusOption?.key || ''),
          isHouseholdHead: Boolean(member.is_household_head),
          };
        }));
      } catch {
        if (isCurrent) {
          setHousehold(null);
          setMembers([]);
          setHouseholdLoadError('Unable to load the assigned household and member names.');
        }
      } finally {
        if (isCurrent) setHouseholdLoading(false);
      }
    }

    loadHousehold();

    return () => {
      isCurrent = false;
    };
  }, [assignmentId, householdId, memberStatusOptions]);

  function updateMemberCondition(id: string, condition: string) {
    setMembers((currentMembers) =>
      currentMembers.map((member) => (member.id === id ? { ...member, condition } : member)),
    );
  }

  async function handleSubmit() {
    if (alreadySubmitted || submitting) {
      return;
    }

    if (!householdId || !assignmentId || !household || householdLoading) {
      Alert.alert('Household unavailable', 'The selected assignment and household must load before submitting.');
      return;
    }

    if (!membersAssessed) {
      Alert.alert('Member assessment required', 'Select a status other than Unchecked for every household member.');
      return;
    }

    if (!incidentType) {
      Alert.alert('Incident type required', 'Select the incident type.');
      return;
    }

    if (incidentType === 'other' && !otherIncidentType.trim()) {
      Alert.alert('Incident type required', 'Specify the incident type.');
      return;
    }

    if (!propertyDamage) {
      Alert.alert('Property damage required', 'Select a property damage level.');
      return;
    }

    if (deathCount === '' || !/^\d+$/.test(deathCount)) {
      Alert.alert('Invalid death count', 'Enter a non-negative whole number. Enter 0 if there are no deaths.');
      return;
    }

    if (injuredCount === '' || !/^\d+$/.test(injuredCount)) {
      Alert.alert('Invalid injured count', 'Enter a non-negative whole number. Enter 0 if nobody is injured.');
      return;
    }

    setSubmitError('');
    setSubmitting(true);

    try {
      await onSubmitReport({
        assignment_id: assignmentId,
        household_id: String(household.household_id || householdId),
        idempotency_key: idempotencyKey.current,
        incident_type: incidentType,
        incident_type_other: incidentType === 'other' ? otherIncidentType.trim() : undefined,
        property_damage: propertyDamage,
        death_count: Number(deathCount),
        injured_count: Number(injuredCount),
        notes: notes.trim() || undefined,
        members: members.map((member) => ({
          member_id: member.id,
          status_key: member.condition,
        })),
      });
      setReportSubmitted(true);
      setSubmittedForAssignment(assignmentId);
      Alert.alert('Field report submitted', 'The report was saved successfully. You can now complete this assignment.');
    } catch (error: any) {
      const validationMessage = Object.values(error?.response?.data?.errors || {})
        .flat()
        .find((message): message is string => typeof message === 'string');
      setSubmitError(
        validationMessage
        || error?.response?.data?.message
        || (!error?.response
          ? 'The server could not be reached. Check your connection and try again; your entries are still here.'
          : 'The report could not be saved. Your entries are still here; review the error and try again.'),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Household Information" />

        {householdLoading ? (
          <View style={styles.loadingRow}>
            <ActivityIndicator color={palette.navActive} />
            <Text style={styles.householdMessage}>Loading assigned household...</Text>
          </View>
        ) : householdLoadError ? (
          <Text style={styles.householdError}>{householdLoadError}</Text>
        ) : (
          <>
            <View style={styles.householdDetails}>
              <View>
                <Text style={styles.fieldLabel}>Household ID</Text>
                <Text style={styles.fieldValue}>{household?.household_id || householdId}</Text>
              </View>
              <View>
                <Text style={styles.fieldLabel}>Household head</Text>
                <Text style={styles.fieldValue}>{householdHeadName || 'Name unavailable'}</Text>
              </View>
              <View>
                <Text style={styles.fieldLabel}>Location</Text>
                <Text style={styles.fieldValue}>{householdAddress || 'Location unavailable'}</Text>
              </View>
            </View>

            <View style={styles.memberSection}>
              <SectionHeader title="Household Members Assessment" />
              {members.length === 0 ? (
                <Text style={styles.householdError}>This household has no active members to assess.</Text>
              ) : null}
              {members.map((member) => (
                <View key={member.id} style={styles.memberRow}>
                  <View style={styles.memberName}>
                    <Text style={styles.fieldValue}>{member.name}</Text>
                    {member.isHouseholdHead && <Text style={styles.fieldLabel}>Household head</Text>}
                  </View>
                  <RescuerSelect
                    title={`Assessment for ${member.name}`}
                    placeholder="Unchecked · select status"
                    options={availableMemberStatuses}
                    value={member.condition}
                    onChange={(value) => updateMemberCondition(member.id, value)}
                    disabled={submitting || alreadySubmitted}
                    style={styles.memberConditionDropdown}
                  />
                </View>
              ))}
            </View>
          </>
        )}

        {canShowReportDetails ? (
          <>
            <SectionHeader title="Incident Details" />
            <View style={styles.fieldGroup}>
              <Text style={styles.fieldLabel}>Incident type *</Text>
              <RescuerSelect
                title="Incident type"
                placeholder="Select incident type"
                options={incidentTypeOptions}
                value={incidentType}
                onChange={setIncidentType}
                disabled={submitting || alreadySubmitted}
              />
            </View>

            {incidentType === 'other' ? (
              <TextInput
                style={styles.input}
                value={otherIncidentType}
                onChangeText={setOtherIncidentType}
                placeholder="Specify the incident type *"
                placeholderTextColor="#7d8da0"
                editable={!submitting && !alreadySubmitted}
                maxLength={255}
              />
            ) : null}

            <View style={styles.fieldGroup}>
              <Text style={styles.fieldLabel}>Property damage *</Text>
              <RescuerSelect
                title="Property damage"
                placeholder="Select property damage"
                options={propertyDamageOptions}
                value={propertyDamage}
                onChange={setPropertyDamage}
                disabled={submitting || alreadySubmitted}
              />
            </View>

            <View style={styles.countRow}>
              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Number of deaths *</Text>
                <TextInput
                  style={styles.input}
                  value={deathCount}
                  onChangeText={(value) => updateNonNegativeCount(value, setDeathCount, setCountError)}
                  placeholder="0"
                  placeholderTextColor="#7d8da0"
                  keyboardType="number-pad"
                  editable={!submitting && !alreadySubmitted}
                />
              </View>
              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Number injured *</Text>
                <TextInput
                  style={styles.input}
                  value={injuredCount}
                  onChangeText={(value) => updateNonNegativeCount(value, setInjuredCount, setCountError)}
                  placeholder="0"
                  placeholderTextColor="#7d8da0"
                  keyboardType="number-pad"
                  editable={!submitting && !alreadySubmitted}
                />
              </View>
            </View>
            {countError ? <Text style={styles.validationError}>{countError}</Text> : null}

            <SectionHeader title="Additional Notes" />
            <TextInput
              style={[styles.input, styles.textArea]}
              value={notes}
              onChangeText={setNotes}
              placeholder="Situation, observations, assistance provided, or other relevant details (optional)"
              placeholderTextColor="#7d8da0"
              multiline
              textAlignVertical="top"
              editable={!submitting && !alreadySubmitted}
              maxLength={2000}
            />

            {alreadySubmitted ? (
              <Text style={styles.successMessage}>A field report has been saved for this assignment.</Text>
            ) : null}
            {submitError ? <Text style={styles.validationError}>{submitError}</Text> : null}
            <ActionButton
              label={submitting ? 'Submitting report...' : alreadySubmitted ? 'Report Submitted' : 'Submit Field Report'}
              icon="send-outline"
              disabled={submitting || alreadySubmitted || householdLoading || !household || !members.length}
              onPress={handleSubmit}
            />
          </>
        ) : !householdLoading && !householdLoadError ? (
          <Text style={styles.householdMessage}>Select a status other than Unchecked for each household member to continue.</Text>
        ) : null}
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

function updateNonNegativeCount(
  value: string,
  setValue: (next: string) => void,
  setError: (next: string) => void,
) {
  if (/^\d*$/.test(value)) {
    setValue(value);
    setError('');
    return;
  }

  setError('Counts must be whole numbers greater than or equal to 0.');
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
    ...shadow,
  },
  householdDetails: {
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: '#fff',
  },
  householdMessage: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '700',
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  householdError: {
    color: palette.unsafe,
    fontSize: 13,
    fontWeight: '700',
  },
  fieldLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '800',
  },
  fieldValue: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
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
  memberSection: {
    gap: spacing.sm,
  },
  memberSectionTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  fieldGroup: {
    flex: 1,
    gap: spacing.xs,
  },
  countRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  validationError: {
    color: palette.unsafe,
    fontSize: 13,
    fontWeight: '700',
  },
  successMessage: {
    color: palette.safe,
    fontSize: 13,
    fontWeight: '800',
  },
  memberName: {
    flex: 1,
    gap: 2,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  memberConditionDropdown: {
    flex: 1.2,
    minHeight: 46,
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
