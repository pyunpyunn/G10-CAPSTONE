import type { Dispatch, SetStateAction } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';
import type { SetupDetails } from '@/hooks/useHouseholdSetup';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { HouseholdButton, HouseholdSection } from './HouseholdUI';

export function HouseholdSetupAddressForm({ address, details, setDetails, disabled }: {
  address: any; details: SetupDetails; setDetails: Dispatch<SetStateAction<SetupDetails>>; disabled: boolean;
}) {
  function field(key: Exclude<keyof SetupDetails, 'is_apartment'>, label: string, maxLength: number) {
    return <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput accessibilityLabel={label} style={styles.input} value={details[key]} editable={!disabled} maxLength={maxLength}
        onChangeText={(value) => setDetails((current) => ({ ...current, [key]: value }))} />
    </View>;
  }
  return <View style={styles.card}>
    <HouseholdSection title="Household address" />
    <Text style={styles.label}>Registered address</Text>
    <Text style={styles.address}>{address
      ? [address.street || address.street_address, address.purok_sitio, address.purok_name, address.sitio_name, address.barangay_name, address.city_name, address.province_name].filter(Boolean).join(', ')
      : 'No registered address available. Your map pin is saved separately.'}</Text>
    <Text style={styles.label}>Do you live in an apartment?</Text>
    <View style={styles.row}>
      <HouseholdButton label="Yes" tone={details.is_apartment === true ? 'primary' : 'light'} disabled={disabled}
        onPress={() => setDetails((current) => ({ ...current, is_apartment: true }))} />
      <HouseholdButton label="No" tone={details.is_apartment === false ? 'primary' : 'light'} disabled={disabled}
        onPress={() => setDetails((current) => ({ ...current, is_apartment: false }))} />
    </View>
    {field('house_number', 'House / building number (if available)', 80)}
    {details.is_apartment ? <>
      {field('building_name', 'Building / apartment name (optional)', 120)}
      {field('unit_number', 'Unit number (required)', 80)}
      {field('floor_number', 'Floor (optional)', 30)}
      {field('room_number', 'Room (optional)', 80)}
    </> : null}
    {field('landmark', 'Nearby landmark (optional)', 255)}
  </View>;
}

const styles = StyleSheet.create({
  card: { gap: spacing.md, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.lg, backgroundColor: palette.card },
  field: { gap: spacing.xs },
  label: { color: palette.textSoft, fontWeight: '700' },
  address: { color: palette.text, fontWeight: '700' },
  input: { minHeight: 44, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, paddingHorizontal: spacing.md, color: palette.text },
  row: { flexDirection: 'row', gap: spacing.sm },
});
