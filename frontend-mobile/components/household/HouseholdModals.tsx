import { householdQrValue } from '@/utils/householdQr';
import { useEffect, useRef, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { saveQrImage } from '@/utils/saveQrImage';
import type Svg from 'react-native-svg';
import Ionicons from '@expo/vector-icons/Ionicons';
import QRCode from 'react-native-qrcode-svg';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdButton, HouseholdEmpty } from './HouseholdUI';

type QrModalProps = {
  visible: boolean;
  qr: any;
  householdId: string | number | null | undefined;
  onClose: () => void;
};

export function HouseholdQrModal({ visible, qr, householdId, onClose }: QrModalProps) {
  const qrValue = householdQrValue(qr, householdId);
  const qrRef = useRef<Svg | null>(null);
  const downloading = useRef(false);
  const [saving, setSaving] = useState(false);

  async function downloadQr() {
    if (!qrValue || !qrRef.current || downloading.current) return;
    downloading.current = true;
    setSaving(true);
    try {
      const filename = `qr-${qrValue.replace(/[^a-zA-Z0-9._-]/g, '_')}-${Date.now()}.png`;
      const svg = qrRef.current;
      const base64 = await new Promise<string>((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('QR export timed out. Please try again.')), 10000);
        try {
          svg.toDataURL((data) => {
            clearTimeout(timeout);
            if (!data) reject(new Error('Unable to export the QR image.'));
            else resolve(data);
          }, { width: 512, height: 512 });
        } catch (error) {
          clearTimeout(timeout);
          reject(error);
        }
      });

      Alert.alert('QR downloaded', await saveQrImage(base64, filename));
    } catch (error) {
      Alert.alert('Unable to download QR', error instanceof Error ? error.message : 'Could not save the PNG. Please try again.');
    } finally {
      downloading.current = false;
      setSaving(false);
    }
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={saving ? () => {} : onClose}>
      <View style={styles.backdrop}>
        <View style={styles.modalCard}>
          <ModalHeader title="Evacuation QR" onClose={saving ? () => {} : onClose} />
          {qrValue ? <>
            <View style={styles.qrBox}>
              <QRCode value={qrValue} getRef={(ref: Svg | null) => { qrRef.current = ref; }} size={180} quietZone={16} color="#0a1520" backgroundColor="#fff" />
            </View>
            {qr?.household_name ? <Text style={styles.qrTitle}>{qr.household_name}</Text> : null}
            <Text style={styles.qrMeta} selectable>Household ID: {qrValue}</Text>
            <HouseholdButton label={saving ? 'Saving PNG...' : 'Download QR'} icon="download-outline" tone="nav" disabled={saving} onPress={downloadQr} />
          </> : <Text style={styles.qrMeta} accessibilityRole="alert">Household QR unavailable. Refresh your household data and try again.</Text>}
        </View>
      </View>
    </Modal>
  );
}

