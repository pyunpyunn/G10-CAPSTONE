import { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import Svg, { Circle, Path } from 'react-native-svg';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { storageUrl } from '@/api/client';
import { removeProfilePhoto, uploadProfilePhoto } from '@/api/profilePhoto';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { reverseGeocodeAddress } from '@/utils/geocoding';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type ProfileProps = {
  overview: any;
  onUpdateGeotag: (payload: any) => Promise<void>;
  onLogout: () => void;
};

export function HouseholdProfileScreen({
  overview,
  onUpdateGeotag,
  onLogout,
}: ProfileProps) {
  const household = overview.profile?.household || {};
  const user = overview.profile?.user || {};
  const members = useMemo(() => overview.members || [], [overview.members]);
  const geotag = overview.geotag || null;
  const currentStatus = overview.current_status || null;
  const selectedMemberId = String(user.member_id || members[0]?.member_id || '');
  const [savingGeotag, setSavingGeotag] = useState(false);
  const [profilePhoto, setProfilePhoto] = useState(user.profile_photo || null);
  const [pendingPhoto, setPendingPhoto] = useState<any>(null);
  const [savingProfilePhoto, setSavingProfilePhoto] = useState(false);

  useEffect(() => {
    setProfilePhoto(user.profile_photo || null);
  }, [user.profile_photo]);

  const selectedMember = useMemo(() => {
    return members.find((member: any) => String(member.member_id) === String(selectedMemberId)) || members[0] || null;
  }, [members, selectedMemberId]);

  const householdMembers = useMemo(() => {
    return members.filter((member: any) => String(member.member_id) !== String(user.member_id));
  }, [members, user.member_id]);

  const deviceOwnerName = user.full_name || user.username || 'Household user';
  const householdName = household.household_name || 'Household';
  const householdIdentifier = household.household_id || user.username || 'No household ID';
  const memberCount = members.length || Number(household.member_count || 0);
  const hasGeotag = Boolean(geotag?.latitude && geotag?.longitude);

  async function handleChangeProfilePhoto() {
    let result;
    try {
      result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.85,
      });
    } catch {
      Alert.alert('Unable to open photos', 'Check photo library access and try again.');
      return;
    }

    if (result.canceled || !result.assets[0]) return;

    const photo = result.assets[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (photo.mimeType && !allowedTypes.includes(photo.mimeType)) {
      Alert.alert('Unsupported image', 'Choose a JPG, PNG, or WebP image.');
      return;
    }
    if (photo.fileSize && photo.fileSize > 5 * 1024 * 1024) {
      Alert.alert('Image is too large', 'Choose an image smaller than 5 MB.');
      return;
    }

    setPendingPhoto(photo);
  }

  async function handleSaveProfilePhoto() {
    if (!pendingPhoto) return;

    setSavingProfilePhoto(true);
    try {
      const response = await uploadProfilePhoto(pendingPhoto);
      setProfilePhoto(response.profile_photo);
      setPendingPhoto(null);
      Alert.alert('Profile picture updated', 'Your picture is saved to your account.');
    } catch (error: any) {
      Alert.alert('Unable to update picture', error?.response?.data?.message || error?.userMessage || 'Please try again.');
    } finally {
      setSavingProfilePhoto(false);
    }
  }

  function handleRemoveProfilePhoto() {
    Alert.alert('Remove profile picture?', 'Your default initials avatar will be shown.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Remove',
        style: 'destructive',
        onPress: async () => {
          setSavingProfilePhoto(true);
          try {
            await removeProfilePhoto();
            setProfilePhoto(null);
            setPendingPhoto(null);
            Alert.alert('Profile picture removed', 'Your default initials avatar is restored.');
          } catch (error: any) {
            Alert.alert('Unable to remove picture', error?.response?.data?.message || error?.userMessage || 'Please try again.');
          } finally {
            setSavingProfilePhoto(false);
          }
        },
      },
    ]);
  }

  async function handleUpdateGeotag() {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      Alert.alert('Location required', 'Allow location access to update your household geotag.');
      return;
    }

    setSavingGeotag(true);

    try {
      const location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const address = await reverseGeocodeAddress(location.coords.latitude, location.coords.longitude);

      await onUpdateGeotag({
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        accuracy_m: location.coords.accuracy,
        address_label: address.label || household.address || 'Updated household geotag',
        member_id: selectedMemberId || undefined,
        relationship_to_family: selectedMember?.relationship || 'Household member',
      });

      Alert.alert('Geotag updated', 'Your household geotag was saved to the shared database.');
    } catch {
      // Parent screen already shows the API error message.
    } finally {
      setSavingGeotag(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <View style={styles.heroTop}>
          <View style={styles.avatar}>
            <HouseholdAccountIcon />
          </View>
          <View style={styles.heroText}>
            <Text style={styles.role}>Household account</Text>
            <Text style={styles.name}>{householdName}</Text>
            <Text style={styles.meta}>{householdIdentifier}</Text>
          </View>
        </View>

      </View>

      <View style={styles.card}>
        <HouseholdSection title="User Information" />

        <View style={styles.devicePanel}>
          <View style={styles.profilePhotoColumn}>
            <Pressable
              style={styles.profilePhotoTouchable}
              accessibilityRole="button"
              accessibilityLabel={profilePhoto ? 'Change profile picture' : 'Add profile picture'}
              accessibilityHint="Opens the photo library"
              disabled={savingProfilePhoto}
              onPress={handleChangeProfilePhoto}
            >
              <View style={styles.memberAvatar}>
                {pendingPhoto ? (
                  <Image source={{ uri: pendingPhoto.uri }} style={styles.profilePhotoImage} />
                ) : profilePhoto ? (
                  <Image source={{ uri: storageUrl(profilePhoto) }} style={styles.profilePhotoImage} />
                ) : (
                  <Text style={styles.memberAvatarText}>{initials(deviceOwnerName)}</Text>
                )}
              </View>
              <View style={styles.profilePhotoEditBadge}>
                <Ionicons name="camera-outline" size={13} color={palette.navActive} />
              </View>
            </Pressable>
            {savingProfilePhoto ? <Text style={styles.photoSavingText}>Saving...</Text> : null}
          </View>
          <View style={styles.panelText}>
            <Text style={styles.panelTitle}>{deviceOwnerName}</Text>
            <View style={styles.userRelationshipRow}>
              <Text style={styles.panelLabel}>User:</Text>
              <Text style={[styles.panelMeta, styles.userRelationshipText]}>{memberRelationship(user)}</Text>
            </View>
          </View>
          <View style={styles.statusCell}>
            <HouseholdBadge
              label={currentStatus?.status_label || 'Unchecked'}
              tone={currentStatus?.status_key || 'neutral'}
            />
          </View>
        </View>
        <View style={styles.profilePhotoControls}>
          {pendingPhoto ? (
            <>
              <Pressable
                style={[styles.profilePhotoAction, styles.profilePhotoActionPrimary]}
                accessibilityRole="button"
                disabled={savingProfilePhoto}
                onPress={handleSaveProfilePhoto}
              >
                <Ionicons name="save-outline" size={16} color="#fff" />
                <Text style={styles.profilePhotoActionPrimaryText}>{savingProfilePhoto ? 'Saving...' : 'Save picture'}</Text>
              </Pressable>
              <Pressable
                style={styles.profilePhotoAction}
                accessibilityRole="button"
                disabled={savingProfilePhoto}
                onPress={() => setPendingPhoto(null)}
              >
                <Ionicons name="arrow-back-outline" size={16} color={palette.nav} />
                <Text style={styles.profilePhotoActionText}>Cancel</Text>
              </Pressable>
            </>
          ) : (
            <>
              <Pressable
                style={styles.profilePhotoAction}
                accessibilityRole="button"
                disabled={savingProfilePhoto}
                onPress={handleChangeProfilePhoto}
              >
                <Ionicons name={profilePhoto ? 'camera-outline' : 'person-add-outline'} size={16} color={palette.nav} />
                <Text style={styles.profilePhotoActionText}>{profilePhoto ? 'Change picture' : 'Add profile picture'}</Text>
              </Pressable>
              {profilePhoto ? (
                <Pressable
                  style={[styles.profilePhotoAction, styles.profilePhotoActionDanger]}
                  accessibilityRole="button"
                  disabled={savingProfilePhoto}
                  onPress={handleRemoveProfilePhoto}
                >
                  <Ionicons name="trash-outline" size={16} color={palette.brandRed} />
                  <Text style={styles.profilePhotoActionDangerText}>Delete picture</Text>
                </Pressable>
              ) : null}
            </>
          )}
        </View>

        {householdMembers.length ? (
          <View style={styles.memberProfilePanel}>
            <Text style={styles.panelLabel}>Member profile</Text>
            {householdMembers.map((member: any) => {
              const memberKey = memberProfileKey(member);
              const memberName = member.name || `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Household member';

              return (
                <View key={memberKey} style={styles.memberRow}>
                  <View style={styles.memberAvatar}>
                    <Text style={styles.memberAvatarText}>{initials(memberName)}</Text>
                  </View>
                  <View style={styles.panelText}>
                    <Text style={styles.panelTitle}>{memberName}</Text>
                    <Text style={styles.panelMeta}>{memberRelationship(member)}</Text>
                  </View>
                  <View style={styles.statusCell}>
                    <HouseholdBadge
                      label={member.current_status?.status_label || 'Unchecked'}
                      tone={member.current_status?.status_key || 'neutral'}
                    />
                  </View>
                </View>
              );
            })}
          </View>
        ) : null}
      </View>

      <View style={styles.card}>
        <HouseholdSection
          title="Household geotag"
          action={<HouseholdBadge label={hasGeotag ? 'Saved' : 'Missing'} tone={hasGeotag ? 'safe' : 'warning'} />}
        />
        {geotag ? (
          <>
            <View style={styles.locationPanel}>
              <View style={styles.locationIcon}>
                <Ionicons name="location-outline" size={23} color={palette.safe} />
              </View>
              <View style={styles.panelText}>
                <Text style={styles.panelLabel}>Saved address</Text>
                <Text style={styles.panelTitle}>{geotag.location_label || 'Household geotag'}</Text>
                <Text style={styles.panelMeta}>{coordinatesLabel(geotag)}</Text>
              </View>
            </View>
            <InfoRow icon="time-outline" label="Updated" value={geotag.updated_label || 'Not recorded'} />
          </>
        ) : (
          <HouseholdEmpty icon="location-outline" title="No geotag saved yet" />
        )}
        <HouseholdButton
          label={savingGeotag ? 'Updating geotag...' : 'Update geotag from GPS'}
          icon="locate-outline"
          tone="light"
          disabled={savingGeotag}
          onPress={handleUpdateGeotag}
        />
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Household details" />
        <InfoRow icon="business-outline" label="Barangay" value={household.barangay || 'Not recorded'} />
        <InfoRow icon="home-outline" label="Address" value={household.address || 'Not recorded'} />
        <InfoRow icon="people-outline" label="Members" value={String(memberCount)} />
        <InfoRow icon="call-outline" label="Contact" value={household.contact_number || user.contact_number || 'Not recorded'} />
      </View>

      <HouseholdButton label="Log out" icon="log-out-outline" tone="danger" onPress={onLogout} />
    </View>
  );
}

function HouseholdAccountIcon() {
  return (
    <Svg width={54} height={54} viewBox="0 0 64 64" fill="none">
      <Path
        d="M8 29L32 9L56 29M14 24V55H50V24M43 18V12H49V23"
        stroke={palette.nav}
        strokeWidth={2.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Circle cx={32} cy={31} r={4} stroke={palette.nav} strokeWidth={2.6} />
      <Circle cx={22} cy={36} r={3.2} stroke={palette.nav} strokeWidth={2.4} />
      <Circle cx={42} cy={36} r={3.2} stroke={palette.nav} strokeWidth={2.4} />
      <Path
        d="M23 51V47C23 42.6 26.6 39 31 39H33C37.4 39 41 42.6 41 47V51M15.5 51V48.5C15.5 45.2 18.2 42.5 21.5 42.5H23M41 42.5H42.5C45.8 42.5 48.5 45.2 48.5 48.5V51"
        stroke={palette.nav}
        strokeWidth={2.6}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function InfoRow({ icon, label, value }: { icon?: keyof typeof Ionicons.glyphMap; label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <View style={styles.infoLabelWrap}>
        {icon ? <Ionicons name={icon} size={16} color={palette.textSoft} /> : null}
        <Text style={styles.infoLabel}>{label}</Text>
      </View>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

function initials(value: string) {
  return value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');
}

function memberRelationship(member: any) {
  return member?.relationship || member?.relation || 'Member';
}

function memberProfileKey(member: any) {
  return String(member.member_id || member.name || member.full_name || `${member.first_name || 'member'}-${member.last_name || ''}`);
}

function coordinatesLabel(geotag: any) {
  if (!geotag?.latitude || !geotag?.longitude) {
    return 'Coordinates not recorded';
  }

  return `${Number(geotag.latitude).toFixed(6)}, ${Number(geotag.longitude).toFixed(6)}`;
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  hero: {
    gap: spacing.md,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.nav,
    ...shadow,
  },
  heroTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  avatar: {
    width: 62,
    height: 62,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#ffffff66',
    borderRadius: 31,
    backgroundColor: '#fff',
  },
  heroText: {
    flex: 1,
    minWidth: 0,
  },
  role: {
    color: palette.navMuted,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  name: {
    marginTop: 3,
    color: '#fff',
    fontSize: 22,
    fontWeight: '900',
  },
  meta: {
    marginTop: 3,
    color: palette.navText,
    fontSize: 12,
    fontWeight: '800',
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  devicePanel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  memberProfilePanel: {
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.sm,
    paddingVertical: spacing.sm,
  },
  memberAvatar: {
    width: 48,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 24,
    backgroundColor: palette.secondary,
    borderWidth: 1,
    borderColor: palette.border,
  },
  memberAvatarText: {
    color: palette.navActive,
    fontSize: 16,
    fontWeight: '900',
  },
  profilePhotoColumn: {
    alignItems: 'center',
    gap: 2,
  },
  profilePhotoImage: {
    width: 46,
    height: 46,
    borderRadius: 23,
  },
  profilePhotoTouchable: {
    position: 'relative',
  },
  profilePhotoEditBadge: {
    position: 'absolute',
    right: -3,
    bottom: -3,
    width: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.card,
    borderRadius: 11,
    backgroundColor: palette.secondary,
  },
  profilePhotoControls: {
    flexDirection: 'row',
    gap: spacing.sm,
    flexWrap: 'wrap',
  },
  profilePhotoAction: {
    minHeight: 40,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xs,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.card,
  },
  profilePhotoActionText: {
    color: palette.nav,
    fontSize: 12,
    fontWeight: '800',
  },
  profilePhotoActionPrimary: {
    borderColor: palette.brandRed,
    backgroundColor: palette.brandRed,
  },
  profilePhotoActionPrimaryText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },
  profilePhotoActionDanger: {
    borderColor: `${palette.brandRed}55`,
    backgroundColor: `${palette.brandRed}0d`,
  },
  profilePhotoActionDangerText: {
    color: palette.brandRed,
    fontSize: 12,
    fontWeight: '800',
  },
  photoSavingText: {
    color: palette.textSoft,
    fontSize: 10,
    fontWeight: '700',
  },
  panelText: {
    flex: 1,
    minWidth: 0,
  },
  statusCell: {
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  panelLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  panelTitle: {
    marginTop: 3,
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
  },
  panelMeta: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  userRelationshipRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginTop: 3,
  },
  userRelationshipText: {
    marginTop: 0,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.sm,
  },
  infoLabelWrap: {
    flex: 0.9,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  infoLabel: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  infoValue: {
    flex: 1,
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
    textAlign: 'right',
  },
  locationPanel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderWidth: 1,
    borderColor: '#3a7d5733',
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: '#f2fbf6',
  },
  locationIcon: {
    width: 48,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: '#3a7d5718',
  },
});
