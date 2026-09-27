import { useEffect, useMemo, useState } from 'react';
import { Alert, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
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
  const selectedMemberId = String(user.member_id || members[0]?.member_id || '');
  const [savingGeotag, setSavingGeotag] = useState(false);

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
            <Text style={styles.avatarText}>{initials(householdName || user.full_name || 'H')}</Text>
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
          <View style={styles.deviceIcon}>
            <Ionicons name="phone-portrait-outline" size={24} color={palette.navActive} />
          </View>
          <View style={styles.panelText}>
            <Text style={styles.panelLabel}>User</Text>
            <Text style={styles.panelTitle}>{deviceOwnerName}</Text>
          </View>
        </View>

        {householdMembers.length ? (
          <View style={styles.memberProfilePanel}>
            <Text style={styles.panelLabel}>Member profile</Text>
            {householdMembers.map((member: any) => {
              return (
                <View key={member.member_id || member.name || member.full_name || `${member.first_name || 'member'}-${member.last_name || ''}`} style={styles.memberRow}>
                  <View style={styles.memberAvatar}>
                    <Text style={styles.memberAvatarText}>{initials(member.name || `${member.first_name || ''} ${member.last_name || ''}` || 'H')}</Text>
                  </View>
                  <View style={styles.panelText}>
                    <Text style={styles.panelTitle}>{member.name || `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Household member'}</Text>
                    <Text style={styles.panelMeta}>Member: {member.member_id || 'No member ID'}</Text>
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
    backgroundColor: '#ffffff1f',
  },
  avatarText: {
    color: '#fff',
    fontSize: 21,
    fontWeight: '900',
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
    paddingVertical: spacing.xs,
  },
  memberAvatar: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    backgroundColor: palette.secondary,
    borderWidth: 1,
    borderColor: palette.border,
  },
  memberAvatarText: {
    color: palette.navActive,
    fontSize: 12,
    fontWeight: '900',
  },
  deviceIcon: {
    width: 48,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: '#fff',
  },
  panelText: {
    flex: 1,
    minWidth: 0,
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
