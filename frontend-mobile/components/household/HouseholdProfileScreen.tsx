import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { reverseGeocodeAddress } from '@/utils/geocoding';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdSection } from './HouseholdUI';

type ProfileProps = {
  overview: any;
  deviceUuid: string;
  currentDevice: any;
  realBatteryLevel?: number | null;
  connectionLabel?: string;
  onSaveDeviceUser: (memberId: string) => Promise<void>;
  onUpdateMember: (memberId: string, payload: any) => Promise<void>;
  onUpdateGeotag: (payload: any) => Promise<void>;
  onLogout: () => void;
};

export function HouseholdProfileScreen({
  overview,
  deviceUuid,
  currentDevice,
  realBatteryLevel,
  connectionLabel,
  onSaveDeviceUser,
  onUpdateMember,
  onUpdateGeotag,
  onLogout,
}: ProfileProps) {
  const household = overview.profile?.household || {};
  const user = overview.profile?.user || {};
  const members = useMemo(() => overview.members || [], [overview.members]);
  const geotag = overview.geotag || null;
  const [selectedMemberId, setSelectedMemberId] = useState('');
  const [memberForm, setMemberForm] = useState(memberFormFrom(null));
  const [savingMember, setSavingMember] = useState(false);
  const [savingMemberInfo, setSavingMemberInfo] = useState(false);
  const [savingGeotag, setSavingGeotag] = useState(false);

  useEffect(() => {
    const nextMemberId = currentDevice?.member_id || members[0]?.member_id || '';
    setSelectedMemberId(String(nextMemberId));
  }, [currentDevice?.member_id, members]);

  const selectedMember = useMemo(() => {
    return members.find((member: any) => String(member.member_id) === String(selectedMemberId)) || null;
  }, [members, selectedMemberId]);

  useEffect(() => {
    setMemberForm(memberFormFrom(selectedMember));
  }, [selectedMember]);

  const deviceUser = selectedMember?.name || currentDevice?.member_name || 'No member selected';
  const householdName = household.household_name || 'Household';
  const householdIdentifier = household.household_id || user.username || 'No household ID';
  const memberCount = members.length || Number(household.member_count || 0);
  const batteryValue = realBatteryLevel !== null && realBatteryLevel !== undefined ? `${realBatteryLevel}%` : 'Not sent';
  const connectionValue = connectionLabel || 'Offline';
  const lastLocationValue = currentDevice?.last_location_label || geotag?.location_label || 'Not recorded';
  const hasGeotag = Boolean(geotag?.latitude && geotag?.longitude);

  async function handleSaveDeviceUser() {
    if (!selectedMemberId) {
      Alert.alert('Select member', 'Choose which household member is using this device.');
      return;
    }

    setSavingMember(true);

    try {
      await onSaveDeviceUser(selectedMemberId);
      Alert.alert('Device user saved', 'This mobile device is now assigned to the selected household member.');
    } catch {
      // Parent screen already shows the API error message.
    } finally {
      setSavingMember(false);
    }
  }

  async function handleSaveMemberInfo() {
    if (!selectedMemberId) {
      Alert.alert('Select member', 'Choose which household member you want to update.');
      return;
    }

    if (!memberForm.first_name.trim() || !memberForm.last_name.trim() || !memberForm.relationship.trim()) {
      Alert.alert('Missing details', 'First name, last name, and relationship are required.');
      return;
    }

    setSavingMemberInfo(true);

    try {
      await onUpdateMember(selectedMemberId, {
        first_name: memberForm.first_name.trim(),
        middle_name: memberForm.middle_name.trim() || null,
        last_name: memberForm.last_name.trim(),
        relationship: memberForm.relationship.trim(),
        gender: memberForm.gender.trim() || null,
        age: memberForm.age ? Number(memberForm.age) : null,
        birth_date: memberForm.birth_date.trim() || null,
        special_needs: memberForm.special_needs.trim() || null,
      });
      Alert.alert('Member saved', 'Family member information was updated.');
    } catch {
      // Parent screen already shows the API error message.
    } finally {
      setSavingMemberInfo(false);
    }
  }

  async function handleUpdateGeotag() {
    if (!selectedMemberId) {
      Alert.alert('Select member', 'Choose the member using this device before updating the geotag.');
      return;
    }

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
        member_id: selectedMemberId,
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
          <HouseholdBadge label={overview.active_event ? 'Disaster mode' : 'Standby'} tone={overview.active_event ? 'danger' : 'safe'} />
        </View>

        <View style={styles.heroStats}>
          <HeroStat icon="people-outline" label="Members" value={String(memberCount)} />
          <HeroStat icon="phone-portrait-outline" label="Device user" value={deviceUser} />
          <HeroStat icon="location-outline" label="Geotag" value={hasGeotag ? 'Saved' : 'Missing'} />
        </View>
      </View>

      <View style={styles.card}>
        <HouseholdSection
          title="Device owner"
          action={<HouseholdBadge label={connectionValue} tone={connectionValue === 'Offline' ? 'warning' : 'active'} />}
        />

        <View style={styles.devicePanel}>
          <View style={styles.deviceIcon}>
            <Ionicons name="phone-portrait-outline" size={24} color={palette.navActive} />
          </View>
          <View style={styles.panelText}>
            <Text style={styles.panelLabel}>Assigned member</Text>
            <Text style={styles.panelTitle}>{deviceUser}</Text>
            <Text style={styles.panelMeta}>{shortDeviceId(deviceUuid)}</Text>
          </View>
        </View>

        <View style={styles.metricGrid}>
          <MetricTile icon="battery-half-outline" label="Battery" value={batteryValue} />
          <MetricTile icon="wifi-outline" label="Connection" value={connectionValue} />
          <MetricTile icon="navigate-outline" label="Last location" value={lastLocationValue} />
        </View>

        <Text style={styles.selectorLabel}>Assign this device</Text>
        {members.length === 0 ? (
          <HouseholdEmpty icon="people-outline" title="No family members found" />
        ) : (
          <View style={styles.memberGrid}>
            {members.map((member: any) => {
              const isSelected = String(member.member_id) === String(selectedMemberId);

              return (
                <Pressable
                  key={member.member_id}
                  style={[styles.memberChoice, isSelected && styles.memberChoiceActive]}
                  onPress={() => setSelectedMemberId(String(member.member_id))}
                >
                  <View style={styles.memberChoiceTop}>
                    <MemberInitialAvatar name={member.name} selected={isSelected} />
                    {isSelected ? <Ionicons name="checkmark-circle" size={20} color="#fff" /> : null}
                  </View>
                  <Text style={[styles.memberName, isSelected && styles.memberTextActive]} numberOfLines={1}>{member.name}</Text>
                  <Text style={[styles.memberRelation, isSelected && styles.memberTextActive]} numberOfLines={1}>{member.relationship || 'Member'}</Text>
                </Pressable>
              );
            })}
          </View>
        )}

        <HouseholdButton
          label={savingMember ? 'Saving device user...' : 'Save device user'}
          icon="phone-portrait-outline"
          disabled={savingMember || !selectedMemberId}
          onPress={handleSaveDeviceUser}
        />
      </View>

      <View style={styles.card}>
        <HouseholdSection
          title="Member profile"
          action={selectedMember ? <HouseholdBadge label={selectedMember.relationship || 'Member'} tone="info" /> : undefined}
        />
        {selectedMember ? (
          <>
            <View style={styles.memberProfileHeader}>
              <MemberInitialAvatar name={selectedMember.name} selected size="large" />
              <View style={styles.panelText}>
                <Text style={styles.panelLabel}>Editing</Text>
                <Text style={styles.panelTitle}>{selectedMember.name || 'Household member'}</Text>
                <Text style={styles.panelMeta}>{selectedMember.relationship || 'Member'}</Text>
              </View>
            </View>

            <View style={styles.metricGrid}>
              <MetricTile icon="pulse-outline" label="Device check" value={deviceCheckLabel(selectedMember)} />
              <MetricTile icon="location-outline" label="Location" value={selectedMember.device?.last_location_label || 'No device location'} />
              <MetricTile icon="time-outline" label="Last seen" value={selectedMember.device?.last_seen_label || 'Not recorded'} />
            </View>

            <View style={styles.formCluster}>
              <View style={styles.twoColumn}>
                <View style={styles.inputGroup}>
                  <Text style={styles.formLabel}>First name</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.first_name}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, first_name: value }))}
                    placeholder="First name"
                    placeholderTextColor="#7d8da0"
                    autoCapitalize="words"
                  />
                </View>
                <View style={styles.inputGroupSmall}>
                  <Text style={styles.formLabel}>MI.</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.middle_name}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, middle_name: value }))}
                    placeholder="MI."
                    placeholderTextColor="#7d8da0"
                    autoCapitalize="words"
                  />
                </View>
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.formLabel}>Last name</Text>
                <TextInput
                  style={styles.input}
                  value={memberForm.last_name}
                  onChangeText={(value) => setMemberForm((current) => ({ ...current, last_name: value }))}
                  placeholder="Last name"
                  placeholderTextColor="#7d8da0"
                  autoCapitalize="words"
                />
              </View>

              <View style={styles.twoColumn}>
                <View style={styles.inputGroup}>
                  <Text style={styles.formLabel}>Relationship</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.relationship}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, relationship: value }))}
                    placeholder="Relationship"
                    placeholderTextColor="#7d8da0"
                    autoCapitalize="words"
                  />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.formLabel}>Gender</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.gender}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, gender: value }))}
                    placeholder="Gender"
                    placeholderTextColor="#7d8da0"
                    autoCapitalize="words"
                  />
                </View>
              </View>

              <View style={styles.twoColumn}>
                <View style={styles.inputGroup}>
                  <Text style={styles.formLabel}>Age</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.age}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, age: value.replace(/[^0-9]/g, '') }))}
                    keyboardType="number-pad"
                    placeholder="Age"
                    placeholderTextColor="#7d8da0"
                  />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.formLabel}>Birth date</Text>
                  <TextInput
                    style={styles.input}
                    value={memberForm.birth_date}
                    onChangeText={(value) => setMemberForm((current) => ({ ...current, birth_date: value }))}
                    placeholder="YYYY-MM-DD"
                    placeholderTextColor="#7d8da0"
                  />
                </View>
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.formLabel}>Special needs</Text>
                <TextInput
                  style={[styles.input, styles.multilineInput]}
                  value={memberForm.special_needs}
                  onChangeText={(value) => setMemberForm((current) => ({ ...current, special_needs: value }))}
                  placeholder="None, PWD, senior, pregnant, medication needs..."
                  placeholderTextColor="#7d8da0"
                  multiline
                />
              </View>
            </View>

            <HouseholdButton
              label={savingMemberInfo ? 'Saving member...' : 'Save member information'}
              icon="save-outline"
              disabled={savingMemberInfo}
              onPress={handleSaveMemberInfo}
            />
          </>
        ) : (
          <HouseholdEmpty icon="person-outline" title="Select a member first" />
        )}
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

