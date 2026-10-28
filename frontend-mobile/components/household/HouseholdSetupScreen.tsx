import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { useHouseholdSetup } from '@/hooks/useHouseholdSetup';
import { HouseholdSetupAddressForm } from './HouseholdSetupAddressForm';
import { HouseholdBadge, HouseholdButton, HouseholdSection } from './HouseholdUI';

type SetupProps = {
  overview: any;
  deviceUuid: string;
  onComplete: (payload: any) => Promise<void>;
};

export function HouseholdSetupScreen({ overview, deviceUuid, onComplete }: SetupProps) {
  const user = overview.profile?.user || {};
  const [privacyAccepted, setPrivacyAccepted] = useState(false);
  const { pin, address, details, setDetails, saving, findingAddress, geotagMessage,
    requestGpsLocation, handleSave } = useHouseholdSetup({ overview, deviceUuid, onComplete });

  function completeSetup() {
    if (!privacyAccepted) {
      Alert.alert('Agreement required', 'Confirm that this household location may be used for disaster response.');
      return;
    }
    void handleSave();
  }

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <HouseholdBadge label="Required setup" tone="warning" />
        <Text style={styles.heroTitle}>Pin your household location</Text>
      </View>
      <View style={styles.card}>
        <HouseholdSection title="Location" action={<HouseholdButton label="Use GPS" icon="locate-outline" tone="light"
          disabled={saving || findingAddress} onPress={requestGpsLocation} />} />
        <View style={styles.webMapFallback}>
          <Ionicons name="map-outline" size={30} color={palette.navActive} />
          <Text style={styles.fallbackText}>Use GPS here, or open the mobile app to choose the household location on the map.</Text>
          <HouseholdBadge label={findingAddress ? 'Checking location...' : pin ? 'Location selected' : 'No location selected'} tone={pin ? 'info' : 'neutral'} />
        </View>
        <Text style={styles.helperText}>{geotagMessage}</Text>
      </View>
      <HouseholdSetupAddressForm address={address} details={details} setDetails={setDetails} disabled={saving} />
      <View style={styles.card}>
        <HouseholdSection title="Device user" />
        <Text style={styles.previewText}>{user.full_name || 'Member unavailable'}</Text>
        <Text style={styles.helperText}>Member ID: {user.member_id}</Text>
      </View>
      <Pressable style={styles.privacyRow} onPress={() => setPrivacyAccepted((value) => !value)}>
        <View style={[styles.checkBox, privacyAccepted && styles.checkBoxActive]}>
          {privacyAccepted ? <Ionicons name="checkmark" size={15} color="#fff" /> : null}
        </View>
        <Text style={styles.privacyText}>I agree that this household location may be used by HQ and assigned rescuers during disaster response.</Text>
      </Pressable>
      <HouseholdButton label={saving ? 'Saving setup...' : 'Complete setup'} icon="checkmark-circle-outline"
        disabled={saving || findingAddress} onPress={completeSetup} />
    </View>
  );
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  hero: {
    gap: spacing.sm,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.nav,
    ...shadow,
  },
  heroTitle: {
    color: '#fff',
    fontSize: 22,
    fontWeight: '900',
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  webMapFallback: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    minHeight: 220,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.lg,
    backgroundColor: palette.secondary,
  },
  fallbackTitle: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
    textAlign: 'center',
  },
  fallbackText: {
    maxWidth: 320,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
    lineHeight: 18,
    textAlign: 'center',
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
  previewBox: {
    gap: 4,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.page,
  },
  previewText: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '800',
    lineHeight: 18,
  },
  choiceGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  choice: {
    flexBasis: '48%',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  smallChoice: {
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: 9,
    backgroundColor: palette.card,
  },
  choiceActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  choiceTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  choiceSub: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  choiceTextActive: {
    color: '#fff',
  },
  label: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  helperText: {
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '800',
  },
  privacyRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  checkBox: {
    width: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: 6,
    backgroundColor: palette.card,
  },
  checkBoxActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  privacyText: {
    flex: 1,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
    lineHeight: 17,
  },
});
