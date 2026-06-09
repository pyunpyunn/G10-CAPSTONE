import { useMemo, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import MapView, { Marker } from 'react-native-maps';
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

const defaultRegion = {
  latitude: 10.3157,
  longitude: 123.8854,
  latitudeDelta: 0.025,
  longitudeDelta: 0.025,
};

export function HouseholdSetupScreen({ overview, deviceUuid, onComplete }: SetupProps) {
  const household = overview.profile?.household || {};
  const members = overview.members || [];
  const [pin, setPin] = useState<any>(null);
  const [locationLabel, setLocationLabel] = useState<string>(household.address || '');
  const [houseNumber, setHouseNumber] = useState<string>('');
  const [unitNumber, setUnitNumber] = useState<string>('');
  const [street, setStreet] = useState<string>('');
  const [barangay, setBarangay] = useState<string>(household.barangay === 'Not recorded' ? '' : household.barangay || '');
  const [city, setCity] = useState<string>(household.city === 'Not recorded' ? '' : household.city || '');
  const [province, setProvince] = useState<string>(household.province === 'Not recorded' ? '' : household.province || '');
  const [memberId, setMemberId] = useState(members[0]?.member_id || '');
  const [relationship, setRelationship] = useState('Head of household');
  const [photoUri, setPhotoUri] = useState('');
  const [saving, setSaving] = useState(false);
  const [findingAddress, setFindingAddress] = useState(false);

  const selectedAddress = useMemo(() => {
    return [unitNumber, houseNumber, street, barangay, city, province].filter(Boolean).join(', ');
  }, [unitNumber, houseNumber, street, barangay, city, province]);

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
      setLocationLabel('Selected map location');
    } finally {
      setFindingAddress(false);
    }
  }

  async function useCurrentLocation() {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      Alert.alert('Location required', 'Allow location access or pin the household location on the map.');
      return;
    }

    const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    await applyPinnedCoordinate({
      latitude: current.coords.latitude,
      longitude: current.coords.longitude,
      accuracy_m: current.coords.accuracy,
    });
  }

  async function pickPhoto() {
    const result = await ImagePicker.launchImageLibraryAsync({
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.75,
    });

    if (!result.canceled) {
      setPhotoUri(result.assets[0]?.uri || '');
    }
  }

  async function handleSave() {
    if (!pin) {
      Alert.alert('Location required', 'Use your current location or tap the map to pin your household.');
      return;
    }

    if (!locationLabel.trim() && !selectedAddress.trim()) {
      Alert.alert('Address required', 'Confirm the household address before saving setup.');
      return;
    }

    if (!memberId) {
      Alert.alert('Member required', 'Select which family member is using this device.');
      return;
    }

    setSaving(true);

    try {
      await onComplete({
        latitude: pin.latitude,
        longitude: pin.longitude,
        accuracy_m: pin.accuracy_m,
        address_label: locationLabel.trim() || selectedAddress,
        house_number: houseNumber.trim(),
        unit_number: unitNumber.trim(),
        street: street.trim(),
        barangay: barangay.trim(),
        city: city.trim(),
        province: province.trim(),
        member_id: memberId,
        relationship_to_family: relationship,
        device_uuid: deviceUuid,
        device_name: 'Household mobile',
        platform: 'expo',
        photo_uri: photoUri || null,
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
        <MapView
          style={styles.map}
          initialRegion={pin ? { ...pin, latitudeDelta: 0.018, longitudeDelta: 0.018 } : defaultRegion}
          onPress={(event) => applyPinnedCoordinate({ ...event.nativeEvent.coordinate, accuracy_m: null })}
        >
          {pin ? <Marker coordinate={pin} title="Household pin" /> : null}
        </MapView>
        <Text style={styles.mapHint}>{findingAddress ? 'Finding address...' : pin ? 'Pin selected' : 'Use GPS or tap map'}</Text>
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
        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={unitNumber} onChangeText={setUnitNumber} placeholder="Unit" placeholderTextColor="#7d8da0" />
          <TextInput style={[styles.input, styles.flexInput]} value={houseNumber} onChangeText={setHouseNumber} placeholder="House no." placeholderTextColor="#7d8da0" />
        </View>
        <TextInput style={styles.input} value={street} onChangeText={setStreet} placeholder="Street / purok" placeholderTextColor="#7d8da0" />
        <View style={styles.twoColumn}>
          <TextInput style={[styles.input, styles.flexInput]} value={barangay} onChangeText={setBarangay} placeholder="Barangay" placeholderTextColor="#7d8da0" />
          <TextInput style={[styles.input, styles.flexInput]} value={city} onChangeText={setCity} placeholder="City" placeholderTextColor="#7d8da0" />
        </View>
        <TextInput style={styles.input} value={province} onChangeText={setProvince} placeholder="Province" placeholderTextColor="#7d8da0" />
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Device user" />
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

        <View style={styles.photoRow}>
          <View style={styles.photoPreview}>
            {photoUri ? <Image source={{ uri: photoUri }} style={styles.photo} /> : <Ionicons name="image-outline" size={26} color={palette.navMuted} />}
          </View>
          <View style={styles.photoActions}>
            <Text style={styles.photoTitle}>Family photo</Text>
            <HouseholdButton label={photoUri ? 'Change photo' : 'Upload photo'} icon="image-outline" tone="light" onPress={pickPhoto} />
          </View>
        </View>
      </View>

      <HouseholdButton
        label={saving ? 'Saving setup...' : 'Complete setup'}
        icon="checkmark-circle-outline"
        disabled={saving}
        onPress={handleSave}
      />
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
  map: {
    height: 280,
    borderRadius: radius.md,
  },
  mapHint: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
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
  photoRow: {
    flexDirection: 'row',
    gap: spacing.md,
    alignItems: 'center',
  },
  photoPreview: {
    width: 82,
    height: 82,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    backgroundColor: palette.secondary,
    overflow: 'hidden',
  },
  photo: {
    width: '100%',
    height: '100%',
  },
  photoActions: {
    flex: 1,
    gap: 6,
  },
  photoTitle: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
});
