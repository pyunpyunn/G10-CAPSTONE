import { useMemo, useRef, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import Svg, { Circle, Path } from 'react-native-svg';
import * as Location from 'expo-location';
import { MobileLeafletMap } from '@/components/MobileLeafletMap';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type ProfileProps = {
  overview: any;
  onUpdateGeotag: (payload: any) => Promise<any>;
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
  const [geotagMapVisible, setGeotagMapVisible] = useState(false);
  const [savingGeotag, setSavingGeotag] = useState(false);
  const [geotagMessage, setGeotagMessage] = useState(
    household.sitio_id
      ? `Saved in ${household.sitio_name || 'your registered sitio'}.`
      : 'Sitio will be identified automatically when verified boundary data is available.'
  );
  const geotagVersion = useRef(0);
  const geotagSaveQueue = useRef<Promise<void>>(Promise.resolve());
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
        ? `Saved in ${classification.sitio_name} · ${classification.purok_name}.`
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

      <View style={styles.card}>
        <HouseholdSection title="User Information" />

        <View style={styles.devicePanel}>
          <View style={styles.memberAvatar}>
            <Text style={styles.memberAvatarText}>{initials(deviceOwnerName)}</Text>
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
        <Text style={styles.mapHint}>Use GPS or open the map to move the center pin over your home. The location is saved when you stop moving the map.</Text>
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
          <>
            <Text style={styles.mapHint}>Map opens on Mambaling. Keep the pin at the center and pan the map; satellite imagery is available in the map controls. The server validates the point before saving.</Text>
            <MobileLeafletMap
              markers={[]}
              routes={[]}
              geotagPicker
              geotagMode="center"
              onMapPress={handleMapPoint}
              onPinMoving={() => setGeotagMessage('Move the map until the center pin is over your home, then stop to save.')}
              height={300}
            />
          </>
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
    </View>
  );
}

function HouseholdAccountIcon() {
  return (
    <Svg width={54} height={54} viewBox="0 0 64 64" fill="none">
      <Path
        d="M8 29L32 9L56 29M14 24V55H50V24M43 18V12H49V23"
        stroke={palette.navActive}
        strokeWidth={2.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Circle cx={32} cy={31} r={4} stroke={palette.navActive} strokeWidth={2.6} />
      <Circle cx={22} cy={36} r={3.2} stroke={palette.navActive} strokeWidth={2.4} />
      <Circle cx={42} cy={36} r={3.2} stroke={palette.navActive} strokeWidth={2.4} />
      <Path
        d="M23 51V47C23 42.6 26.6 39 31 39H33C37.4 39 41 42.6 41 47V51M15.5 51V48.5C15.5 45.2 18.2 42.5 21.5 42.5H23M41 42.5H42.5C45.8 42.5 48.5 45.2 48.5 48.5V51"
        stroke={palette.navActive}
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
    backgroundColor: palette.navActive,
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
    backgroundColor: '#f8fafc',
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
  mapHint: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
    lineHeight: 18,
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
