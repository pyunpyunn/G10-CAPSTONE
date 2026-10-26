import { useState } from 'react';
import { Image, Platform, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import * as ImagePicker from 'expo-image-picker';
import MapView, { Marker } from 'react-native-maps';
import { MobileLeafletMap } from '@/components/MobileLeafletMap.native';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { useHouseholdSetup } from '@/hooks/useHouseholdSetup';
import { HouseholdSetupAddressForm } from './HouseholdSetupAddressForm';
import { HouseholdBadge, HouseholdButton, HouseholdSection } from './HouseholdUI';

type SetupProps = {
  overview: any;
  deviceUuid: string;
  onComplete: (payload: any) => Promise<void>;
};

const defaultRegion = {
  latitude: 10.2875,
  longitude: 123.8854,
  latitudeDelta: 0.025,
  longitudeDelta: 0.025,
};

export function HouseholdSetupScreen({ overview, deviceUuid, onComplete }: SetupProps) {
  const user = overview.profile?.user || {};
  const member = (overview.members || []).find((item: any) => String(item.member_id) === String(user.member_id));
  const memberId = user.member_id || '';
  const relationship = member?.relationship || 'Member';
  const [photoUri, setPhotoUri] = useState('');
  const [locationMode, setLocationMode] = useState<'gps' | 'map'>('map');
  const { pin, address, details, setDetails, saving, findingAddress, geotagMessage, beginPinMove,
    applyPinnedCoordinate, requestGpsLocation, handleSave } = useHouseholdSetup({ overview, deviceUuid, onComplete, photoUri });

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

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <HouseholdBadge label="Required setup" tone="warning" />
        <Text style={styles.heroTitle}>Pin your household location</Text>
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Location" />
        <View style={styles.twoColumn}>
          <HouseholdButton label="Use GPS" icon="locate-outline" tone={locationMode === 'gps' ? 'primary' : 'light'} disabled={saving || findingAddress} onPress={() => { setLocationMode('gps'); void requestGpsLocation(); }} />
          <HouseholdButton label="Drag map pin" icon="location-outline" tone={locationMode === 'map' ? 'primary' : 'light'} disabled={saving || findingAddress} onPress={() => setLocationMode('map')} />
        </View>
        <Text style={styles.mapHint}>Place the blue pin over your household. The location is saved after you complete setup.</Text>
        {Platform.OS !== 'android' ? (
          <MapView
            style={styles.map}
            initialRegion={pin ? { ...pin, latitudeDelta: 0.018, longitudeDelta: 0.018 } : defaultRegion}
            onPress={(event) => applyPinnedCoordinate({ ...event.nativeEvent.coordinate, accuracy_m: null })}
          >
            {pin ? <Marker coordinate={pin} title="Household pin" draggable onDragEnd={(event) => applyPinnedCoordinate({ ...event.nativeEvent.coordinate, accuracy_m: null })} /> : null}
          </MapView>
        ) : (
          <MobileLeafletMap
            markers={[]}
            routes={[]}
            center={pin || defaultRegion}
            height={360}
            geotagPicker
            selectedPoint={pin}
            onPinMoving={() => { setLocationMode('map'); beginPinMove(); }}
            onMapPress={(coordinate) => { setLocationMode('map'); void applyPinnedCoordinate({ ...coordinate, accuracy_m: null }); }}
          />
        )}
        <Text style={styles.mapHint}>{geotagMessage}</Text>
      </View>

      <HouseholdSetupAddressForm address={address} details={details} setDetails={setDetails} disabled={saving} />

      <View style={styles.card}>
        <HouseholdSection title="Device user" />
        <Text style={styles.choiceTitle}>{member?.name || user.full_name || 'Member unavailable'}</Text>
        <Text style={styles.choiceSub}>Member ID: {memberId}</Text>
        <Text style={styles.choiceSub}>{relationship}</Text>

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
        disabled={saving || findingAddress}
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
