import { useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import QRCode from 'react-native-qrcode-svg';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdButton, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type QrModalProps = {
  visible: boolean;
  qr: any;
  onClose: () => void;
};

export function HouseholdQrModal({ visible, qr, onClose }: QrModalProps) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.modalCard}>
          <ModalHeader title="Evacuation QR" onClose={onClose} />
          <View style={styles.qrBox}>
            <QRCode value={qr?.value || 'RESQPERATION-HOUSEHOLD'} size={180} />
          </View>
          <Text style={styles.qrTitle}>{qr?.household_name || 'Household'}</Text>
          <Text style={styles.qrMeta}>{qr?.household_id || 'Household ID'}</Text>
        </View>
      </View>
    </Modal>
  );
}

type PinModalProps = {
  visible: boolean;
  mode: 'set' | 'verify' | 'change';
  saving: boolean;
  error: string;
  onClose: () => void;
  onConfirm: (payload: { pin: string; currentPin?: string }) => Promise<void>;
};

export function TrustedPinModal({ visible, mode, saving, error, onClose, onConfirm }: PinModalProps) {
  const [pin, setPin] = useState('');
  const [confirmPin, setConfirmPin] = useState('');
  const [currentPin, setCurrentPin] = useState('');
  const [showCurrentPin, setShowCurrentPin] = useState(false);
  const [showPin, setShowPin] = useState(false);
  const [showConfirmPin, setShowConfirmPin] = useState(false);
  const needsConfirmation = mode !== 'verify';
  const needsCurrentPin = mode === 'change';

  const title = mode === 'change'
    ? 'Change household PIN'
    : mode === 'set'
      ? 'Set household PIN'
      : 'Enter household PIN';

  useEffect(() => {
    if (!visible) {
      setPin('');
      setConfirmPin('');
      setCurrentPin('');
      setShowCurrentPin(false);
      setShowPin(false);
      setShowConfirmPin(false);
    }
  }, [visible]);

  function submit() {
    if (needsConfirmation && pin !== confirmPin) {
      return;
    }

    if (needsCurrentPin && pin === currentPin) {
      return;
    }

    onConfirm({ pin, currentPin: needsCurrentPin ? currentPin : undefined });
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.modalCard}>
          <ModalHeader title={title} onClose={onClose} />
          <Text style={styles.note}>
            {mode === 'change'
              ? 'Enter your current PIN, then choose a new 4-digit PIN.'
              : mode === 'verify'
                ? 'Required before opening trusted household details.'
                : 'This PIN protects trusted household details for your household.'}
          </Text>
          {needsCurrentPin ? (
            <PinInput
              value={currentPin}
              onChangeText={(value) => setCurrentPin(value.replace(/[^0-9]/g, '').slice(0, 4))}
              placeholder="Current 4-digit PIN"
              isVisible={showCurrentPin}
              onToggleVisibility={() => setShowCurrentPin((current) => !current)}
            />
          ) : null}
          <PinInput
            value={pin}
            onChangeText={(value) => setPin(value.replace(/[^0-9]/g, '').slice(0, 4))}
            placeholder="4-digit PIN"
            isVisible={showPin}
            onToggleVisibility={() => setShowPin((current) => !current)}
          />
          {needsConfirmation ? (
            <PinInput
              value={confirmPin}
              onChangeText={(value) => setConfirmPin(value.replace(/[^0-9]/g, '').slice(0, 4))}
              placeholder="Confirm PIN"
              isVisible={showConfirmPin}
              onToggleVisibility={() => setShowConfirmPin((current) => !current)}
            />
          ) : null}
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {needsConfirmation && pin !== confirmPin && confirmPin.length === 4 ? (
            <Text style={styles.error}>PIN confirmation does not match.</Text>
          ) : null}
          {needsCurrentPin && pin === currentPin && pin.length === 4 ? (
            <Text style={styles.error}>New PIN must be different from the current PIN.</Text>
          ) : null}
          <HouseholdButton
            label={saving ? 'Saving...' : mode === 'change' ? 'Change PIN' : mode === 'set' ? 'Save PIN' : 'Open trusted household'}
            icon={mode === 'change' ? 'lock-closed-outline' : 'lock-open-outline'}
            disabled={saving}
            onPress={submit}
          />
        </View>
      </View>
    </Modal>
  );
}

function PinInput({
  value,
  onChangeText,
  placeholder,
  isVisible,
  onToggleVisibility,
}: {
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  isVisible: boolean;
  onToggleVisibility: () => void;
}) {
  return (
    <View style={styles.pinInputWrap}>
      <TextInput
        style={styles.pinInput}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#7d8da0"
        keyboardType="number-pad"
        secureTextEntry={!isVisible}
      />
      <Pressable
        style={styles.pinVisibilityButton}
        onPress={onToggleVisibility}
        accessibilityRole="button"
        accessibilityLabel={isVisible ? 'Hide PIN' : 'Show PIN'}
      >
        <Ionicons name={isVisible ? 'eye-off-outline' : 'eye-outline'} size={20} color={palette.textSoft} />
      </Pressable>
    </View>
  );
}

type AddTrustedModalProps = {
  visible: boolean;
  loading: boolean;
  lookupResult: any;
  onClose: () => void;
  onLookup: (householdId: string) => void;
  onSubmit: (payload: any) => void;
};

