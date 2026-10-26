import { useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from '@/api/client';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getRememberedCredentials, saveRememberedCredentials } from '@/utils/secureStorage';

const emptyForm = { current_password: '', password: '', password_confirmation: '' };

export function ChangePasswordSection({ accountIdentifiers }: { accountIdentifiers: (string | undefined)[] }) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const submitting = useRef(false);

  async function submit() {
    if (submitting.current) return;
    setError('');
    setMessage('');
    if (!form.current_password) return setError('Current password is required.');
    if (form.password.length < 8 || form.password.length > 128) return setError('New password must contain 8 to 128 characters.');
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

  return (
    <View style={styles.card}>
      <Text style={styles.title}>Change password</Text>
      {message ? <Text accessibilityRole="alert" style={styles.success}>{message}</Text> : null}
      {open ? <>
        <Text style={styles.help}>Use 8 to 128 characters. Other signed-in devices will be signed out.</Text>
        {([
          ['current_password', 'Current password'],
          ['password', 'New password'],
          ['password_confirmation', 'Confirm new password'],
        ] as const).map(([key, label]) => (
          <View key={key} style={styles.field}>
            <Text style={styles.label}>{label}</Text>
            <TextInput accessibilityLabel={label} secureTextEntry autoCapitalize="none" autoCorrect={false}
              autoComplete={key === 'current_password' ? 'current-password' : 'new-password'}
              editable={!saving} value={form[key]} style={styles.input}
              onChangeText={(value) => setForm((current) => ({ ...current, [key]: value }))} />
          </View>
        ))}
        {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
        <Pressable accessibilityRole="button" disabled={saving} onPress={submit} style={styles.button}>
          <Text style={styles.buttonText}>{saving ? 'Updating...' : 'Update password'}</Text>
        </Pressable>
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
  title: { fontSize: 18, fontWeight: '800', color: palette.text },
  help: { color: palette.textSoft },
  field: { gap: spacing.xs },
  label: { color: palette.textSoft, fontWeight: '700' },
  input: { minHeight: 44, paddingHorizontal: spacing.md, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, color: palette.text, backgroundColor: palette.page },
  button: { minHeight: 44, padding: spacing.sm, alignItems: 'center', justifyContent: 'center', backgroundColor: palette.navActive, borderRadius: radius.md },
  buttonText: { color: '#fff', fontWeight: '800' },
  error: { color: palette.unsafe },
  success: { color: palette.safe },
});
