import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type FieldReportProps = {
  reports: any[];
  statusOptions: any[];
  onSubmitReport: (payload: any) => Promise<void>;
};

const defaultStatuses = [
  { key: 'safe', label: 'Safe' },
  { key: 'evacuated', label: 'Evacuated' },
  { key: 'unsafe', label: 'Unsafe' },
];

export function FieldReportScreen({ reports, statusOptions, onSubmitReport }: FieldReportProps) {
  const options = statusOptions?.length ? statusOptions : defaultStatuses;
  const [householdId, setHouseholdId] = useState('');
  const [householdHead, setHouseholdHead] = useState('');
  const [address, setAddress] = useState('');
  const [statusKey, setStatusKey] = useState(options[0]?.key || 'safe');
  const [batteryLevel, setBatteryLevel] = useState('');
  const [memberName, setMemberName] = useState('');
  const [memberCondition, setMemberCondition] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    if (!householdId.trim()) {
      Alert.alert('Missing household ID', 'Enter the household ID before submitting.');
      return;
    }

    setSubmitting(true);

    try {
      await onSubmitReport({
        household_id: householdId.trim(),
        household_head: householdHead.trim(),
        address: address.trim(),
        status_key: statusKey,
        battery_level: batteryLevel ? Number(batteryLevel) : undefined,
        notes: notes.trim(),
        members:
          memberName.trim() || memberCondition.trim()
            ? [{ name: memberName.trim(), condition: memberCondition.trim() }]
            : [],
      });

      setHouseholdId('');
      setHouseholdHead('');
      setAddress('');
      setBatteryLevel('');
      setMemberName('');
      setMemberCondition('');
      setNotes('');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Household field report" />

        <View style={styles.statusRow}>
          {options.map((status) => (
            <Pressable
              key={status.key}
              style={[styles.statusChip, statusKey === status.key && styles.statusChipActive]}
              onPress={() => setStatusKey(status.key)}
            >
              <Text style={[styles.statusChipText, statusKey === status.key && styles.statusChipTextActive]}>
                {status.label}
              </Text>
            </Pressable>
          ))}
        </View>

        <TextInput
          style={styles.input}
          value={householdId}
          onChangeText={setHouseholdId}
          placeholder="Household ID"
          placeholderTextColor="#7d8da0"
          autoCapitalize="characters"
        />

        <TextInput
          style={styles.input}
          value={householdHead}
          onChangeText={setHouseholdHead}
          placeholder="Household head"
          placeholderTextColor="#7d8da0"
        />

        <TextInput
          style={styles.input}
          value={address}
          onChangeText={setAddress}
          placeholder="Location / purok / landmark"
          placeholderTextColor="#7d8da0"
        />

        <View style={styles.twoColumn}>
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={memberName}
            onChangeText={setMemberName}
            placeholder="Member name"
            placeholderTextColor="#7d8da0"
          />
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={batteryLevel}
            onChangeText={setBatteryLevel}
            placeholder="Battery %"
            placeholderTextColor="#7d8da0"
            keyboardType="number-pad"
          />
        </View>

        <TextInput
          style={styles.input}
          value={memberCondition}
          onChangeText={setMemberCondition}
          placeholder="Member condition"
          placeholderTextColor="#7d8da0"
        />

        <TextInput
          style={[styles.input, styles.textArea]}
          value={notes}
          onChangeText={setNotes}
          placeholder="Notes for HQ"
          placeholderTextColor="#7d8da0"
          multiline
          textAlignVertical="top"
        />

        <ActionButton
          label={submitting ? 'Submitting...' : 'Submit to HQ'}
          icon="send-outline"
          disabled={submitting}
          onPress={handleSubmit}
        />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Submitted reports" />
        {reports.length === 0 ? (
          <EmptyState icon="document-text-outline" title="No field reports yet" />
        ) : (
          reports.map((report) => (
            <View key={report.status_log_id} style={styles.reportRow}>
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
