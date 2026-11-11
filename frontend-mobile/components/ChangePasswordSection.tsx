import { useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { api } from '@/api/client';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getRememberedCredentials, saveRememberedCredentials } from '@/utils/secureStorage';
import { passwordPolicyError } from '@/utils/passwordPolicy';
import { HouseholdButton } from '@/components/household/HouseholdUI';

const emptyForm = { current_password: '', password: '', password_confirmation: '' };

export function ChangePasswordSection({
  accountIdentifiers,
  standaloneButton = false,
  settingsRow = false,
}: {
  accountIdentifiers: (string | undefined)[];
  standaloneButton?: boolean;
  settingsRow?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [visible, setVisible] = useState<Record<string, boolean>>({});
  const submitting = useRef(false);
  const canSubmit = Boolean(form.current_password.trim() && form.password.trim() && form.password_confirmation.trim());

  async function submit() {
    if (submitting.current) return;
    setError('');
    setMessage('');
    if (!form.current_password) return setError('Current password is required.');
    const policyError = passwordPolicyError(form.password);
    if (policyError) return setError(policyError);
    if (form.password !== form.password_confirmation) return setError('New password and confirmation do not match.');
    if (form.password === form.current_password) return setError('Choose a different new password.');
    submitting.current = true;
    setSaving(true);
    try {
      const response = await api.patch('/profile/password', form);
      setForm(emptyForm);
      setOpen(false);
      setMessage(response.data.message || 'Password changed successfully.');
      try {
        const saved = await getRememberedCredentials();
        if (saved && accountIdentifiers.some((id) => id && id.toLowerCase() === saved.login.toLowerCase())) {
          await saveRememberedCredentials(saved.login, form.password, true);
        }
      } catch {
        setMessage('Password changed successfully. Remembered credentials could not be updated; use your new password next time you sign in.');
      }
    } catch (failure: any) {
      const errors = failure.response?.data?.errors;
      const detail = errors ? Object.values(errors).flat()[0] : null;
      setError(typeof detail === 'string' ? detail : failure.response?.data?.message || 'Unable to change password. Check your connection and try again.');
    } finally {
      submitting.current = false;
      setSaving(false);
    }
  }

  if (!open && standaloneButton) {
    return (
      <>
        {message ? <Text accessibilityRole="alert" style={styles.success}>{message}</Text> : null}
        <HouseholdButton
          label="Change password"
          icon="lock-closed-outline"
          tone="nav"
          layout={settingsRow ? 'settings' : 'default'}
          onPress={() => { setOpen(true); setMessage(''); }}
        />
      </>
    );
  }

  return (
    <View style={settingsRow ? styles.settingsForm : styles.card}>
      {settingsRow ? (
        <HouseholdButton
          label="Cancel password change"
          icon="lock-closed-outline"
          tone="light"
          layout="settings"
          onPress={() => { setOpen(false); setForm(emptyForm); setError(''); }}
        />
      ) : <Text style={styles.title}>Change password</Text>}
      {message ? <Text accessibilityRole="alert" style={styles.success}>{message}</Text> : null}
      {open ? <>
        {([
          ['current_password', 'Current password'],
          ['password', 'New password'],
          ['password_confirmation', 'Confirm new password'],
        ] as const).map(([key, label]) => (
          <View key={key} style={styles.field}>
            <Text style={styles.label}>{label}</Text>
            <View style={styles.passwordInput}>
              <TextInput accessibilityLabel={label} secureTextEntry={!visible[key]} autoCapitalize="none" autoCorrect={false}
              autoComplete={key === 'current_password' ? 'current-password' : 'new-password'}
              editable={!saving} value={form[key]} style={styles.input} maxLength={key === 'current_password' ? 255 : 128}
              onChangeText={(value) => setForm((current) => ({ ...current, [key]: value }))} />
              <Pressable accessibilityRole="button" accessibilityLabel={visible[key] ? 'Hide password' : 'Show password'}
                onPress={() => setVisible((current) => ({ ...current, [key]: !current[key] }))} style={styles.eyeButton}>
                <Ionicons name={visible[key] ? 'eye-off-outline' : 'eye-outline'} size={20} color={palette.textSoft} />
              </Pressable>
            </View>
          </View>
        ))}
        {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
        <HouseholdButton
          label={saving ? 'Updating...' : 'Update password'}
          icon="save-outline"
          tone="nav"
          disabled={saving || !canSubmit}
          onPress={submit}
        />
        <Pressable accessibilityRole="button" disabled={saving} onPress={() => { setOpen(false); setForm(emptyForm); setError(''); }}>
          <Text style={styles.label}>Cancel</Text>
        </Pressable>
      </> : <Pressable accessibilityRole="button" onPress={() => { setOpen(true); setMessage(''); }} style={styles.button}>
        <Text style={styles.buttonText}>Change password</Text>
      </Pressable>}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { gap: spacing.sm, padding: spacing.lg, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, backgroundColor: palette.card },
  settingsForm: { gap: spacing.sm },
  title: { fontSize: 18, fontWeight: '800', color: palette.text },
  field: { gap: spacing.xs },
  label: { color: palette.textSoft, fontWeight: '700' },
  passwordInput: { minHeight: 44, flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, backgroundColor: palette.page },
  input: { flex: 1, minHeight: 44, paddingHorizontal: spacing.md, color: palette.text },
  eyeButton: { minWidth: 44, minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  button: { minHeight: 44, padding: spacing.sm, alignItems: 'center', justifyContent: 'center', backgroundColor: palette.navActive, borderRadius: radius.md },
  buttonText: { color: '#fff', fontWeight: '800' },
  error: { color: palette.unsafe },
  success: { color: palette.safe },
});