export function AddTrustedHouseholdModal({
  visible,
  loading,
  lookupResult,
  onClose,
  onLookup,
  onSubmit,
}: AddTrustedModalProps) {
  const [householdId, setHouseholdId] = useState('');
  const [reason, setReason] = useState('');
  const [relationships, setRelationships] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!visible) {
      setHouseholdId('');
      setReason('');
      setRelationships({});
    }
  }, [visible]);

  function submit() {
    onSubmit({
      trusted_household_id: lookupResult?.household_id || householdId,
      reason,
      member_relationships: (lookupResult?.members || []).map((member: any) => ({
        member_id: member.member_id,
        name: member.name,
        relationship_to_family: relationships[member.member_id] || '',
      })),
    });
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={[styles.modalCard, styles.tallModal]}>
          <ModalHeader title="Add trusted household" onClose={onClose} />
          <ScrollView contentContainerStyle={styles.modalScroll}>
            <View style={styles.stepRow}>
              <StepPill label="Find" active />
              <StepPill label="Reason" active={Boolean(lookupResult)} />
              <StepPill label="Members" active={Boolean(lookupResult?.members?.length)} />
            </View>

            <View style={styles.lookupRow}>
              <TextInput
                style={[styles.input, styles.lookupInput]}
                value={householdId}
                onChangeText={setHouseholdId}
                placeholder="HH-2024035503 or 2024035503"
                placeholderTextColor="#7d8da0"
                autoCapitalize="characters"
              />
              <Pressable style={styles.lookupButton} onPress={() => onLookup(householdId.trim())}>
                <Ionicons name="search-outline" size={19} color="#fff" />
              </Pressable>
            </View>

            {lookupResult ? (
              <View style={styles.lookupCard}>
                <Text style={styles.lookupLabel}>Connect with</Text>
                <Text style={styles.lookupTitle}>{lookupResult.family_name} household</Text>
                <Text style={styles.lookupMeta}>{lookupResult.household_id}</Text>
              </View>
            ) : (
              <HouseholdEmpty icon="home-outline" title="No household selected" />
            )}

            <TextInput
              style={[styles.input, styles.textArea]}
              value={reason}
              onChangeText={setReason}
              placeholder="Reason for trusted access"
              placeholderTextColor="#7d8da0"
              multiline
              textAlignVertical="top"
            />

            {lookupResult?.members?.length ? (
              <View style={styles.relationshipStack}>
                <HouseholdSection title="Member relationships" />
                {lookupResult.members.map((member: any) => (
                  <View key={member.member_id} style={styles.relationshipRow}>
                    <Text style={styles.memberName}>{member.name}</Text>
                    {member.household_relationship ? (
                      <Text style={styles.memberMeta}>{member.household_relationship}</Text>
                    ) : null}
                    <TextInput
                      style={[styles.input, styles.relationshipInput]}
                      value={relationships[member.member_id] || ''}
                      onChangeText={(value) => setRelationships((current) => ({ ...current, [member.member_id]: value }))}
                      placeholder="Relationship"
                      placeholderTextColor="#7d8da0"
                    />
                  </View>
                ))}
              </View>
            ) : null}
          </ScrollView>
          <HouseholdButton
            label={loading ? 'Saving...' : 'Send request'}
            icon="send-outline"
            disabled={loading || !lookupResult || !reason.trim()}
            onPress={submit}
          />
        </View>
      </View>
    </Modal>
  );
}

function ModalHeader({ title, onClose }: { title: string; onClose: () => void }) {
  return (
    <View style={styles.modalHeader}>
      <Text style={styles.modalTitle}>{title}</Text>
      <Pressable style={styles.closeButton} onPress={onClose}>
        <Ionicons name="close-outline" size={22} color={palette.nav} />
      </Pressable>
    </View>
  );
}

function StepPill({ label, active }: { label: string; active: boolean }) {
  return (
    <View style={[styles.stepPill, active && styles.stepPillActive]}>
      <Text style={[styles.stepText, active && styles.stepTextActive]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.lg,
    backgroundColor: '#0a152099',
  },
  modalCard: {
    width: '100%',
    maxWidth: 420,
    gap: spacing.md,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  tallModal: {
    maxHeight: '88%',
  },
  modalScroll: {
    gap: spacing.md,
    paddingBottom: spacing.sm,
  },
  stepRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  stepPill: {
    flex: 1,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingVertical: 8,
    backgroundColor: palette.secondary,
  },
  stepPillActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  stepText: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
  },
  stepTextActive: {
    color: '#fff',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  modalTitle: {
    color: palette.text,
    fontSize: 18,
    fontWeight: '900',
  },
  closeButton: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
  },
  qrBox: {
    alignSelf: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: '#fff',
  },
  qrTitle: {
    color: palette.text,
    fontSize: 17,
    fontWeight: '900',
    textAlign: 'center',
  },
  qrMeta: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
    textAlign: 'center',
  },
  note: {
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 19,
    fontWeight: '700',
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
  pinInputWrap: {
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    backgroundColor: '#fff',
  },
  pinInput: {
    flex: 1,
    minHeight: 46,
    paddingLeft: spacing.md,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  pinVisibilityButton: {
    width: 46,
    height: 46,
    alignItems: 'center',
    justifyContent: 'center',
  },
  textArea: {
    minHeight: 90,
    paddingTop: spacing.md,
  },
  error: {
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '900',
  },
  lookupRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  lookupInput: {
    flex: 1,
  },
  lookupButton: {
    width: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.navActive,
  },
  lookupCard: {
    gap: 4,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  lookupLabel: {
    color: palette.textSoft,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  lookupTitle: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
  },
  lookupMeta: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  relationshipStack: {
    gap: spacing.sm,
  },
  relationshipRow: {
    gap: 6,
  },
  memberName: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  memberMeta: {
    marginTop: -2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  relationshipInput: {
    minHeight: 42,
  },
});
