import { useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
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
  { key: 'unsafe', label: 'Critical' },
];

const incidentTypeOptions = [
  { key: 'fire', label: 'Fire' },
  { key: 'flood', label: 'Flood' },
  { key: 'landslide', label: 'Landslide' },
  { key: 'storm', label: 'Storm' },
  { key: 'earthquake', label: 'Earthquake' },
  { key: 'medical', label: 'Medical' },
  { key: 'other', label: 'Other' },
];

const propertyDamageOptions = [
  { key: 'minor', label: 'minor' },
  { key: 'major', label: 'major damage' },
  { key: 'destroyed', label: 'completely destroyed' },
];

type HouseholdMemberEntry = {
  id: string;
  name: string;
  condition: string;
};

export function FieldReportScreen({ reports, statusOptions, onSubmitReport }: FieldReportProps) {
  const options = (statusOptions?.length ? statusOptions : defaultStatuses)
    .filter((status) => !['active', 'relocated', 'displaced', 'returned'].includes(String(status?.key ?? '').toLowerCase()));
  const [householdId, setHouseholdId] = useState('');
  const [householdHead, setHouseholdHead] = useState('');
  const [address, setAddress] = useState('');
  const [statusKey, setStatusKey] = useState(options[0]?.key || 'safe');
  const [members, setMembers] = useState<HouseholdMemberEntry[]>([{ id: 'member-1', name: '', condition: '' }]);
  const [incidentType, setIncidentType] = useState('');
  const [injuredCount, setInjuredCount] = useState('');
  const [deathCount, setDeathCount] = useState('');
  const [propertyDamage, setPropertyDamage] = useState('');
  const [isIncidentMenuOpen, setIsIncidentMenuOpen] = useState(false);
  const [isDamageMenuOpen, setIsDamageMenuOpen] = useState(false);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  function updateMember(id: string, field: 'name' | 'condition', value: string) {
    setMembers((currentMembers) =>
      currentMembers.map((member) => (member.id === id ? { ...member, [field]: value } : member)),
    );
  }

  function addMember() {
    setMembers((currentMembers) => [
      ...currentMembers,
      {
        id: `member-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        name: '',
        condition: '',
      },
    ]);
  }

  function removeMember(id: string) {
    setMembers((currentMembers) => {
      if (currentMembers.length === 1) {
        return [{ id, name: '', condition: '' }];
      }

      return currentMembers.filter((member) => member.id !== id);
    });
  }

  async function handleSubmit() {
    const cleanHouseholdId = householdId.trim();
    const cleanHouseholdHead = householdHead.trim();
    const cleanAddress = address.trim();
    const cleanIncidentType = incidentType.trim();
    const cleanInjuredCount = injuredCount.trim();
    const cleanDeathCount = deathCount.trim();
    const cleanPropertyDamage = propertyDamage.trim();
    const cleanNotes = notes.trim();
    const cleanPropertyDamageValue = propertyDamage.trim();
    const injuredValue = cleanInjuredCount ? Number(cleanInjuredCount) : undefined;
    const deathValue = cleanDeathCount ? Number(cleanDeathCount) : undefined;
    const normalizedMembers = members
      .map((member) => ({
        name: member.name.trim(),
        condition: member.condition.trim(),
      }))
      .filter((member) => member.name || member.condition);

    if (!cleanHouseholdId) {
      Alert.alert('Missing household ID', 'Enter the household ID before submitting.');
      return;
    }

    if (!cleanHouseholdHead) {
      Alert.alert('Missing household head', 'Enter the household head name before submitting.');
      return;
    }

    if (!cleanAddress) {
      Alert.alert('Missing location', 'Enter the purok, address, or landmark before submitting.');
      return;
    }

    if (!cleanNotes || cleanNotes.length < 5) {
      Alert.alert('Missing field notes', 'Enter a short note that explains the household situation.');
      return;
    }

    if (normalizedMembers.some((member) => (member.name && !member.condition) || (!member.name && member.condition))) {
      Alert.alert('Incomplete member condition', 'Enter both member name and condition, or leave both blank for that person.');
      return;
    }

    if (injuredValue !== undefined && (Number.isNaN(injuredValue) || injuredValue < 0 || injuredValue > 999)) {
      Alert.alert('Invalid injuries count', 'Injuries must be between 0 and 999.');
      return;
    }

    if (deathValue !== undefined && (Number.isNaN(deathValue) || deathValue < 0 || deathValue > 999)) {
      Alert.alert('Invalid deaths count', 'Deaths must be between 0 and 999.');
      return;
    }

    setSubmitting(true);

    try {
      await onSubmitReport({
        household_id: cleanHouseholdId,
        household_head: cleanHouseholdHead,
        address: cleanAddress,
        status_key: statusKey,
        notes: cleanNotes,
        incident_type: cleanIncidentType || undefined,
        injured_count: injuredValue,
        death_count: deathValue,
        property_damage: cleanPropertyDamageValue || undefined,
        members: normalizedMembers,
      });

      setHouseholdId('');
      setHouseholdHead('');
      setAddress('');
      setMembers([{ id: 'member-1', name: '', condition: '' }]);
      setIncidentType('');
      setInjuredCount('');
      setDeathCount('');
      setPropertyDamage('');
      setNotes('');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Household field report" />

        <Pressable style={styles.dropdownButton} onPress={() => setIsIncidentMenuOpen(true)}>
          <View style={styles.dropdownValueRow}>
            <Text style={styles.dropdownText}>
              {incidentType ? incidentTypeOptions.find((option) => option.key === incidentType)?.label : 'Incident type'}
            </Text>
            <Ionicons name="chevron-down" size={18} color={palette.textSoft} />
          </View>
        </Pressable>

        {isIncidentMenuOpen && (
          <Modal transparent visible={isIncidentMenuOpen} animationType="fade" onRequestClose={() => setIsIncidentMenuOpen(false)}>
            <Pressable style={styles.modalOverlay} onPress={() => setIsIncidentMenuOpen(false)}>
              <Pressable style={styles.modalContent} onPress={() => undefined}>
                <ScrollView style={styles.modalList}>
                  {incidentTypeOptions.map((option) => (
                    <Pressable
                      key={option.key}
                      style={[styles.modalOption, incidentType === option.key && styles.modalOptionActive]}
                      onPress={() => {
                        setIncidentType(option.key);
                        setIsIncidentMenuOpen(false);
                      }}
                    >
                      <Text style={[styles.modalOptionText, incidentType === option.key && styles.modalOptionTextActive]}>
                        {option.label}
                      </Text>
                    </Pressable>
                  ))}
                </ScrollView>
              </Pressable>
            </Pressable>
          </Modal>
        )}

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
          placeholder="Location"
          placeholderTextColor="#7d8da0"
        />

        <View style={styles.memberSection}>
          <Text style={styles.memberSectionTitle}>Household members</Text>
          {members.map((member, index) => (
            <View key={member.id} style={styles.memberRow}>
              <TextInput
                style={[styles.input, styles.memberNameInput]}
                value={member.name}
                onChangeText={(text) => updateMember(member.id, 'name', text)}
                placeholder={`Member ${index + 1} name`}
                placeholderTextColor="#7d8da0"
              />
              <TextInput
                style={[styles.input, styles.memberConditionInput]}
                value={member.condition}
                onChangeText={(text) => updateMember(member.id, 'condition', text)}
                placeholder={`Member ${index + 1} condition`}
                placeholderTextColor="#7d8da0"
              />
              {members.length > 1 && (
                <Pressable style={styles.removeMemberButton} onPress={() => removeMember(member.id)}>
                  <Ionicons name="close-circle" size={20} color={palette.textSoft} />
                </Pressable>
              )}
            </View>
          ))}

          <Pressable style={styles.addMemberButton} onPress={addMember}>
            <Ionicons name="add-circle-outline" size={18} color={palette.navActive} />
            <Text style={styles.addMemberText}>Add household member</Text>
          </Pressable>
        </View>

        <View style={styles.twoColumn}>
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={injuredCount}
            onChangeText={setInjuredCount}
            placeholder="Injuries"
            placeholderTextColor="#7d8da0"
            keyboardType="number-pad"
          />
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={deathCount}
            onChangeText={setDeathCount}
            placeholder="Deaths"
            placeholderTextColor="#7d8da0"
            keyboardType="number-pad"
          />
        </View>

        <Pressable style={styles.dropdownButton} onPress={() => setIsDamageMenuOpen(true)}>
          <View style={styles.dropdownValueRow}>
            <Text style={styles.dropdownText}>
              {propertyDamage ? propertyDamageOptions.find((option) => option.key === propertyDamage)?.label : 'Property damage'}
            </Text>
            <Ionicons name="chevron-down" size={18} color={palette.textSoft} />
          </View>
        </Pressable>

        {isDamageMenuOpen && (
          <Modal transparent visible={isDamageMenuOpen} animationType="fade" onRequestClose={() => setIsDamageMenuOpen(false)}>
            <Pressable style={styles.modalOverlay} onPress={() => setIsDamageMenuOpen(false)}>
              <Pressable style={styles.modalContent} onPress={() => undefined}>
                <ScrollView style={styles.modalList}>
                  {propertyDamageOptions.map((option) => (
                    <Pressable
                      key={option.key}
                      style={[styles.modalOption, propertyDamage === option.key && styles.modalOptionActive]}
                      onPress={() => {
                        setPropertyDamage(option.key);
                        setIsDamageMenuOpen(false);
                      }}
                    >
                      <Text style={[styles.modalOptionText, propertyDamage === option.key && styles.modalOptionTextActive]}>
                        {option.label}
                      </Text>
                    </Pressable>
                  ))}
                </ScrollView>
              </Pressable>
            </Pressable>
          </Modal>
        )}

        <TextInput
          style={[styles.input, styles.textArea]}
          value={notes}
          onChangeText={setNotes}
          placeholder="Additional operational notes"
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
  dropdownButton: {
    minHeight: 54,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#fff',
    justifyContent: 'center',
  },
  dropdownValueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  dropdownText: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
    padding: spacing.lg,
  },
  modalContent: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
  },
  modalTitle: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
    marginBottom: spacing.sm,
  },
  modalList: {
    maxHeight: 240,
  },
  modalOption: {
    minHeight: 46,
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
  },
  modalOptionActive: {
    backgroundColor: palette.navActive,
  },
  modalOptionText: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  modalOptionTextActive: {
    color: '#fff',
  },
  memberSection: {
    gap: spacing.sm,
  },
  memberSectionTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  memberNameInput: {
    flex: 1,
  },
  memberConditionInput: {
    flex: 1.2,
  },
  removeMemberButton: {
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addMemberButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    paddingVertical: spacing.xs,
  },
  addMemberText: {
    color: palette.navActive,
    fontSize: 13,
    fontWeight: '800',
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