export function HouseholdNotificationsModal({ visible, requests, notices = [], busyId, onClose, onAccept, onReject }: {
  visible: boolean;
  requests: any[];
  notices?: any[];
  busyId: string;
  onClose: () => void;
  onAccept: (request: any) => void;
  onReject: (request: any) => void;
}) {
  const [expandedId, setExpandedId] = useState('');

  useEffect(() => {
    if (!visible) setExpandedId('');
  }, [visible]);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={[styles.modalCard, styles.tallModal]}>
          <ModalHeader title="Notifications" onClose={onClose} />
          <Text style={styles.note}>Trusted household requests and updates for your household.</Text>
          <ScrollView contentContainerStyle={styles.notificationList}>
            {notices.map((notice) => (
              <View key={`notice-${notice.notification_id}`} style={styles.infoNotificationCard}>
                <View style={styles.notificationIcon}><Ionicons name="information-circle-outline" size={19} color={palette.navActive} /></View>
                <View style={styles.notificationCopy}>
                  <Text style={styles.notificationTitle}>{notice.title}</Text>
                  <Text style={styles.notificationDetailText}>{notice.message}</Text>
                  <Text style={styles.notificationDate}>{notice.created_label || ''}</Text>
                </View>
              </View>
            ))}
            {requests.length ? requests.map((request) => {
              const id = String(request.connection_id);
              const expanded = expandedId === id;
              const householdName = request.household_name || request.family_name || 'Household';
              return (
                <View key={id} style={styles.notificationCard}>
                  <Pressable
                    style={styles.notificationRow}
                    onPress={() => setExpandedId(expanded ? '' : id)}
                    accessibilityRole="button"
                    accessibilityLabel={`${householdName}, household ID ${request.requesting_household_id}`}
                  >
                    <View style={styles.notificationIcon}><Ionicons name="people-outline" size={19} color={palette.navActive} /></View>
                    <View style={styles.notificationCopy}>
                      <Text style={styles.notificationTitle}>Trusted household request</Text>
                      <Text style={styles.notificationMeta}>{householdName} · {request.requesting_household_id}</Text>
                    </View>
                    <Ionicons name={expanded ? 'chevron-up' : 'chevron-down'} size={18} color={palette.textSoft} />
                  </Pressable>
                  {expanded ? (
                    <View style={styles.notificationDetails}>
                      <Text style={styles.notificationDetailText}>Relationship: {request.relationship_label || request.reason || 'Trusted household connection request'}</Text>
                      <Text style={styles.notificationDate}>{request.created_label || ''}</Text>
                      <View style={styles.notificationActions}>
                        <Pressable
                          style={[styles.notificationAction, styles.rejectAction]}
                          onPress={() => onReject(request)}
                          disabled={busyId === id}
                        >
                          <Text style={[styles.notificationActionText, styles.rejectText]}>{busyId === id ? 'Please wait...' : 'Reject'}</Text>
                        </Pressable>
                        <Pressable
                          style={[styles.notificationAction, styles.acceptAction]}
                          onPress={() => onAccept(request)}
                          disabled={busyId === id}
                        >
                          <Text style={[styles.notificationActionText, styles.acceptText]}>{busyId === id ? 'Please wait...' : 'Accept'}</Text>
                        </Pressable>
                      </View>
                    </View>
                  ) : null}
                </View>
              );
            }) : notices.length === 0 ? (
              <HouseholdEmpty icon="notifications-outline" title="You're all caught up" body="New trusted household requests will appear here." />
            ) : null}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

type PinModalProps = {
  visible: boolean;
  mode: 'set' | 'verify' | 'change';
  householdName?: string;
  saving: boolean;
  error: string;
  onClose: () => void;
  onConfirm: (payload: { pin: string; currentPin?: string; currentPassword?: string }) => Promise<void>;
};

export function TrustedPinModal({ visible, mode, householdName, saving, error, onClose, onConfirm }: PinModalProps) {
  const [pin, setPin] = useState('');
  const [confirmPin, setConfirmPin] = useState('');
  const [currentPin, setCurrentPin] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [validationError, setValidationError] = useState('');
  const [showCurrentPin, setShowCurrentPin] = useState(false);
  const [showPin, setShowPin] = useState(false);
  const [showConfirmPin, setShowConfirmPin] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [forgotPin, setForgotPin] = useState(false);
  const needsConfirmation = mode === 'set' || mode === 'change';
  const needsCurrentPin = mode === 'change' && !forgotPin;

  const title = mode === 'change'
    ? 'Change Household PIN'
    : mode === 'set'
      ? 'Set Household PIN'
      : 'Enter Trusted Household PIN';

  useEffect(() => {
    if (!visible) {
      setPin('');
      setConfirmPin('');
      setCurrentPin('');
      setCurrentPassword('');
      setValidationError('');
      setShowCurrentPin(false);
      setShowPin(false);
      setShowConfirmPin(false);
      setShowCurrentPassword(false);
      setForgotPin(false);
    }
  }, [visible]);

  function submit() {
    if (mode === 'change' && forgotPin && !currentPassword) {
      setValidationError('Enter your account password to change your PIN.');
      return;
    }

    if (needsCurrentPin && currentPin.length !== 4) {
      setValidationError('Enter your current 4-digit PIN.');
      return;
    }

    if (pin.length !== 4) {
      setValidationError('Enter a 4-digit PIN.');
      return;
    }

    if (needsConfirmation && confirmPin.length !== 4) {
      setValidationError('Confirm your 4-digit PIN.');
      return;
    }

    if (needsConfirmation && pin !== confirmPin) {
      setValidationError('PIN confirmation does not match.');
      return;
    }

    if (needsCurrentPin && pin === currentPin) {
      setValidationError('New PIN must be different from the current PIN.');
      return;
    }

    setValidationError('');
    onConfirm({
      pin,
      currentPin: needsCurrentPin ? currentPin : undefined,
      currentPassword: mode === 'change' && forgotPin ? currentPassword : undefined,
    });
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.modalCard}>
          <ModalHeader title={title} onClose={onClose} />
          <Text style={styles.note}>
            {mode === 'change'
              ? forgotPin
                ? 'Verify your account password, then choose a new 4-digit PIN.'
                : 'Enter your current PIN, then choose a new 4-digit PIN.'
              : mode === 'verify'
                ? `Enter ${householdName || 'this trusted household'}'s 4-digit PIN to view members and update their status.`
                : 'This PIN protects trusted household details for your household.'}
          </Text>
          {mode === 'change' && forgotPin ? (
            <View style={styles.pinInputWrap}>
              <TextInput
                style={styles.pinInput}
                value={currentPassword}
                onChangeText={(value) => {
                  setCurrentPassword(value);
                  setValidationError('');
                }}
                placeholder="Account password"
                placeholderTextColor="#7d8da0"
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry={!showCurrentPassword}
                autoComplete="current-password"
              />
              <Pressable
                style={styles.pinVisibilityButton}
                onPress={() => setShowCurrentPassword((current) => !current)}
                accessibilityRole="button"
                accessibilityLabel={showCurrentPassword ? 'Hide password' : 'Show password'}
              >
                <Ionicons name={showCurrentPassword ? 'eye-off-outline' : 'eye-outline'} size={20} color={palette.textSoft} />
              </Pressable>
            </View>
          ) : null}
          {needsCurrentPin ? (
            <PinInput
              value={currentPin}
              onChangeText={(value) => {
                setCurrentPin(value.replace(/[^0-9]/g, '').slice(0, 4));
                setValidationError('');
              }}
              placeholder="Current 4-digit PIN"
              isVisible={showCurrentPin}
              onToggleVisibility={() => setShowCurrentPin((current) => !current)}
            />
          ) : null}
          <PinInput
            value={pin}
            onChangeText={(value) => {
              setPin(value.replace(/[^0-9]/g, '').slice(0, 4));
              setValidationError('');
            }}
            placeholder="4-digit PIN"
            isVisible={showPin}
            onToggleVisibility={() => setShowPin((current) => !current)}
          />
          {needsConfirmation ? (
            <PinInput
              value={confirmPin}
              onChangeText={(value) => {
                setConfirmPin(value.replace(/[^0-9]/g, '').slice(0, 4));
                setValidationError('');
              }}
              placeholder="Confirm PIN"
              isVisible={showConfirmPin}
              onToggleVisibility={() => setShowConfirmPin((current) => !current)}
            />
          ) : null}
          {mode === 'change' ? (
            <Pressable
              onPress={() => {
                setForgotPin((current) => !current);
                setCurrentPin('');
                setCurrentPassword('');
                setValidationError('');
              }}
              accessibilityRole="button"
            >
              <Text style={styles.forgotPinText}>
                {forgotPin ? 'Enter my PIN' : 'Forgot PIN?'}
              </Text>
            </Pressable>
          ) : null}
          {error || validationError ? <Text style={styles.error}>{error || validationError}</Text> : null}
          <HouseholdButton
            label={saving ? 'Saving...' : mode === 'change' ? 'Change PIN' : mode === 'set' ? 'Save PIN' : 'Open trusted household'}
            icon={mode === 'change' ? 'lock-closed-outline' : 'lock-open-outline'}
            tone="nav"
            pressedBackgroundColor="#ffffff20"
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
  onLookup: (identifier: string) => void;
  onSubmit: (payload: any) => void;
};

const trustedRelationshipOptions = [
  { relationshipID: 'relative', relationshipLabel: 'Relative', icon: 'people-outline' },
  { relationshipID: 'extended_family_household', relationshipLabel: 'Extended Family Household', icon: 'home-outline' },
  { relationshipID: 'family_friend_household', relationshipLabel: 'Family Friend Household', icon: 'heart-outline' },
  { relationshipID: 'close_friend_household', relationshipLabel: "Close Friend's Household", icon: 'person-add-outline' },
] as const;

export function AddTrustedHouseholdModal({
  visible,
  loading,
  lookupResult,
  onClose,
  onLookup,
  onSubmit,
}: AddTrustedModalProps) {
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [householdIdentifier, setHouseholdIdentifier] = useState('');
  const [relationship, setRelationship] = useState<(typeof trustedRelationshipOptions)[number] | null>(null);
  const [targetPin, setTargetPin] = useState('');
  const [isTargetPinVisible, setIsTargetPinVisible] = useState(false);
  const householdDisplayName = String(
    lookupResult?.household_name || lookupResult?.household_code || lookupResult?.household_id || 'Trusted household'
  ).trim();

  useEffect(() => {
    if (!visible) {
      setStep(1);
      setHouseholdIdentifier('');
      setRelationship(null);
      setTargetPin('');
      setIsTargetPinVisible(false);
    }
  }, [visible]);

  useEffect(() => {
    if (visible && lookupResult) {
      setStep(2);
    }
  }, [lookupResult, visible]);

  function submit() {
    if (!relationship) {
      return;
    }

    onSubmit({
      trusted_household_id: lookupResult?.household_id || householdIdentifier,
      household_identifier: householdIdentifier,
      relationshipID: relationship.relationshipID,
      relationshipLabel: relationship.relationshipLabel,
      pin: targetPin,
    });
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={[styles.modalCard, styles.tallModal]}>
          <ModalHeader title="Add trusted household" onClose={onClose} />
          <ScrollView contentContainerStyle={styles.modalScroll}>
            <View style={styles.stepRow}>
              <StepPill label="1. Identity" active={step === 1} complete={step > 1} />
              <StepPill label="2. Relationship" active={step === 2} complete={step > 2} />
              <StepPill label="3. Verify PIN" active={step === 3} />
            </View>

            {step === 1 ? (
              <View style={styles.stepContent}>
                <Text style={styles.stepTitle}>Find a household</Text>
                <Text style={styles.note}>Enter the trusted household ID or the household account username.</Text>
                <TextInput
                  style={styles.input}
                  value={householdIdentifier}
                  onChangeText={setHouseholdIdentifier}
                  placeholder="Household ID or username"
                  placeholderTextColor="#7d8da0"
                  autoCapitalize="none"
                  autoCorrect={false}
                  returnKeyType="search"
                  onSubmitEditing={() => onLookup(householdIdentifier.trim())}
                  accessibilityLabel="Trusted household ID or username"
                />
                <HouseholdButton
                  label={loading ? 'Looking up household...' : 'Continue'}
                  icon="arrow-forward-outline"
                  tone="nav"
                  pressedBackgroundColor="#ffffff20"
                  disabled={loading || !householdIdentifier.trim()}
                  onPress={() => onLookup(householdIdentifier.trim())}
                />
              </View>
            ) : null}

            {step === 2 && lookupResult ? (
              <View style={styles.stepContent}>
                <View style={styles.lookupCard}>
                  <Text style={styles.lookupLabel}>Adding</Text>
                  <Text style={styles.lookupTitle}>{householdDisplayName}</Text>
                  <Text style={styles.lookupMeta}>{lookupResult.household_id}</Text>
                </View>
                <Text style={styles.stepTitle}>Select your relationship</Text>
                <Text style={styles.note}>Choose the relationship that best describes this household.</Text>
                <View style={styles.relationshipChoices}>
                  {trustedRelationshipOptions.map((option) => (
                    <Pressable
                      key={option.relationshipID}
                      style={({ pressed }) => [styles.relationshipChoice, pressed && styles.relationshipChoicePressed]}
                      onPress={() => {
                        setRelationship(option);
                        setStep(3);
                      }}
                      accessibilityRole="button"
                      accessibilityLabel={option.relationshipLabel}
                    >
                      <View style={styles.relationshipChoiceIcon}>
                        <Ionicons name={option.icon} size={20} color={palette.navActive} />
                      </View>
                      <Text style={styles.relationshipChoiceText}>{option.relationshipLabel}</Text>
                      <Ionicons name="chevron-forward-outline" size={18} color={palette.textSoft} />
                    </Pressable>
                  ))}
                </View>
                <Pressable style={styles.backLink} onPress={() => setStep(1)} accessibilityRole="button">
                  <Text style={styles.backLinkText}>Use a different household</Text>
                </Pressable>
              </View>
            ) : null}

            {step === 3 && lookupResult && relationship ? (
              <View style={styles.stepContent}>
                <View style={styles.lookupCard}>
                  <Text style={styles.lookupLabel}>Relationship</Text>
                  <Text style={styles.lookupTitle}>{relationship.relationshipLabel}</Text>
                  <Text style={styles.lookupMeta}>{householdDisplayName} · {lookupResult.household_id}</Text>
                </View>
                <Text style={styles.stepTitle}>Verify household PIN</Text>
                <Text style={styles.note}>Enter the PIN for {householdDisplayName}. A correct PIN sends the request for their confirmation.</Text>
                <View style={styles.pinInputWrap}>
                <TextInput
                  style={styles.pinInput}
                  value={targetPin}
                  onChangeText={(value) => setTargetPin(value.replace(/[^0-9]/g, '').slice(0, 4))}
                  placeholder="4-digit household PIN"
                  placeholderTextColor="#7d8da0"
                  keyboardType="number-pad"
                  secureTextEntry={!isTargetPinVisible}
                  maxLength={4}
                  accessibilityLabel="Target household 4-digit PIN"
                />
                  <Pressable
                    style={styles.pinVisibilityButton}
                    onPress={() => setIsTargetPinVisible((current) => !current)}
                    accessibilityRole="button"
                    accessibilityLabel={isTargetPinVisible ? 'Hide household PIN' : 'Show household PIN'}
                  >
                    <Ionicons name={isTargetPinVisible ? 'eye-off-outline' : 'eye-outline'} size={20} color={palette.textSoft} />
                  </Pressable>
                </View>
                <Pressable style={styles.backLink} onPress={() => setStep(2)} accessibilityRole="button">
                  <Text style={styles.backLinkText}>Change relationship</Text>
                </Pressable>
              </View>
            ) : null}
          </ScrollView>
          {step === 3 ? (
            <HouseholdButton
              label={loading ? 'Verifying PIN...' : 'Send request'}
              icon="send-outline"
              tone="nav"
              pressedBackgroundColor="#ffffff20"
              disabled={loading || !lookupResult || !relationship || targetPin.length !== 4}
              onPress={submit}
            />
          ) : null}
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

function StepPill({ label, active, complete = false }: { label: string; active: boolean; complete?: boolean }) {
  return (
    <View style={[styles.stepPill, (active || complete) && styles.stepPillActive]}>
      <Text style={[styles.stepText, (active || complete) && styles.stepTextActive]}>{label}</Text>
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
  notificationList: {
    gap: spacing.sm,
    paddingBottom: spacing.sm,
  },
  notificationCard: {
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.card,
    overflow: 'hidden',
  },
  infoNotificationCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: `${palette.navActive}44`,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: `${palette.navActive}0b`,
  },
  notificationRow: {
    minHeight: 68,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  notificationIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: `${palette.navActive}15`,
  },
  notificationCopy: { flex: 1, gap: 3 },
  notificationTitle: { color: palette.text, fontSize: 13, fontWeight: '800' },
  notificationMeta: { color: palette.textSoft, fontSize: 11, fontWeight: '600' },
  notificationDetails: {
    borderTopWidth: 1,
    borderTopColor: palette.border,
    padding: spacing.md,
    gap: spacing.sm,
  },
  notificationDetailText: { color: palette.text, fontSize: 13, lineHeight: 19 },
  notificationDate: { color: palette.textSoft, fontSize: 11 },
  notificationActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: spacing.sm, marginTop: spacing.xs },
  notificationAction: { minWidth: 88, minHeight: 36, alignItems: 'center', justifyContent: 'center', borderRadius: radius.sm, paddingHorizontal: spacing.md },
  rejectAction: { backgroundColor: palette.nav, opacity: 0.55 },
  acceptAction: { backgroundColor: palette.nav },
  notificationActionText: { fontSize: 12, fontWeight: '800' },
  rejectText: { color: '#fff' },
  acceptText: { color: '#fff' },
  targetPinGroup: { gap: spacing.xs },
  stepContent: {
    gap: spacing.md,
  },
  stepTitle: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
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
    borderColor: palette.nav,
    backgroundColor: palette.nav,
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
  identifierInputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  identifierInput: {
    flex: 1,
    minWidth: 0,
  },
  scanIconButton: {
    width: 46,
    height: 46,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
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
  forgotPinText: {
    alignSelf: 'flex-end',
    color: palette.navActive,
    fontSize: 13,
    fontWeight: '800',
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
  relationshipChoices: {
    gap: spacing.sm,
  },
  relationshipChoice: {
    minHeight: 62,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: '#fff',
  },
  relationshipChoicePressed: {
    borderColor: palette.borderStrong,
    backgroundColor: palette.secondary,
  },
  relationshipChoiceIcon: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 17,
    backgroundColor: `${palette.navActive}14`,
  },
  relationshipChoiceText: {
    flex: 1,
    color: palette.text,
    fontSize: 13,
    fontWeight: '800',
  },
  backLink: {
    alignSelf: 'flex-start',
    minHeight: 34,
    justifyContent: 'center',
  },
  backLinkText: {
    color: palette.navActive,
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
