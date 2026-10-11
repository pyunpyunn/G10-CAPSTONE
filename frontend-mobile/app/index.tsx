import { useEffect, useRef, useState } from 'react';
import {
  Animated,
  ActivityIndicator,
  Alert,
  Easing,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  useWindowDimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { clearToken } from '@/api/client';
import { getRememberedCredentials, saveRememberedCredentials } from '@/utils/secureStorage';
import { loginMobile, changePasswordWithOldPassword, verifyPasswordChange } from '@/api/auth';
import { palette, spacing } from '@/constants/resqTheme';
import { askStandardMobilePermissions } from '@/utils/mobilePermissions';
import ResQperationLogo from '@/components/ResQperationLogo';

export default function MobileLoginScreen() {
  const router = useRouter();
  const { width: viewportWidth } = useWindowDimensions();
  const logoEntrance = useRef(new Animated.Value(0)).current;
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [rememberPassword, setRememberPassword] = useState(false);
  const edited = useRef(false);
  useEffect(() => {
    let active = true;
    getRememberedCredentials().then(saved => {
      if (!active || !saved || edited.current) return;
      setLogin(saved.login); setPassword(saved.password); setRememberPassword(true);
    }).catch(() => { /* Login remains available if secure storage cannot be read. */ });
    return () => { active = false; };
  }, []);
  useEffect(() => {
    const animation = Animated.timing(logoEntrance, {
      toValue: 1,
      duration: 720,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    });
    animation.start();
    return () => animation.stop();
  }, [logoEntrance]);
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const [changing, setChanging] = useState(false);
  const [newPassword, setNewPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [notice, setNotice] = useState('');

  function switchMode() {
    edited.current = true;
    setChanging(value => !value); setPassword(''); setNewPassword(''); setConfirmation(''); setShowPassword(false); setNotice('');
  }

  async function verifyAccountId() {
    const accountId = login.trim();
    if (!accountId) return;

    try {
      await verifyPasswordChange(accountId);
    } catch (error: any) {
      Alert.alert('Account ID not found', authErrorMessage(error, 'The account ID could not be verified.'));
    }
  }

  async function verifyOldPassword() {
    const accountId = login.trim();
    if (!changing || !accountId || !password) return;

    try {
      await verifyPasswordChange(accountId, password);
    } catch (error: any) {
      Alert.alert('Old password not verified', authErrorMessage(error, 'The account ID or old password is incorrect.'));
    }
  }

  async function handlePasswordChange() {
    setLoading(true); setNotice('');
    try {
      if (!login.trim()) throw new Error('Enter your account ID.');
      if (!password) throw new Error('Enter your old password.');
      await verifyPasswordChange(login, password);
      if (newPassword.length < 8 || newPassword.length > 128) throw new Error('New password must contain 8 to 128 characters.');
      if (newPassword !== confirmation) throw new Error('New password and confirmation do not match.');
      if (newPassword === password) throw new Error('Choose a different new password.');
      await changePasswordWithOldPassword({ login: login.trim(), current_password: password, password: newPassword, password_confirmation: confirmation });
      try {
        const saved = await getRememberedCredentials();
        if (saved?.login === login.trim()) await saveRememberedCredentials(login, newPassword, rememberPassword);
      } catch { Alert.alert('Password changed', 'The password was changed, but the saved password could not be updated. Enter the new password on your next login.'); }
      await clearToken(); setChanging(false); setPassword(''); setNewPassword(''); setConfirmation(''); setShowPassword(false);
      setNotice('Password changed. Sign in with your new password.');
    } catch (error: any) {
      Alert.alert('Password change failed', authErrorMessage(error, 'Unable to change password. Try again.'));
    } finally { setLoading(false); }
  }

  async function handleLogin() {
    if (!login.trim() || !password.trim()) {
      Alert.alert('Missing details', 'Enter your user ID and password.');
      return;
    }

    setLoading(true);

    try {
      const user = await loginMobile(login.trim(), password);
      const role = user.role?.role_key;
      if (role === 'household_resident' || role === 'rescuer') {
        try { await saveRememberedCredentials(login, password, rememberPassword); }
        catch { Alert.alert('Signed in', 'Your password could not be remembered on this device. You can still continue.'); }
      }

      if (role === 'household_resident') {
        await askStandardMobilePermissions('household_resident');
        router.replace('/household' as Href);
        return;
      }

      if (role === 'rescuer') {
        await askStandardMobilePermissions('rescuer');
        router.replace('/rescuer' as Href);
        return;
      }

      await clearToken();
      Alert.alert('Use web dashboard', 'This account is for HQ/Admin web access.');
    } catch (error: any) {
      Alert.alert('Login failed', authErrorMessage(error, 'Unable to sign in. Please check your user ID and password.'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.screen} keyboardShouldPersistTaps="handled">
          <Animated.View
            style={[
              styles.brandBlock,
              {
                opacity: logoEntrance,
                transform: [{ translateY: logoEntrance.interpolate({ inputRange: [0, 1], outputRange: [10, 0] }) }],
              },
            ]}
          >
            <ResQperationLogo width={Math.min(viewportWidth - spacing.xl * 2, 370)} />
          </Animated.View>

          <View style={styles.card}>
            <View style={styles.headerBlock}>
              <Text style={styles.title}>{changing ? 'Change password' : 'Sign in'}</Text>
              {changing && <Text style={styles.help}>Verify your old password, then choose a different password of at least 8 characters. All devices will be signed out.</Text>}
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>User ID</Text>
              <View style={styles.inputShell}>
                <Ionicons name="person-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={login}
                  onChangeText={value => { edited.current = true; setLogin(value); }}
                  onBlur={() => { void verifyAccountId(); }}
                  placeholder="Enter your account ID"
                  placeholderTextColor={palette.navMuted}
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>{changing ? 'Old password' : 'Password'}</Text>
              <View style={styles.inputShell}>
                <Ionicons name="lock-closed-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={password}
                  onChangeText={value => { edited.current = true; setPassword(value); }}
                  onBlur={() => { void verifyOldPassword(); }}
                  placeholder="Enter password"
                  placeholderTextColor={palette.navMuted}
                  secureTextEntry={!showPassword}
                />
                <Pressable
                  style={styles.eyeButton}
                  onPress={() => setShowPassword((current) => !current)}
                  accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
                >
                  <Ionicons
                    name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                    size={21}
                    color={palette.navText}
                  />
                </Pressable>
              </View>
            </View>

            {!changing && Platform.OS !== 'web' && <Pressable
              style={styles.rememberRow}
              disabled={loading}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: rememberPassword }}
              onPress={() => {
                const next = !rememberPassword; setRememberPassword(next);
                if (!next) void saveRememberedCredentials('', '', false).catch(() => Alert.alert('Unable to forget password', 'Please try again.'));
              }}
            ><Ionicons name={rememberPassword ? 'checkbox' : 'square-outline'} size={21} color={palette.brandRed} /><Text style={styles.rememberText}>Remember password</Text></Pressable>}
            {changing && <>
              <View style={styles.fieldGroup}><Text style={styles.label}>New password</Text><View style={styles.inputShell}><TextInput style={styles.input} value={newPassword} onChangeText={setNewPassword} secureTextEntry={!showPassword} autoCapitalize="none" autoCorrect={false} autoComplete="new-password" maxLength={128} editable={!loading} /></View></View>
              <View style={styles.fieldGroup}><Text style={styles.label}>Confirm new password</Text><View style={styles.inputShell}><TextInput style={styles.input} value={confirmation} onChangeText={setConfirmation} secureTextEntry={!showPassword} autoCapitalize="none" autoCorrect={false} autoComplete="new-password" maxLength={128} editable={!loading} /></View></View>
            </>}
            {!!notice && <Text accessibilityLiveRegion="polite" style={styles.help}>{notice}</Text>}
            <Pressable style={({ pressed }) => [styles.button, pressed && !loading && styles.buttonPressed, loading && styles.buttonDisabled]} onPress={changing ? handlePasswordChange : handleLogin} disabled={loading}>
              {loading ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <>
                  <Ionicons name={changing ? 'key-outline' : 'log-in-outline'} size={19} color="#fff" />
                  <Text style={styles.buttonText}>{changing ? 'Update password' : 'Login'}</Text>
                </>
              )}
            </Pressable>

            <Pressable
              style={({ pressed }) => [styles.modeButton, pressed && styles.modeButtonPressed]}
              disabled={loading}
              onPress={switchMode}
            >
              <Text style={styles.modeButtonText}>{changing ? 'Back to sign in' : 'Change password'}</Text>
            </Pressable>
            {changing && <Text style={styles.recoveryHelp}>Forgot your old password? Contact HQ/Admin for account recovery.</Text>}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function authErrorMessage(error: any, fallback: string) {
  const responseData = error?.response?.data;
  const validationMessage = Object.values(responseData?.errors || {}).flat()[0];
  return String(validationMessage || error?.userMessage || responseData?.message || error?.message || fallback);
}

const styles = StyleSheet.create({
  rememberRow: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingVertical: 2 },
  help: { color: palette.navMuted, fontSize: 13, lineHeight: 20 },
  safe: {
    flex: 1,
    backgroundColor: palette.nav,
  },
  keyboard: {
    flex: 1,
  },
  screen: {
    flexGrow: 1,
    justifyContent: 'center',
    gap: spacing.xl,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xl,
  },
  brandBlock: {
    alignItems: 'center',
  },
  card: {
    gap: spacing.md,
    borderWidth: 0,
    borderRadius: 0,
    padding: 0,
    backgroundColor: 'transparent',
  },
  headerBlock: {
    gap: spacing.xs,
  },
  title: {
    color: palette.navText,
    fontSize: 29,
    fontWeight: '800',
  },
  fieldGroup: {
    gap: 8,
  },
  label: {
    color: palette.navMuted,
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },
  inputShell: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: '#385670',
    borderRadius: 11,
    paddingHorizontal: spacing.md,
    backgroundColor: '#0e1c2b',
  },
  input: {
    flex: 1,
    minHeight: 52,
    color: palette.navText,
    fontSize: 15,
    fontWeight: '600',
  },
  eyeButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
  },
  button: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderRadius: 11,
    backgroundColor: palette.brandRed,
    shadowColor: palette.brandRed,
    shadowOffset: { width: 0, height: 5 },
    shadowOpacity: 0.2,
    shadowRadius: 10,
    elevation: 3,
  },
  buttonPressed: {
    backgroundColor: '#a5262b',
    transform: [{ scale: 0.99 }],
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  modeButton: {
    minHeight: 46,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#385670',
    borderRadius: 11,
    backgroundColor: '#1a3047',
  },
  modeButtonPressed: {
    backgroundColor: palette.navHover,
  },
  modeButtonText: {
    color: palette.navText,
    fontSize: 13,
    fontWeight: '700',
  },
  rememberText: { color: palette.navMuted, fontSize: 13, fontWeight: '600' },
  recoveryHelp: { color: palette.navMuted, fontSize: 12, lineHeight: 18, textAlign: 'center' },
});
