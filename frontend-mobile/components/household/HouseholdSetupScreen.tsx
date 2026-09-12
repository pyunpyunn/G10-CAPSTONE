import { useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { reverseGeocodeAddress } from '@/utils/geocoding';
import { HouseholdBadge, HouseholdButton, HouseholdSection } from './HouseholdUI';

type SetupProps = {
  overview: any;
  deviceUuid: string;
  onComplete: (payload: any) => Promise<void>;
};

const relationships = [
  'Head of household',
  'Spouse / Partner',
  'Son / Daughter',
  'Father / Mother',
  'Brother / Sister',
  'Grandparent',
  'Grandchild',
  'Guardian',
  'Relative',
  'Other',
];

export function HouseholdSetupScreen({ overview, deviceUuid, onComplete }: SetupProps) {
  const household = overview.profile?.household || {};
  const members = overview.members || [];
  const [pin, setPin] = useState<any>(null);
  const [locationLabel, setLocationLabel] = useState<string>(household.address || '');
  const [houseNumber, setHouseNumber] = useState<string>('');
  const [buildingName, setBuildingName] = useState<string>('');
  const [floorNumber, setFloorNumber] = useState<string>('');
  const [unitNumber, setUnitNumber] = useState<string>('');
  const [roomNumber, setRoomNumber] = useState<string>('');
  const [street, setStreet] = useState<string>('');
  const [barangay, setBarangay] = useState<string>(household.barangay === 'Not recorded' ? '' : household.barangay || '');
  const [city, setCity] = useState<string>(household.city === 'Not recorded' ? '' : household.city || '');
  const [province, setProvince] = useState<string>(household.province === 'Not recorded' ? '' : household.province || '');
  const [memberId, setMemberId] = useState(members[0]?.member_id || '');
  const [relationship, setRelationship] = useState('Head of household');
  const [privacyAccepted, setPrivacyAccepted] = useState(false);
  const [saving, setSaving] = useState(false);
  const [findingAddress, setFindingAddress] = useState(false);

  const selectedAddress = useMemo(() => {
    return [buildingName, floorNumber, unitNumber, roomNumber, houseNumber, street, barangay, city, province]
      .map((part) => part.trim())
      .filter(Boolean)
      .join(', ');
  }, [buildingName, floorNumber, unitNumber, roomNumber, houseNumber, street, barangay, city, province]);

  const rescueAddress = useMemo(() => {
    return buildRescueAddress(selectedAddress, locationLabel);
  }, [selectedAddress, locationLabel]);

  async function applyPinnedCoordinate(coordinate: any) {
    setPin(coordinate);
    setFindingAddress(true);
    setLocationLabel('Finding address...');

    try {
      const address = await reverseGeocodeAddress(coordinate.latitude, coordinate.longitude);

      setLocationLabel(address.label);
      setStreet(address.street);
      setBarangay(address.barangay);
      setCity(address.city);
      setProvince(address.province);
    } catch {
      setLocationLabel('Selected GPS location');
    } finally {
      setFindingAddress(false);
    }
  }

  async function useCurrentLocation() {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      Alert.alert('Location required', 'Allow location access on mobile, or open the app in Expo Go to pin on the native map.');
      return;
    }

    const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    await applyPinnedCoordinate({
      latitude: current.coords.latitude,
      longitude: current.coords.longitude,
      accuracy_m: current.coords.accuracy,
    });
  }

  async function handleSave() {
    if (!pin) {
      Alert.alert('Location required', 'Use GPS on this device or complete setup in Expo Go on Android/iPhone.');
      return;
    }

    if (!rescueAddress.trim()) {
      Alert.alert('Address required', 'Confirm the household address before saving setup.');
      return;
    }

    if (!memberId) {
      Alert.alert('Member required', 'Select which family member is using this device.');
      return;
    }

    if (!privacyAccepted) {
      Alert.alert('Agreement required', 'Confirm that this geotag and address may be used by HQ and assigned rescuers during disaster response.');
      return;
    }

    setSaving(true);

    try {
      await onComplete({
        latitude: pin.latitude,
        longitude: pin.longitude,
        accuracy_m: pin.accuracy_m,
        address_label: rescueAddress,
        house_number: houseNumber.trim(),
        unit_number: [buildingName, floorNumber, unitNumber, roomNumber].map((part) => part.trim()).filter(Boolean).join(' / '),
        street: street.trim(),
        barangay: barangay.trim(),
        city: city.trim(),
        province: province.trim(),
        member_id: memberId,
        relationship_to_family: relationship,
        device_uuid: deviceUuid,
        device_name: 'Household mobile',
        platform: 'expo',
        photo_uri: null,
      });
    } finally {
      setSaving(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <HouseholdBadge label="Required setup" tone="warning" />
        <Text style={styles.heroTitle}>Pin your household location</Text>
      </View>

      <View style={styles.card}>
        <HouseholdSection
          title="Location"
          action={<HouseholdButton label="Use GPS" icon="locate-outline" tone="light" onPress={useCurrentLocation} />}
        />
        <View style={styles.webMapFallback}>
          <Ionicons name="map-outline" size={30} color={palette.navActive} />
          <Text style={styles.fallbackTitle}>Map pinning is mobile-only</Text>
          <Text style={styles.fallbackText}>
            Open this setup screen in Expo Go on Android or iPhone to tap the native map. Web can use GPS only.
          </Text>
          <HouseholdBadge label={findingAddress ? 'Finding address...' : pin ? 'GPS selected' : 'No GPS selected'} tone={pin ? 'info' : 'neutral'} />
        </View>
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Address confirmation" />
        <TextInput
          style={styles.input}
          value={locationLabel}
          onChangeText={setLocationLabel}
          placeholder="Selected address / landmark"
          placeholderTextColor="#7d8da0"
        />
        <TextInput
          style={styles.input}
          value={buildingName}
          onChangeText={setBuildingName}
          placeholder="Building / apartment / boarding house"
          placeholderTextColor="#7d8da0"
        />
        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={floorNumber} onChangeText={setFloorNumber} placeholder="Floor" placeholderTextColor="#7d8da0" />
          <TextInput style={[styles.input, styles.flexInput]} value={unitNumber} onChangeText={setUnitNumber} placeholder="Unit" placeholderTextColor="#7d8da0" />
        </View>
        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={roomNumber} onChangeText={setRoomNumber} placeholder="Room" placeholderTextColor="#7d8da0" />
          <TextInput style={[styles.input, styles.flexInput]} value={houseNumber} onChangeText={setHouseNumber} placeholder="House no." placeholderTextColor="#7d8da0" />
        </View>
        <TextInput style={styles.input} value={street} onChangeText={setStreet} placeholder="Street / purok" placeholderTextColor="#7d8da0" />
        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={barangay} onChangeText={setBarangay} placeholder="Barangay" placeholderTextColor="#7d8da0" />
          <TextInput style={[styles.input, styles.flexInput]} value={city} onChangeText={setCity} placeholder="City" placeholderTextColor="#7d8da0" />
        </View>
        <TextInput style={styles.input} value={province} onChangeText={setProvince} placeholder="Province" placeholderTextColor="#7d8da0" />
        <View style={styles.previewBox}>
          <Text style={styles.label}>Saved rescue address</Text>
          <Text style={styles.previewText}>{rescueAddress || 'Complete the address fields above.'}</Text>
        </View>
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Device user" />
        <Text style={styles.helperText}>Choose the member using this phone so HQ can see the correct device, battery, and last location.</Text>
        <View style={styles.choiceGrid}>
          {members.map((member: any) => (
            <Pressable
              key={member.member_id}
              style={[styles.choice, memberId === member.member_id && styles.choiceActive]}
              onPress={() => setMemberId(member.member_id)}
            >
              <Text style={[styles.choiceTitle, memberId === member.member_id && styles.choiceTextActive]}>{member.name}</Text>
              <Text style={[styles.choiceSub, memberId === member.member_id && styles.choiceTextActive]}>{member.relationship}</Text>
            </Pressable>
          ))}
        </View>

        <Text style={styles.label}>Relationship to family</Text>
        <View style={styles.choiceGrid}>
          {relationships.map((item) => (
            <Pressable
              key={item}
              style={[styles.smallChoice, relationship === item && styles.choiceActive]}
              onPress={() => setRelationship(item)}
            >
              <Text style={[styles.choiceTitle, relationship === item && styles.choiceTextActive]}>{item}</Text>
            </Pressable>
          ))}
        </View>
      </View>

      <Pressable style={styles.privacyRow} onPress={() => setPrivacyAccepted((value) => !value)}>
        <View style={[styles.checkBox, privacyAccepted && styles.checkBoxActive]}>
          {privacyAccepted ? <Ionicons name="checkmark" size={15} color="#fff" /> : null}
        </View>
        <Text style={styles.privacyText}>
          I agree that RESQPERATION may securely store this household geotag and exact address for disaster response, HQ monitoring, and assigned rescuer access only.
        </Text>
      </Pressable>

      <HouseholdButton
        label={saving ? 'Saving setup...' : 'Complete setup'}
        icon="checkmark-circle-outline"
        disabled={saving}
        onPress={handleSave}
      />
    </View>
  );
}

function buildRescueAddress(selectedAddress: string, locationLabel: string) {
  const cleanSelected = selectedAddress.trim();
  const cleanLabel = locationLabel.trim();

  if (cleanSelected && cleanLabel && !cleanLabel.toLowerCase().includes(cleanSelected.toLowerCase())) {
    return `${cleanSelected} - ${cleanLabel}`.slice(0, 255);
  }

  return (cleanSelected || cleanLabel).slice(0, 255);
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
