import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type OperationsProps = {
  checkIns: any[];
  statusOptions: any[];
  onSubmitCheckIn: (payload: any) => Promise<void>;
  onVerifyQr: (payload: any) => Promise<void>;
};

export function RescuerOperationsScreen({
  checkIns,
  statusOptions,
  onSubmitCheckIn,
  onVerifyQr,
}: OperationsProps) {
  const options = statusOptions?.length ? statusOptions : [];
  const [householdId, setHouseholdId] = useState('');
  const [memberName, setMemberName] = useState('');
  const [statusKey, setStatusKey] = useState(options[0]?.key || 'safe');
  const [notes, setNotes] = useState('');
  const [qrPayload, setQrPayload] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleCheckIn() {
    const cleanHouseholdId = householdId.trim();

    if (!cleanHouseholdId) {
      Alert.alert('Missing household ID', 'Enter the household ID before recording a check-in.');
      return;
    }

    setSubmitting(true);

    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      let locationPayload = {};

      if (permission.status === 'granted') {
        const location = await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.High,
        });

        locationPayload = {
          latitude: location.coords.latitude,
          longitude: location.coords.longitude,
        };
      }

      await onSubmitCheckIn({
        household_id: cleanHouseholdId,
        member_id: memberName.trim() || undefined,
        status_key: statusKey,
        notes: notes.trim() || undefined,
        check_in_method: 'field_visit',
        ...locationPayload,
      });

      setHouseholdId('');
      setMemberName('');
      setNotes('');
      Alert.alert('Check-in saved', 'Household rescue check-in was sent to HQ.');
    } catch (error: any) {
      Alert.alert('Unable to save check-in', error?.response?.data?.message || 'Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleVerifyQr() {
    const cleanPayload = qrPayload.trim();

    if (!cleanPayload) {
      Alert.alert('Missing QR payload', 'Paste the scanned evacuation QR payload before verifying.');
      return;
    }

    setSubmitting(true);

    try {
      await onVerifyQr({ qr_payload: cleanPayload });
      setQrPayload('');
      Alert.alert('Evacuation verified', 'Household marked as officially evacuated.');
    } catch (error: any) {
      Alert.alert('QR verification failed', error?.response?.data?.message || 'Check the QR payload and try again.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Evacuation QR" />
        <TextInput
          style={[styles.input, styles.textArea]}
          value={qrPayload}
          onChangeText={setQrPayload}
          placeholder='Paste QR JSON payload'
          placeholderTextColor="#7d8da0"
          multiline
        />
        <ActionButton
          label={submitting ? 'Verifying...' : 'Verify evacuation QR'}
          icon="qr-code-outline"
          onPress={handleVerifyQr}
          disabled={submitting}
        />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Household rescue check-in" />

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
        />
        <TextInput
          style={styles.input}
          value={memberName}
          onChangeText={setMemberName}
          placeholder="Family member name (optional)"
          placeholderTextColor="#7d8da0"
        />
        <TextInput
          style={[styles.input, styles.textArea]}
          value={notes}
          onChangeText={setNotes}
          placeholder="Check-in notes"
          placeholderTextColor="#7d8da0"
          multiline
        />
        <ActionButton
          label={submitting ? 'Saving...' : 'Save check-in'}
          icon="checkmark-circle-outline"
          onPress={handleCheckIn}
          disabled={submitting}
        />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Recent check-ins" />
        {checkIns.length === 0 ? (
          <EmptyState icon="people-outline" title="No check-ins yet" />
        ) : (
          checkIns.map((item) => (
            <View key={item.check_in_id || `${item.household_id}-${item.checked_in_at}`} style={styles.row}>
              <View style={styles.rowIcon}>
                <Ionicons name="home-outline" size={18} color={palette.navActive} />
              </View>
              <View style={styles.rowText}>
                <Text style={styles.rowTitle}>{item.household_id}</Text>
                <Text style={styles.rowMeta}>
                  {item.member_id ? `${item.member_id} · ` : ''}
                  {item.checked_in_at ? formatPhilippineDateTime(item.checked_in_at) : 'Recent'}
                </Text>
              </View>
              <StatusBadge label={item.status_label || item.status_key || 'Checked in'} tone={item.status_key || 'neutral'} />
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
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  input: {
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    color: palette.text,
    backgroundColor: palette.page,
  },
  textArea: {
    minHeight: 88,
    textAlignVertical: 'top',
  },
  statusRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  statusChip: {
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    backgroundColor: palette.page,
  },
  statusChipActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.secondary,
  },
  statusChipText: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  statusChipTextActive: {
    color: palette.navActive,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
  },
  rowIcon: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 17,
    backgroundColor: palette.secondary,
  },
  rowText: {
    flex: 1,
    gap: 2,
  },
  rowTitle: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
  rowMeta: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
});
