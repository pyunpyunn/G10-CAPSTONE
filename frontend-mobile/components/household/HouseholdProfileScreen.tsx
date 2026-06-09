import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
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

  async function handleSaveDeviceUser() {
    if (!selectedMemberId) {
      Alert.alert('Select member', 'Choose which household member is using this device.');
      return;
    }

    setSavingMember(true);

    try {
      await onSaveDeviceUser(selectedMemberId);
      Alert.alert('Device user saved', 'This mobile device is now assigned to the selected household member.');
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
    } finally {
      setSavingGeotag(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initials(household.household_name || user.full_name || 'H')}</Text>
        </View>
        <View style={styles.heroText}>
          <Text style={styles.role}>Household account</Text>
          <Text style={styles.name}>{household.household_name || 'Household'}</Text>
          <Text style={styles.meta}>{household.household_id || user.username || 'No household ID'}</Text>
        </View>
        <HouseholdBadge label={overview.active_event ? 'Disaster mode' : 'Standby'} tone={overview.active_event ? 'danger' : 'safe'} />
      </View>

      <View style={styles.card}>
        <HouseholdSection title="This device" />
        <InfoRow label="Logged device user" value={deviceUser} />
        <InfoRow label="Device ID" value={shortDeviceId(deviceUuid)} />
        <InfoRow label="Battery" value={realBatteryLevel !== null && realBatteryLevel !== undefined ? `${realBatteryLevel}%` : 'Not sent'} />
        <InfoRow label="Connection" value={connectionLabel || 'Offline'} />
        <InfoRow label="Last location" value={currentDevice?.last_location_label || geotag?.location_label || 'Not recorded'} />

        <Text style={styles.selectorLabel}>Assign this device to one member</Text>
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
                  <Text style={[styles.memberName, isSelected && styles.memberTextActive]}>{member.name}</Text>
                  <Text style={[styles.memberRelation, isSelected && styles.memberTextActive]}>{member.relationship || 'Member'}</Text>
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
        <HouseholdSection title="Member information" />
        {selectedMember ? (
          <>
            <View style={styles.statusPanel}>
              <InfoRow label="Device check" value={deviceCheckLabel(selectedMember)} />
              <InfoRow label="Last location" value={selectedMember.device?.last_location_label || 'No device location'} />
              <InfoRow label="Last seen" value={selectedMember.device?.last_seen_label || 'Not recorded'} />
            </View>

            <View style={styles.twoColumn}>
              <View style={styles.inputGroup}>
                <Text style={styles.formLabel}>First name</Text>
                <TextInput
                  style={styles.input}
                  value={memberForm.first_name}
                  onChangeText={(value) => setMemberForm((current) => ({ ...current, first_name: value }))}
                  placeholder="First name"
                  placeholderTextColor="#7d8da0"
                />
              </View>
              <View style={styles.inputGroup}>
                <Text style={styles.formLabel}>MI.</Text>
                <TextInput
                  style={styles.input}
                  value={memberForm.middle_name}
                  onChangeText={(value) => setMemberForm((current) => ({ ...current, middle_name: value }))}
                  placeholder="MI."
                  placeholderTextColor="#7d8da0"
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
        <HouseholdSection title="Household geotag" />
        {geotag ? (
          <>
            <InfoRow label="Location" value={geotag.location_label || 'Household geotag'} />
            <InfoRow label="Coordinates" value={`${Number(geotag.latitude).toFixed(6)}, ${Number(geotag.longitude).toFixed(6)}`} />
            <InfoRow label="Updated" value={geotag.updated_label || 'Not recorded'} />
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
        <HouseholdSection title="Household information" />
        <InfoRow label="Barangay" value={household.barangay || 'Not recorded'} />
        <InfoRow label="Address" value={household.address || 'Not recorded'} />
        <InfoRow label="Members" value={String(household.member_count || members.length || 0)} />
        <InfoRow label="Contact" value={household.contact_number || user.contact_number || 'Not recorded'} />
      </View>

      <HouseholdButton label="Log out" icon="log-out-outline" tone="danger" onPress={onLogout} />
    </View>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.navActive,
    ...shadow,
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
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
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
  statusPanel: {
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  inputGroup: {
    flex: 1,
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
    flexBasis: '48%',
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
});
