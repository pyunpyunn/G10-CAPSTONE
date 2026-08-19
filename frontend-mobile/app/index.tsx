import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { clearToken } from '@/api/client';
import { getRecoveryQuestions, loginMobile, resetMobilePassword } from '@/api/auth';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { askStandardMobilePermissions } from '@/utils/mobilePermissions';

export default function MobileLoginScreen() {
  const router = useRouter();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [remember, setRemember] = useState(false);
  const [recoveryOpen, setRecoveryOpen] = useState(false);
  const [recoveryMethod, setRecoveryMethod] = useState<'previous_password' | 'security_questions'>('previous_password');
  const [recoveryQuestions, setRecoveryQuestions] = useState<any>(null);
  const [previousPassword, setPreviousPassword] = useState('');
  const [answer1, setAnswer1] = useState('');
  const [answer2, setAnswer2] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');

  async function handleLogin() {
    if (!login.trim() || !password.trim()) {
      Alert.alert('Missing details', 'Enter your account ID and password.');
      return;
    }

    setLoading(true);

    try {
      const user = await loginMobile(login.trim(), password, remember);
      const role = user.role?.role_key;

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
      const message =
        error?.userMessage ||
        error?.response?.data?.message ||
        error?.response?.data?.errors?.login?.[0] ||
        'Unable to sign in. Please check your account ID and password.';

      Alert.alert('Login failed', message);
    } finally {
      setLoading(false);
    }
  }

  async function openRecovery() {
    setRecoveryOpen(true);

    if (!login.trim()) {
      return;
    }

    try {
      const data = await getRecoveryQuestions(login.trim());
      setRecoveryQuestions(data.questions);
    } catch (error: any) {
      Alert.alert('Recovery unavailable', error?.response?.data?.message || 'Enter a valid account ID and try again.');
    }
  }

  async function handleRecovery() {
    if (!login.trim() || !newPassword || newPassword !== newPasswordConfirmation) {
      Alert.alert('Missing details', 'Enter your account ID, a matching new password, and the verification details.');
      return;
    }

    try {
      const result = await resetMobilePassword({
        login: login.trim(),
        method: recoveryMethod,
        previous_password: previousPassword,
        answer_1: answer1,
        answer_2: answer2,
        password: newPassword,
        password_confirmation: newPasswordConfirmation,
      });
      Alert.alert('Password reset', result.message || 'Password reset successfully.', [{ text: 'Sign in', onPress: () => setRecoveryOpen(false) }]);
    } catch (error: any) {
      Alert.alert('Recovery failed', error?.response?.data?.message || 'The verification details are incorrect.');
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.screen} keyboardShouldPersistTaps="handled">
          <View style={styles.brandRow}>
            <Image
              source={require('@/assets/images/resqperation-logo.png')}
              style={styles.brandLogo}
              resizeMode="contain"
            />
            <View>
              <Text style={styles.brandName}>RESQPERATION</Text>
              <Text style={styles.brandRole}>Mobile access</Text>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.headerBlock}>
              <Text style={styles.title}>{recoveryOpen ? 'Reset password' : 'Sign in'}</Text>
            </View>

            {!recoveryOpen ? <><View style={styles.fieldGroup}>
              <Text style={styles.label}>Account ID</Text>
              <View style={styles.inputShell}>
                <Ionicons name="person-outline" size={19} color={palette.textSoft} />
                <TextInput
                  style={styles.input}
                  value={login}
                  onChangeText={setLogin}
                  placeholder="Example: 2024035501"
                  placeholderTextColor="#7d8da0"
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>Password</Text>
              <View style={styles.inputShell}>
                <Ionicons name="lock-closed-outline" size={19} color={palette.textSoft} />
                <TextInput
                  style={styles.input}
                  value={password}
                  onChangeText={setPassword}
                  placeholder="Enter password"
                  placeholderTextColor="#7d8da0"
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
                    color={palette.navActive}
                  />
                </Pressable>
              </View>
            </View>

            <Pressable style={styles.rememberRow} onPress={() => setRemember((current) => !current)}>
              <Ionicons name={remember ? 'checkbox' : 'square-outline'} size={20} color={palette.navActive} />
              <Text style={styles.forgotText}>Keep me signed in on this device</Text>
            </Pressable>

            <Pressable style={[styles.button, loading && styles.buttonDisabled]} onPress={handleLogin} disabled={loading}>
              {loading ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <>
                  <Ionicons name="log-in-outline" size={19} color="#fff" />
                  <Text style={styles.buttonText}>Login</Text>
                </>
              )}
            </Pressable>

            <Pressable
              style={styles.forgotButton}
              onPress={openRecovery}
            >
              <Text style={styles.forgotText}>Forgot password?</Text>
            </Pressable></> : <>
              <View style={styles.fieldGroup}><Text style={styles.label}>Account ID</Text><TextInput style={styles.recoveryInput} value={login} onChangeText={setLogin} placeholder="Account ID" /></View>
              <View style={styles.recoveryChoice}><Pressable onPress={() => setRecoveryMethod('previous_password')}><Text style={styles.forgotText}>{recoveryMethod === 'previous_password' ? '● ' : '○ '}Previous password</Text></Pressable><Pressable onPress={() => setRecoveryMethod('security_questions')}><Text style={styles.forgotText}>{recoveryMethod === 'security_questions' ? '● ' : '○ '}Two questions</Text></Pressable></View>
              {recoveryMethod === 'previous_password' ? <TextInput style={styles.recoveryInput} value={previousPassword} onChangeText={setPreviousPassword} placeholder="Previous password" secureTextEntry /> : <><Text style={styles.question}>{recoveryQuestions?.first || 'First security question'}</Text><TextInput style={styles.recoveryInput} value={answer1} onChangeText={setAnswer1} placeholder="Answer" /><Text style={styles.question}>{recoveryQuestions?.second || 'Second security question'}</Text><TextInput style={styles.recoveryInput} value={answer2} onChangeText={setAnswer2} placeholder="Answer" /></>}
              <TextInput style={styles.recoveryInput} value={newPassword} onChangeText={setNewPassword} placeholder="New password" secureTextEntry /><TextInput style={styles.recoveryInput} value={newPasswordConfirmation} onChangeText={setNewPasswordConfirmation} placeholder="Confirm new password" secureTextEntry />
              <Pressable style={styles.button} onPress={handleRecovery}><Text style={styles.buttonText}>Reset password</Text></Pressable><Pressable style={styles.forgotButton} onPress={() => setRecoveryOpen(false)}><Text style={styles.forgotText}>Back to sign in</Text></Pressable>
            </>}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: palette.page,
  },
  keyboard: {
    flex: 1,
  },
  screen: {
    flexGrow: 1,
    justifyContent: 'center',
    gap: spacing.md,
    padding: spacing.md,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  brandLogo: {
    width: 56,
    height: 32,
  },
  brandName: {
    color: palette.nav,
    fontSize: 17,
    fontWeight: '800',
  },
  brandRole: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  card: {
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
    ...shadow,
  },
  headerBlock: {
    gap: spacing.xs,
  },
  title: {
    color: palette.text,
    fontSize: 22,
    fontWeight: '800',
  },
  fieldGroup: {
    gap: 6,
  },
  label: {
    color: palette.text,
    fontSize: 11,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  inputShell: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: '#ffffff',
  },
  input: {
    flex: 1,
    minHeight: 40,
    color: palette.text,
    fontSize: 14,
    fontWeight: '500',
  },
  eyeButton: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
  },
  button: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    backgroundColor: palette.navActive,
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
  forgotButton: {
    alignSelf: 'center',
    paddingVertical: 4,
    paddingHorizontal: 8,
  },
  forgotText: {
    color: palette.navActive,
    fontSize: 12,
    fontWeight: '600',
  },
});
