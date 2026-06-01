import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { type Href, useRouter } from 'expo-router';
import { clearToken } from '@/api/client';
import { loginMobile } from '@/api/auth';

export default function MobileLoginScreen() {
  const router = useRouter();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
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
        error?.response?.data?.message ||
        error?.response?.data?.errors?.login?.[0] ||
        'Unable to sign in. Please check your account ID and password.';

      Alert.alert('Login failed', message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <View style={styles.screen}>
      <View style={styles.card}>
        <View style={styles.brandMark}>
          <Text style={styles.brandLetter}>R</Text>
        </View>

        <Text style={styles.title}>RESQPERATION Mobile</Text>
        <Text style={styles.copy}>
          Household and rescuer accounts only. HQ/Admin users should use the web dashboard.
        </Text>

        <TextInput
          style={styles.input}
          value={login}
          onChangeText={setLogin}
          placeholder="Account ID or email"
          autoCapitalize="none"
        />

        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          placeholder="Password"
          secureTextEntry
        />

        <Pressable style={styles.button} onPress={handleLogin} disabled={loading}>
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Sign in</Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#f5f7fb',
  },
  card: {
    gap: 14,
    borderRadius: 12,
    padding: 22,
    backgroundColor: '#ffffff',
  },
  brandMark: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 22,
    backgroundColor: '#287c77',
  },
  brandLetter: {
    color: '#ffffff',
    fontSize: 18,
    fontWeight: '900',
  },
  title: {
    color: '#172033',
    fontSize: 24,
    fontWeight: '900',
  },
  copy: {
    color: '#64748b',
    fontSize: 14,
    lineHeight: 20,
  },
  input: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: '#dbe3ec',
    borderRadius: 8,
    paddingHorizontal: 12,
    color: '#172033',
    backgroundColor: '#ffffff',
  },
  button: {
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    backgroundColor: '#1f3547',
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '900',
  },
});
