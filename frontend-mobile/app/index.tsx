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
import { loginMobile } from '@/api/auth';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';

export default function MobileLoginScreen() {
  const router = useRouter();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleLogin() {
    if (!login.trim() || !password.trim()) {
      Alert.alert('Missing details', 'Enter your account ID and password.');
      return;
    }

    setLoading(true);

    try {
      const user = await loginMobile(login.trim(), password);
      const role = user.role?.role_key;

      if (role === 'household_resident') {
        router.replace('/household' as Href);
        return;
      }

      if (role === 'rescuer') {
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
              <Text style={styles.title}>Sign in</Text>
            </View>

            <View style={styles.fieldGroup}>
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
              onPress={() => Alert.alert('Password help', 'Please contact HQ/Admin to reset your mobile account password.')}
            >
              <Text style={styles.forgotText}>Forgot password?</Text>
            </Pressable>
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
    gap: spacing.lg,
    padding: spacing.lg,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  brandLogo: {
    width: 72,
    height: 42,
  },
  brandName: {
    color: palette.nav,
    fontSize: 21,
    fontWeight: '900',
  },
  brandRole: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
    ...shadow,
  },
  headerBlock: {
    gap: spacing.xs,
  },
  title: {
    color: palette.text,
    fontSize: 31,
    fontWeight: '900',
  },
  fieldGroup: {
    gap: 7,
  },
  label: {
    color: palette.text,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  inputShell: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: '#ffffff',
  },
  input: {
    flex: 1,
    minHeight: 50,
    color: palette.text,
    fontSize: 15,
    fontWeight: '800',
  },
  eyeButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
  },
  button: {
    minHeight: 52,
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
    fontSize: 15,
    fontWeight: '900',
  },
  forgotButton: {
    alignSelf: 'center',
    paddingVertical: 6,
    paddingHorizontal: 8,
  },
  forgotText: {
    color: palette.navActive,
    fontSize: 13,
    fontWeight: '900',
  },
});