function HeroStat({ icon, label, value }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }) {
  return (
    <View style={styles.heroStat}>
      <Ionicons name={icon} size={16} color="#fff" />
      <Text style={styles.heroStatLabel}>{label}</Text>
      <Text style={styles.heroStatValue} numberOfLines={1}>{value}</Text>
    </View>
  );
}

function MetricTile({ icon, label, value }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }) {
  return (
    <View style={styles.metricTile}>
      <Ionicons name={icon} size={17} color={palette.navActive} />
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue} numberOfLines={2}>{value}</Text>
    </View>
  );
}

function MemberInitialAvatar({
  name,
  selected = false,
  size = 'normal',
}: {
  name: string;
  selected?: boolean;
  size?: 'normal' | 'large';
}) {
  return (
    <View style={[
      styles.choiceAvatar,
      size === 'large' && styles.choiceAvatarLarge,
      selected && styles.choiceAvatarSelected,
    ]}>
      <Text style={[styles.choiceAvatarText, size === 'large' && styles.choiceAvatarTextLarge, selected && styles.choiceAvatarTextSelected]}>
        {String(name || 'H')[0]?.toUpperCase()}
      </Text>
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

function shortDeviceId(value: string) {
  if (!value) return 'Not recorded';
  return value.length > 18 ? `${value.slice(0, 9)}...${value.slice(-5)}` : value;
}

function coordinatesLabel(geotag: any) {
  if (!geotag?.latitude || !geotag?.longitude) {
    return 'Coordinates not recorded';
  }

  return `${Number(geotag.latitude).toFixed(6)}, ${Number(geotag.longitude).toFixed(6)}`;
}

function memberFormFrom(member: any) {
  const nameParts = String(member?.name || '').split(' ').filter(Boolean);
  const firstName = member?.first_name || nameParts[0] || '';
  const lastName = member?.last_name || (nameParts.length > 1 ? nameParts[nameParts.length - 1] : '');
  const middleName = member?.middle_name || (nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '');

  return {
    first_name: firstName,
    middle_name: middleName,
    last_name: lastName,
    relationship: member?.relationship || '',
    gender: member?.gender || '',
    age: member?.age !== null && member?.age !== undefined ? String(member.age) : '',
    birth_date: member?.birth_date || '',
    special_needs: member?.special_needs || '',
  };
}

function deviceCheckLabel(member: any) {
  const device = member?.device;

  if (!device) {
    return 'No device registered';
  }

  const battery = device.battery_level !== null && device.battery_level !== undefined
    ? `${device.battery_level}% battery`
    : 'Battery not sent';
  const active = device.is_active ? 'Active' : 'Inactive';

  return `${active} · ${battery}`;
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
  heroStats: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  heroStat: {
    flex: 1,
    minHeight: 72,
    justifyContent: 'center',
    gap: 4,
    borderWidth: 1,
    borderColor: '#ffffff33',
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: '#ffffff14',
  },
  heroStatLabel: {
    color: palette.navMuted,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  heroStatValue: {
    color: '#fff',
    fontSize: 13,
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
  devicePanel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
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
  metricGrid: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  metricTile: {
    flex: 1,
    minHeight: 86,
    gap: 5,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.sm,
    backgroundColor: '#f8fafc',
  },
  metricLabel: {
    color: palette.textSoft,
    fontSize: 10,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  metricValue: {
    color: palette.text,
    fontSize: 12,
    lineHeight: 16,
    fontWeight: '900',
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
  memberProfileHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  formCluster: {
    gap: spacing.sm,
  },
  inputGroup: {
    flex: 1,
    gap: 6,
  },
  inputGroupSmall: {
    width: 92,
    gap: 6,
  },
  formLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  input: {
    minHeight: 44,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    color: palette.text,
    fontSize: 14,
    fontWeight: '800',
    backgroundColor: '#fff',
  },
  multilineInput: {
    minHeight: 76,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  selectorLabel: {
    marginTop: spacing.xs,
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  memberGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  memberChoice: {
    flexBasis: '47%',
    flexGrow: 1,
    minHeight: 104,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  memberChoiceActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  memberChoiceTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  choiceAvatar: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: 17,
    backgroundColor: palette.secondary,
  },
  choiceAvatarLarge: {
    width: 50,
    height: 50,
    borderRadius: 25,
  },
  choiceAvatarSelected: {
    borderColor: '#ffffff80',
    backgroundColor: '#ffffff24',
  },
  choiceAvatarText: {
    color: palette.navActive,
    fontSize: 13,
    fontWeight: '900',
  },
  choiceAvatarTextLarge: {
    fontSize: 18,
  },
  choiceAvatarTextSelected: {
    color: '#fff',
  },
  memberName: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  memberRelation: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  memberTextActive: {
    color: '#fff',
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
