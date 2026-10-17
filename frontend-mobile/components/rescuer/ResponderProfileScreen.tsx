import { useEffect, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { ActionButton, SectionHeader, StatusBadge } from './RescuerUI';

type ProfileProps = {
  profile: any;
  onSaveProfile: (payload: ProfileForm) => Promise<void>;
  onLogout: () => void;
};

type ProfileForm = {
  username: string;
  first_name: string;
  middle_initial: string;
  last_name: string;
  email: string;
  contact_number: string;
  emergency_contact_name: string;
  emergency_contact_number: string;
  blood_type: string;
  address: string;
  skills: string;
};

export function ResponderProfileScreen({ profile, onSaveProfile, onLogout }: ProfileProps) {
  const user = profile?.user || {};
  const responder = profile?.responder || {};
  const initials = makeInitials(responder.full_name || user.full_name || user.username || 'R');
  const [isEditing, setIsEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<ProfileForm>(() => profileForm(user, responder));

  useEffect(() => {
    const currentUser = profile?.user || {};
    const currentResponder = profile?.responder || {};

    setForm(profileForm(currentUser, currentResponder));
  }, [profile]);

  async function handleSave() {
    setSaving(true);

    try {
      await onSaveProfile(form);
      setIsEditing(false);
    } finally {
      setSaving(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.hero}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <View style={styles.heroText}>
          <Text style={styles.role}>Verified rescuer</Text>
          <Text style={styles.name}>{responder.full_name || user.full_name || 'Responder'}</Text>
          <Text style={styles.meta}>
            @{user.username || 'username'} · {responder.responder_code || 'No account ID'} · {responder.team_name || 'No team'}
          </Text>
        </View>
        <StatusBadge label={responder.duty_status || 'Stand-by'} tone={responder.duty_status || 'neutral'} />
      </View>

      <View style={styles.card}>
        <SectionHeader
          title="Account"
          action={
            <Pressable style={styles.iconButton} onPress={() => setIsEditing((value) => !value)}>
              <Ionicons name={isEditing ? 'close' : 'create-outline'} size={18} color={palette.navActive} />
            </Pressable>
          }
        />
        <InfoRow label="Account ID" value={responder.responder_code || 'Not recorded'} />
        <InfoRow label="Username" value={user.username ? `@${user.username}` : 'Not recorded'} />
        <InfoRow label="Role" value={user.role?.role_name || 'Rescuer'} />
        <InfoRow label="Email" value={user.email || 'Not recorded'} />
        <InfoRow label="Mobile" value={user.contact_number || responder.contact_number || 'Not recorded'} />
      </View>

      {isEditing ? (
        <View style={styles.card}>
          <SectionHeader title="Edit profile" />
          <View style={styles.formGrid}>
            <ProfileInput label="Username" value={form.username} onChangeText={(value) => updateForm(setForm, 'username', value)} />
            <ProfileInput label="First name" value={form.first_name} onChangeText={(value) => updateForm(setForm, 'first_name', value)} />
            <ProfileInput label="MI" value={form.middle_initial} onChangeText={(value) => updateForm(setForm, 'middle_initial', value)} />
            <ProfileInput label="Last name" value={form.last_name} onChangeText={(value) => updateForm(setForm, 'last_name', value)} />
            <ProfileInput label="Email" value={form.email} onChangeText={(value) => updateForm(setForm, 'email', value)} keyboardType="email-address" />
            <ProfileInput label="Mobile" value={form.contact_number} onChangeText={(value) => updateForm(setForm, 'contact_number', value)} keyboardType="phone-pad" />
            <ProfileInput label="Emergency contact name" value={form.emergency_contact_name} onChangeText={(value) => updateForm(setForm, 'emergency_contact_name', value)} />
            <ProfileInput label="Emergency contact mobile" value={form.emergency_contact_number} onChangeText={(value) => updateForm(setForm, 'emergency_contact_number', value)} keyboardType="phone-pad" />
            <ProfileInput label="Blood type" value={form.blood_type} onChangeText={(value) => updateForm(setForm, 'blood_type', value)} />
            <ProfileInput label="Address" value={form.address} onChangeText={(value) => updateForm(setForm, 'address', value)} multiline />
            <ProfileInput label="Skills" value={form.skills} onChangeText={(value) => updateForm(setForm, 'skills', value)} multiline />
          </View>
          <View style={styles.actionRow}>
            <ActionButton label="Cancel" icon="close" tone="light" onPress={() => setIsEditing(false)} />
            <ActionButton label={saving ? 'Saving...' : 'Save profile'} icon="save-outline" disabled={saving} onPress={handleSave} />
          </View>
        </View>
      ) : null}

      <View style={styles.card}>
        <SectionHeader title="Responder details" />
        <InfoRow label="Team" value={responder.team_name || 'Unassigned'} />
        <InfoRow label="Team code" value={responder.team_code || 'Not recorded'} />
        <InfoRow label="Title" value={responder.title || 'Responder'} />
        <InfoRow label="Blood type" value={responder.blood_type || 'Unknown'} />
        <InfoRow label="Skills" value={responder.skills || 'Not recorded'} />
      </View>

      <View style={styles.card}>
        <SectionHeader title="Emergency contact" />
        <InfoRow label="Name" value={responder.emergency_contact_name || 'Not recorded'} />
        <InfoRow label="Mobile" value={responder.emergency_contact_number || 'Not recorded'} />
      </View>

      <ActionButton label="Log out" icon="log-out-outline" tone="danger" onPress={onLogout} />
    </View>
  );
}

function ProfileInput({
  label,
  value,
  onChangeText,
  keyboardType = 'default',
  multiline = false,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  keyboardType?: 'default' | 'email-address' | 'phone-pad';
  multiline?: boolean;
}) {
  return (
    <View style={styles.inputGroup}>
      <Text style={styles.inputLabel}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType}
        autoCapitalize="none"
        multiline={multiline}
        style={[styles.input, multiline && styles.textArea]}
        placeholderTextColor={palette.textSoft}
      />
    </View>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <View style={styles.infoValueWrap}>
        <Ionicons name="ellipse" size={6} color={palette.navMuted} />
        <Text style={styles.infoValue}>{value}</Text>
      </View>
    </View>
  );
}

function updateForm(
  setForm: Dispatch<SetStateAction<ProfileForm>>,
  key: keyof ProfileForm,
  value: string
) {
  setForm((current) => ({ ...current, [key]: value }));
}

function profileForm(user: any, responder: any): ProfileForm {
  const fullName = responder.full_name || user.full_name || '';
  const parts = splitName(fullName);
  const firstName = user.first_name || parts.first_name;
  const lastName = user.last_name || parts.last_name;

  return {
    username: user.username || '',
    first_name: firstName,
    middle_initial: middleInitialFromName(fullName, firstName, lastName),
    last_name: lastName,
    email: user.email || '',
    contact_number: user.contact_number || responder.contact_number || '',
    emergency_contact_name: responder.emergency_contact_name || '',
    emergency_contact_number: responder.emergency_contact_number || '',
    blood_type: responder.blood_type === 'Unknown' ? '' : responder.blood_type || '',
    address: responder.address || '',
    skills: responder.skills || '',
  };
}

function middleInitialFromName(fullName: string, firstName: string, lastName: string) {
  let middle = fullName.trim();

  if (firstName) {
    middle = middle.replace(new RegExp(`^${escapeRegExp(firstName)}\\s+`, 'i'), '');
  }

  if (lastName) {
    middle = middle.replace(new RegExp(`\\s+${escapeRegExp(lastName)}$`, 'i'), '');
  }

  middle = middle.replace('.', '').trim();

  return middle ? `${middle[0].toUpperCase()}.` : '';
}

function escapeRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function splitName(name: string) {
  const parts = name.split(' ').filter(Boolean);

  if (parts.length <= 1) {
    return { first_name: parts[0] || '', last_name: '' };
  }

  return {
    first_name: parts.slice(0, -1).join(' '),
    last_name: parts[parts.length - 1],
  };
}

function makeInitials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');
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
  iconButton: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.page,
  },
  formGrid: {
    gap: spacing.sm,
  },
  inputGroup: {
    gap: 6,
  },
  inputLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  input: {
    minHeight: 44,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    color: palette.text,
    backgroundColor: palette.page,
    fontSize: 14,
    fontWeight: '800',
  },
  textArea: {
    minHeight: 82,
    paddingTop: spacing.sm,
    textAlignVertical: 'top',
  },
  actionRow: {
    flexDirection: 'row',
    gap: spacing.sm,
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
  infoValueWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 6,
  },
  infoValue: {
    flexShrink: 1,
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
    textAlign: 'right',
  },
});
