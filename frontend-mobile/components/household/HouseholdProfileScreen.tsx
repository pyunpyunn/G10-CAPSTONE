import { useMemo, useRef, useState } from 'react';
import { Alert, Image, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import Svg, { Circle, Path } from 'react-native-svg';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { storageUrl } from '@/api/client';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdSection, HouseholdStatusIcon, statusColor } from './HouseholdUI';

type ProfileProps = {
  overview: any;
  onUpdateGeotag: (payload: any) => Promise<any>;
  onUploadPhoto: (payload: FormData) => Promise<void>;
  onRemovePhoto: () => Promise<void>;
  onUploadMemberPhoto: (memberId: string, payload: FormData) => Promise<void>;
  onRemoveMemberPhoto: (memberId: string) => Promise<void>;
  onLogout: () => void;
};

type PendingPhoto = {
  uri: string;
  name: string;
  type: string;
};

export function HouseholdProfileScreen({
  overview,
  onUpdateGeotag,
  onUploadPhoto,
  onRemovePhoto,
  onUploadMemberPhoto,
  onRemoveMemberPhoto,
  onLogout,
}: ProfileProps) {
  const household = overview.profile?.household || {};
  const user = overview.profile?.user || {};
  const members = useMemo(() => overview.members || [], [overview.members]);
  const geotag = overview.geotag || null;
  const currentStatus = overview.current_status || null;
  const selectedMemberId = String(user.member_id || members[0]?.member_id || '');
  const [geotagMapVisible, setGeotagMapVisible] = useState(false);
  const [savingGeotag, setSavingGeotag] = useState(false);
  const [geotagMessage, setGeotagMessage] = useState(
    household.sitio_id
      ? `Saved in ${household.sitio_name || 'your registered sitio'}.`
      : 'Sitio will be identified automatically when verified boundary data is available.'
  );
  const geotagVersion = useRef(0);
  const geotagSaveQueue = useRef<Promise<void>>(Promise.resolve());
  const [photoBusy, setPhotoBusy] = useState(false);
  const [pendingPhoto, setPendingPhoto] = useState<PendingPhoto | null>(null);
  const [showPhotoPreview, setShowPhotoPreview] = useState(false);
  const [showPhotoMenu, setShowPhotoMenu] = useState(false);
  const [photoTarget, setPhotoTarget] = useState<any>(null);
  const [showPhotoView, setShowPhotoView] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showNoPermission, setShowNoPermission] = useState(false);
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
  const profilePhotoUrl = profileImageUrl(user.profile_photo_url);
  const ownPhotoTarget = members.find((member: any) => String(member.member_id) === String(user.member_id)) || user;
  const activePhotoTarget = photoTarget
    ? (String(photoTarget.member_id) === String(user.member_id)
        ? user
        : members.find((member: any) => String(member.member_id) === String(photoTarget.member_id)) || photoTarget)
    : ownPhotoTarget;
  const activePhotoMemberId = String(activePhotoTarget.member_id || user.member_id || '');
  const canChangePhoto = user.can_report_for_household === true;

  async function handlePickPhoto(target: any = activePhotoTarget) {
    if (target !== activePhotoTarget) {
      setPhotoTarget(target);
    }

    if (!canChangePhoto) {
      setShowPhotoMenu(false);
      setShowNoPermission(true);
      return;
    }

    setShowPhotoMenu(false);
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.9,
    });

    const asset = result.canceled ? null : result.assets[0];
    if (!asset?.uri) return;

    setPendingPhoto({
      uri: asset.uri,
      name: asset.fileName || 'profile-photo.jpg',
      type: asset.mimeType || 'image/jpeg',
    });
    setShowPhotoPreview(true);
  }

  async function handleConfirmPhoto() {
    if (!pendingPhoto) return;

    setPhotoBusy(true);
    try {
      const formData = new FormData();
      formData.append('photo', {
        uri: pendingPhoto.uri,
        name: pendingPhoto.name,
        type: pendingPhoto.type,
      } as any);
      if (activePhotoMemberId === String(user.member_id || '')) {
        await onUploadPhoto(formData);
      } else {
        await onUploadMemberPhoto(activePhotoMemberId, formData);
      }
      setPendingPhoto(null);
      setShowPhotoPreview(false);
      Alert.alert('Profile picture updated', 'Your picture was saved to your account.');
    } catch (error: any) {
      Alert.alert('Unable to update profile picture', error?.response?.data?.message || 'Please try again.');
    } finally {
      setPhotoBusy(false);
    }
  }

  async function handleRemovePhoto() {
    if (!canChangePhoto) {
      setShowDeleteConfirm(false);
      setShowNoPermission(true);
      return;
    }

    setPhotoBusy(true);
    try {
      if (activePhotoMemberId === String(user.member_id || '')) {
        await onRemovePhoto();
      } else {
        await onRemoveMemberPhoto(activePhotoMemberId);
      }
      setShowDeleteConfirm(false);
      setShowPhotoMenu(false);
      Alert.alert('Profile picture removed', 'Your default avatar is now shown.');
    } catch (error: any) {
      Alert.alert('Unable to remove profile picture', error?.response?.data?.message || 'Please try again.');
    } finally {
      setPhotoBusy(false);
    }
  }

  function handleAvatarPress(target: any = user) {
    setPhotoTarget(target);
    if (!canChangePhoto) {
      setShowPhotoMenu(false);
      setShowNoPermission(true);
      return;
    }

    if (!profileImageUrl(target.profile_photo_url)) {
      void handlePickPhoto(target);
      return;
    }

    setShowPhotoMenu((current) => !current);
  }

  function handleViewPhoto() {
    setShowPhotoMenu(false);
    setShowPhotoView(true);
  }

  function saveGeotagPoint(point: { latitude: number; longitude: number; accuracy_m?: number | null }) {
    const version = ++geotagVersion.current;
    setSavingGeotag(true);
    setGeotagMessage('Saving your household coordinates...');

    const save = geotagSaveQueue.current.catch(() => undefined).then(async () => {
      if (version !== geotagVersion.current) return;
      const result = await onUpdateGeotag({
        ...point,
        address_label: 'Household geotag in Mambaling',
        member_id: selectedMemberId || undefined,
        relationship_to_family: selectedMember?.relationship || 'Household member',
      });
      if (version !== geotagVersion.current) return;
      const classification = result?.data?.geographic_classification;
      const unresolvedPurok = classification?.status === 'purok_boundaries_missing'
        ? 'The location was saved, but verified Mambaling sitio/purok boundaries are not configured, so sitio_id could not be assigned automatically.'
        : classification?.status === 'no_boundary_match'
          ? 'The location was saved, but no verified Mambaling sitio boundary contains this pin.'
          : classification?.status === 'ambiguous_boundary'
            ? 'The location was saved, but the pin falls on an overlapping or shared boundary.'
            : 'The location was saved, but sitio_id could not be assigned automatically.';
      setGeotagMessage(classification?.status === 'matched'
        ? `Saved in ${classification.sitio_name} - ${classification.purok_name}.`
        : unresolvedPurok);
    }).catch((error: any) => {
      if (version === geotagVersion.current) {
        setGeotagMessage(error?.response?.data?.message || 'Could not save the location. Choose a point inside Mambaling and try again.');
      }
    }).finally(() => {
      if (version === geotagVersion.current) setSavingGeotag(false);
    });

    geotagSaveQueue.current = save;
    return save;
  }

  function handleMapPoint(point: { latitude: number; longitude: number }) {
    void saveGeotagPoint(point);
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
      await saveGeotagPoint({
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        accuracy_m: location.coords.accuracy,
      });
    } catch {
      Alert.alert('Unable to update geotag', 'Your location could not be saved. Choose a point inside Mambaling and try again.');
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

      <View style={[styles.card, styles.userInfoCard]}>
        <Text style={styles.userInfoTitle}>User information</Text>

        <View style={styles.devicePanel}>
          <View style={[
            styles.avatarAnchor,
            showPhotoMenu && String(photoTarget?.member_id) === String(user.member_id) && styles.avatarAnchorActive,
          ]}>
            <Pressable
              style={({ pressed }) => [
                styles.memberAvatar,
                showPhotoMenu && String(photoTarget?.member_id) === String(user.member_id) && styles.memberAvatarSelected,
                pressed && styles.memberAvatarPressed,
              ]}
              onPress={() => handleAvatarPress(user)}
              accessibilityRole="button"
              accessibilityLabel={profilePhotoUrl ? 'Open profile picture options' : 'Add profile picture'}
            >
              {profilePhotoUrl ? <Image source={{ uri: profilePhotoUrl }} style={styles.memberAvatarImage} /> : <Text style={styles.memberAvatarText}>{initials(deviceOwnerName)}</Text>}
            </Pressable>
            {showPhotoMenu && String(photoTarget?.member_id) === String(user.member_id) ? (
              <View style={styles.photoMenu}>
                <Pressable style={styles.photoMenuItem} onPress={handleViewPhoto}>
                  <Ionicons name="eye-outline" size={17} color={palette.nav} />
                  <Text style={styles.photoMenuText}>View</Text>
                </Pressable>
                <Pressable style={styles.photoMenuItem} onPress={() => void handlePickPhoto()}>
                  <Ionicons name="image-outline" size={17} color={palette.nav} />
                  <Text style={styles.photoMenuText}>Update Photo</Text>
                </Pressable>
                <Pressable style={styles.photoMenuItem} onPress={() => { setShowPhotoMenu(false); setShowDeleteConfirm(true); }}>
                  <Ionicons name="trash-outline" size={17} color={palette.brandRed} />
                  <Text style={[styles.photoMenuText, styles.photoMenuDanger]}>Delete Photo</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
          <View style={styles.panelText}>
            <Text style={styles.panelTitle}>{deviceOwnerName}</Text>
            <View style={styles.userRelationshipRow}>
              <Text style={styles.panelLabel}>User:</Text>
              <Text style={[styles.panelMeta, styles.userRelationshipText]}>{memberRelationship(user)}</Text>
            </View>
          </View>
          <View style={styles.statusCell}>
            <ProfileStatusBadge
              label={currentStatus?.status_label || 'Unchecked'}
              tone={currentStatus?.status_key || 'neutral'}
            />
          </View>
        </View>

        {householdMembers.length ? (
          <View style={styles.memberProfilePanel}>
            <Text style={styles.profileSectionLabel}>Member profile</Text>
            {householdMembers.map((member: any, index: number) => {
              const memberKey = memberProfileKey(member);
              const memberName = member.name || `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Household member';

              return (
                <View
                  key={memberKey}
                  style={[
                    styles.memberRow,
                    index === householdMembers.length - 1 && styles.memberRowLast,
                    showPhotoMenu && String(photoTarget?.member_id) === String(member.member_id) && styles.memberRowActive,
                  ]}
                >
                  <View style={[
                    styles.avatarAnchor,
                    showPhotoMenu && String(photoTarget?.member_id) === String(member.member_id) && styles.avatarAnchorActive,
                  ]}>
                    <Pressable
                      style={({ pressed }) => [
                        styles.memberAvatar,
                        showPhotoMenu && String(photoTarget?.member_id) === String(member.member_id) && styles.memberAvatarSelected,
                        pressed && styles.memberAvatarPressed,
                      ]}
                      onPress={() => handleAvatarPress(member)}
                      accessibilityRole="button"
                      accessibilityLabel={member.profile_photo_url ? `Open photo options for ${memberName}` : `Add photo for ${memberName}`}
                    >
                      {profileImageUrl(member.profile_photo_url) ? <Image source={{ uri: profileImageUrl(member.profile_photo_url) }} style={styles.memberAvatarImage} /> : <Text style={styles.memberAvatarText}>{initials(memberName)}</Text>}
                    </Pressable>
                    {showPhotoMenu && String(photoTarget?.member_id) === String(member.member_id) ? (
                      <View style={styles.photoMenu}>
                        <Pressable style={styles.photoMenuItem} onPress={handleViewPhoto}>
                          <Ionicons name="eye-outline" size={17} color={palette.nav} />
                          <Text style={styles.photoMenuText}>View</Text>
                        </Pressable>
                        <Pressable style={styles.photoMenuItem} onPress={() => void handlePickPhoto(member)}>
                          <Ionicons name="image-outline" size={17} color={palette.nav} />
                          <Text style={styles.photoMenuText}>Update Photo</Text>
                        </Pressable>
                        <Pressable style={styles.photoMenuItem} onPress={() => { setShowPhotoMenu(false); setShowDeleteConfirm(true); }}>
                          <Ionicons name="trash-outline" size={17} color={palette.brandRed} />
                          <Text style={[styles.photoMenuText, styles.photoMenuDanger]}>Delete Photo</Text>
                        </Pressable>
                      </View>
                    ) : null}
                  </View>
                  <View style={styles.panelText}>
                    <Text style={styles.panelTitle}>{memberName}</Text>
                    <Text style={styles.panelMeta}>{memberRelationship(member)}</Text>
                  </View>
                  <View style={styles.statusCell}>
                    <ProfileStatusBadge
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

      {!profilePhotoUrl ? (
        <View style={styles.card}>
          <HouseholdSection title="Profile picture" />
          <Text style={styles.panelMeta}>Tap the profile icon above to add a JPEG, PNG, or WebP image up to 5 MB.</Text>
        </View>
      ) : null}

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
        <View style={styles.mapActions}>
          <View style={styles.gpsButton}>
            <HouseholdButton
              label={savingGeotag ? 'Saving geotag...' : 'Update geotag from GPS'}
              icon="locate-outline"
              tone="light"
              disabled={savingGeotag}
              onPress={handleUpdateGeotag}
            />
          </View>
          <Pressable
            accessibilityRole="button"
            accessibilityState={{ expanded: geotagMapVisible }}
            style={[styles.mapOptionButton, geotagMapVisible && styles.mapOptionButtonActive]}
            disabled={savingGeotag}
            onPress={() => setGeotagMapVisible((visible) => !visible)}
          >
            <Ionicons name="map-outline" size={18} color={geotagMapVisible ? '#fff' : palette.nav} />
            <Text style={[styles.mapOptionText, geotagMapVisible && styles.mapOptionTextActive]}>
              {geotagMapVisible ? 'Close map' : 'Choose on map'}
            </Text>
          </Pressable>
        </View>
        {geotagMapVisible ? (
          <MobileLeafletMap
            markers={[]}
            routes={[]}
            geotagPicker
            geotagMode="center"
            onMapPress={handleMapPoint}
            onPinMoving={() => setGeotagMessage('Move the map until the center pin is over your home, then stop to save.')}
            height={300}
          />
        ) : null}
        <Text style={styles.geotagMessage} accessibilityLiveRegion="polite">{geotagMessage}</Text>
      </View>

      <View style={styles.card}>
        <HouseholdSection title="Household details" />
        <InfoRow icon="business-outline" label="Barangay" value={household.barangay || 'Not recorded'} />
        <InfoRow icon="location-outline" label="Sitio" value={household.sitio_name || 'Not recorded'} />
        <InfoRow icon="home-outline" label="Address" value={household.address || 'Not recorded'} />
        <InfoRow icon="people-outline" label="Members" value={String(memberCount)} />
        <InfoRow icon="call-outline" label="Contact" value={household.contact_number || user.contact_number || 'Not recorded'} />
      </View>

      <HouseholdButton label="Log out" icon="log-out-outline" tone="danger" onPress={onLogout} />

      <Modal visible={showPhotoPreview} transparent animationType="fade" onRequestClose={() => !photoBusy && setShowPhotoPreview(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Preview profile picture</Text>
            {pendingPhoto ? <Image source={{ uri: pendingPhoto.uri }} style={styles.previewImage} /> : null}
            <Text style={styles.modalNote}>Save this image as your profile picture?</Text>
            <View style={styles.modalActions}>
              <HouseholdButton label="Cancel" tone="gray" disabled={photoBusy} onPress={() => setShowPhotoPreview(false)} />
              <HouseholdButton label={photoBusy ? 'Saving...' : 'Confirm and save'} disabled={photoBusy} onPress={() => void handleConfirmPhoto()} />
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={showPhotoView} transparent animationType="fade" onRequestClose={() => setShowPhotoView(false)}>
        <View style={styles.photoViewBackdrop}>
          <Pressable style={styles.photoViewClose} onPress={() => setShowPhotoView(false)} accessibilityRole="button" accessibilityLabel="Close profile photo">
            <Ionicons name="close" size={28} color="#fff" />
          </Pressable>
          {profileImageUrl(activePhotoTarget.profile_photo_url) ? <Image source={{ uri: profileImageUrl(activePhotoTarget.profile_photo_url) }} style={styles.photoViewImage} resizeMode="contain" /> : null}
        </View>
      </Modal>

      <Modal visible={showDeleteConfirm} transparent animationType="fade" onRequestClose={() => !photoBusy && setShowDeleteConfirm(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Delete Photo</Text>
            <Text style={styles.modalNote}>Are you sure you want to remove your profile picture?</Text>
            <View style={styles.modalActions}>
              <HouseholdButton label="Cancel" tone="gray" disabled={photoBusy} onPress={() => setShowDeleteConfirm(false)} />
              <HouseholdButton label={photoBusy ? 'Deleting...' : 'Delete Photo'} tone="danger" disabled={photoBusy} onPress={() => void handleRemovePhoto()} />
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={showNoPermission} transparent animationType="fade" onRequestClose={() => setShowNoPermission(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Ionicons name="lock-closed-outline" size={30} color={palette.brandRed} />
            <Text style={styles.modalTitle}>No Permission</Text>
            <Text style={styles.modalNote}>You do not have the required permissions to change this profile picture.</Text>
            <HouseholdButton label="Close" tone="gray" onPress={() => setShowNoPermission(false)} />
          </View>
        </View>
      </Modal>
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

function ProfileStatusBadge({ label, tone }: { label: string; tone: string }) {
  const color = statusColor(tone);

  return (
    <View style={[styles.profileStatusBadge, { borderColor: `${color}35`, backgroundColor: `${color}12` }]}>
      <HouseholdStatusIcon tone={tone} size={12} color={color} />
      <Text style={[styles.profileStatusText, { color }]}>{label}</Text>
    </View>
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

function profileImageUrl(value: unknown): string {
  if (!value) {
    return '';
  }

  const photo = String(value);
  const storageIndex = photo.indexOf('/storage/');

  if (storageIndex >= 0) {
    return storageUrl(photo.slice(storageIndex + '/storage/'.length));
  }

  return /^https?:\/\//i.test(photo) ? photo : storageUrl(photo);
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
  avatarImage: {
    width: 60,
    height: 60,
    borderRadius: 30,
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
  userInfoCard: {
    gap: 0,
    padding: spacing.md,
    zIndex: 20,
    elevation: 20,
  },
  userInfoTitle: {
    marginBottom: spacing.xs,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  devicePanel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
    paddingVertical: spacing.sm,
  },
  memberProfilePanel: {
    paddingTop: spacing.sm,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    minHeight: 64,
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
    paddingVertical: spacing.xs,
  },
  memberRowLast: {
    borderBottomWidth: 0,
  },
  memberRowActive: {
    zIndex: 30,
    elevation: 30,
  },
  memberRowPressed: {
    backgroundColor: '#f2f6fa',
  },
  memberAvatar: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    backgroundColor: palette.secondary,
  },
  memberAvatarPressed: {
    opacity: 0.72,
    transform: [{ scale: 0.92 }],
    backgroundColor: '#dbe7f3',
  },
  memberAvatarSelected: {
    borderWidth: 2,
    borderColor: palette.navActive,
    backgroundColor: '#e8f1fb',
  },
  avatarAnchor: {
    position: 'relative',
    zIndex: 5,
  },
  avatarAnchorActive: {
    zIndex: 50,
    elevation: 50,
  },
  memberAvatarText: {
    color: palette.brandRed,
    fontSize: 12,
    fontWeight: '800',
  },
  memberAvatarImage: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  photoMenu: {
    position: 'absolute',
    top: 44,
    left: 0,
    zIndex: 20,
    width: 170,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingVertical: 4,
    backgroundColor: palette.card,
    shadowColor: '#000',
    shadowOpacity: 0.16,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 3 },
    elevation: 8,
  },
  photoMenuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
  },
  photoMenuText: {
    color: palette.nav,
    fontSize: 12,
    fontWeight: '800',
  },
  photoMenuDanger: {
    color: palette.brandRed,
  },
  modalBackdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.lg,
    backgroundColor: '#0b1728aa',
  },
  photoViewBackdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#000000ee',
  },
  photoViewClose: {
    position: 'absolute',
    top: 52,
    right: 20,
    zIndex: 2,
    padding: spacing.sm,
  },
  photoViewImage: {
    width: '92%',
    height: '78%',
  },
  modalCard: {
    width: '100%',
    maxWidth: 380,
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  modalTitle: {
    color: palette.text,
    fontSize: 17,
    fontWeight: '900',
    textAlign: 'center',
  },
  modalNote: {
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 19,
    textAlign: 'center',
  },
  previewImage: {
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: palette.secondary,
  },
  modalActions: {
    width: '100%',
    gap: spacing.sm,
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
    fontSize: 14,
    fontWeight: '800',
  },
  panelMeta: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '500',
  },
  profileSectionLabel: {
    marginBottom: spacing.xs,
    color: palette.textSoft,
    fontSize: 9,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  profileStatusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  profileStatusText: {
    fontSize: 10,
    fontWeight: '700',
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
  geotagMessage: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  mapActions: {
    flexDirection: 'row',
    alignItems: 'stretch',
    gap: spacing.sm,
    width: '100%',
  },
  gpsButton: {
    flex: 1,
  },
  mapOptionButton: {
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.card,
  },
  mapOptionButtonActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  mapOptionText: {
    color: palette.nav,
    fontSize: 12,
    fontWeight: '900',
  },
  mapOptionTextActive: {
    color: '#fff',
  },
});
