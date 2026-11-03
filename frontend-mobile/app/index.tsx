import { useEffect, useRef, useState } from 'react';
import {
  Animated,
  ActivityIndicator,
  Alert,
  Easing,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  useWindowDimensions,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { clearToken, getApiServerUrl, setApiServerUrl } from '@/api/client';
import { getRememberedCredentials, saveRememberedCredentials, getStoredItem } from '@/utils/secureStorage';
import { navigationMetadata } from '@/utils/navigationStore';
import { householdOwnerSelectionRequired, loginMobile, restoreMobileSession } from '@/api/auth';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { askStandardMobilePermissions } from '@/utils/mobilePermissions';
import ResQperationLogo from '@/components/ResQperationLogo';

export default function MobileLoginScreen() {
  const router = useRouter();
  const { width: viewportWidth } = useWindowDimensions();
  const logoEntrance = useRef(new Animated.Value(0)).current;
  const scrollView = useRef<ScrollView>(null);
  const focusedInput = useRef<number | null>(null);
  function scrollToFocusedInput() {
    requestAnimationFrame(() => {
      if (focusedInput.current !== null) {
        scrollView.current?.scrollResponderScrollNativeHandleToKeyboard(focusedInput.current, 24, true);
      }
    });
  }
  useEffect(() => {
    const listener = Keyboard.addListener('keyboardDidShow', scrollToFocusedInput);
    return () => listener.remove();
  }, []);
  function focusInput(target: number) {
    focusedInput.current = target;
    scrollToFocusedInput();
  }
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [rememberPassword, setRememberPassword] = useState(false);
  const [offlineSession, setOfflineSession] = useState(false);
  const [serverUrl, setServerUrl] = useState('');
  const [editingServer, setEditingServer] = useState(false);
  useEffect(() => { void getApiServerUrl().then((url) => { setServerUrl(url); if (!url) setEditingServer(true); }); }, []);
  useEffect(() => {
    if (Platform.OS !== 'web') void Promise.all([getStoredItem('resqperation_mobile_token'), navigationMetadata('team')])
      .then(([token, team]) => setOfflineSession(Boolean(token && team && team !== 'null'))).catch(() => {});
  }, []);
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
  const [checkingSession, setCheckingSession] = useState(Platform.OS !== 'web');
  const [sessionRole, setSessionRole] = useState('');
  const skeletonOpacity = useRef(new Animated.Value(0.45)).current;

  useEffect(() => {
    if (!checkingSession) return;
    const animation = Animated.loop(Animated.sequence([
      Animated.timing(skeletonOpacity, { toValue: 0.9, duration: 700, useNativeDriver: true }),
      Animated.timing(skeletonOpacity, { toValue: 0.45, duration: 700, useNativeDriver: true }),
    ]));
    animation.start();
    return () => animation.stop();
  }, [checkingSession, skeletonOpacity]);

  useEffect(() => {
    let active = true;
    void getStoredItem('resqperation_mobile_role')
      .then((role) => { if (active) setSessionRole(role || ''); })
      .catch((error) => console.warn('Cached role could not be loaded for the startup skeleton.', error));
    async function restoreSession() {
      try {
        const role = await restoreMobileSession();
        if (!active) return;
        if (role === 'household_resident') {
          router.replace((await householdOwnerSelectionRequired() ? '/household-owner' : '/household') as Href);
          return;
        }
        if (role === 'rescuer' || role === 'tl' || role === 'tm') {
          router.replace('/rescuer' as Href);
          return;
        }
      } catch {
        // Keep the login screen available if session restoration fails.
      } finally {
        if (active) setCheckingSession(false);
      }
    }
    void restoreSession();
    return () => { active = false; };
  }, [router]);

  async function handleLogin() {
    if (!login.trim() || !password.trim()) {
      Alert.alert('Missing details', 'Enter your user ID and password.');
      return;
    }

    setLoading(true);

    try {
      const user = await loginMobile(login.trim(), password);
      const role = user.role?.role_key;
      if (role === 'household_resident' || role === 'rescuer' || role === 'tl' || role === 'tm') {
        try { await saveRememberedCredentials(login, password, rememberPassword); }
        catch { Alert.alert('Signed in', 'Your password could not be remembered on this device. You can still continue.'); }
      }

      if (role === 'household_resident') {
        if (user.owner_selection_required) { router.replace('/household-owner' as Href); return; }
        await askStandardMobilePermissions('household_resident');
        router.replace('/household' as Href);
        return;
      }

      if (role === 'rescuer' || role === 'tl' || role === 'tm') {
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

  if (checkingSession) {
    return <SessionHomeSkeleton role={sessionRole} opacity={skeletonOpacity} />;
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        style={styles.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView ref={scrollView} style={styles.keyboard} contentContainerStyle={styles.screen}
          keyboardShouldPersistTaps="handled" keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}>
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
            <Pressable onPress={() => setEditingServer(!editingServer)} style={{ paddingVertical: 8 }}>
              <Text style={{ color: palette.navMuted }}>Server settings</Text>
            </Pressable>
            {editingServer && <View style={styles.fieldGroup}>
              <Text style={styles.label}>Server API address</Text>
              <TextInput style={[styles.input, { borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 8, padding: 12 }]}
                onFocus={(event) => focusInput(event.nativeEvent.target)}
                value={serverUrl} onChangeText={setServerUrl} autoCapitalize="none" autoCorrect={false}
                keyboardType="url" placeholder="https://your-server/api/v1" />
              <Pressable style={{ paddingVertical: 10 }} onPress={() => {
                void setApiServerUrl(serverUrl).then((url) => { setServerUrl(url); setEditingServer(false); setOfflineSession(false); Alert.alert('Server saved', 'You can now sign in.'); })
                  .catch((error) => Alert.alert('Server address', error.message));
              }}><Text style={{ color: '#1358a4', fontWeight: '700' }}>Save server address</Text></Pressable>
            </View>}
            <View style={styles.headerBlock}>
              <Text style={styles.title}>Sign in</Text>
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>Household ID / Rescuer ID</Text>
              <View style={styles.inputShell}>
                <Ionicons name="person-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={login}
                  onFocus={(event) => focusInput(event.nativeEvent.target)}
                  onChangeText={value => { edited.current = true; setLogin(value); }}
                  placeholder="Enter your household ID or rescuer ID"
                  placeholderTextColor={palette.navMuted}
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>Password</Text>
              <View style={styles.inputShell}>
                <Ionicons name="lock-closed-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={password}
                  onFocus={(event) => focusInput(event.nativeEvent.target)}
                  onChangeText={value => { edited.current = true; setPassword(value); }}
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

            {Platform.OS !== 'web' && <Pressable
              style={styles.rememberRow}
              disabled={loading}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: rememberPassword }}
              onPress={() => {
                const next = !rememberPassword; setRememberPassword(next);
                if (!next) void saveRememberedCredentials('', '', false).catch(() => Alert.alert('Unable to forget password', 'Please try again.'));
              }}
            ><Ionicons name={rememberPassword ? 'checkbox' : 'square-outline'} size={21} color={palette.brandRed} /><Text style={styles.rememberText}>Remember password</Text></Pressable>}
            {offlineSession && <Pressable style={{ paddingVertical: 14 }} onPress={() => router.push('/rescuer/navigation' as Href)}>
              <Text style={{ color: '#1358a4', fontWeight: '700' }}>Open saved offline navigation</Text>
            </Pressable>}
            <Pressable style={({ pressed }) => [styles.button, pressed && !loading && styles.buttonPressed, loading && styles.buttonDisabled]} onPress={handleLogin} disabled={loading}>
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
              style={{ alignItems: 'center', paddingVertical: spacing.sm }}
              disabled={loading}
              onPress={() => router.push(`/forgot-password?login=${encodeURIComponent(login.trim())}` as Href)}
            >
              <Text style={styles.modeButtonText}>Forgot password?</Text>
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function SessionHomeSkeleton({ role, opacity }: { role: string; opacity: Animated.Value }) {
  const household = role === 'household_resident';
  return (
    <SafeAreaView style={styles.skeletonSafe} edges={['top']}>
      <View style={styles.skeletonHeader}>
        <ResQperationLogo width={190} height={42} />
        <SkeletonBlock opacity={opacity} style={styles.skeletonHeaderAction} />
      </View>
      <ScrollView contentContainerStyle={styles.skeletonContent}>
        {household ? (
          <>
            <View style={styles.skeletonBroadcastCard}>
              <SkeletonBlock opacity={opacity} style={styles.skeletonBroadcastHeading} />
              <SkeletonBlock opacity={opacity} style={styles.skeletonLineLong} />
              <SkeletonBlock opacity={opacity} style={styles.skeletonLineMedium} />
              <SkeletonBlock opacity={opacity} style={styles.skeletonLineShort} />
            </View>
            <View style={styles.skeletonStats}>
              {[0, 1, 2].map((item) => <SkeletonBlock key={item} opacity={opacity} style={styles.skeletonStat} />)}
            </View>
            <SkeletonBlock opacity={opacity} style={styles.skeletonHouseholdCard} />
            <SkeletonBlock opacity={opacity} style={styles.skeletonListCard} />
          </>
        ) : (
          <>
            <View style={styles.skeletonIdentityCard}>
              <View style={styles.skeletonIdentityText}>
                <SkeletonBlock opacity={opacity} style={styles.skeletonLineLong} />
                <SkeletonBlock opacity={opacity} style={styles.skeletonLineMedium} />
              </View>
              <SkeletonBlock opacity={opacity} style={styles.skeletonStatus} />
              <SkeletonBlock opacity={opacity} style={styles.skeletonLineLong} />
            </View>
            <View style={styles.skeletonStats}>
              {[0, 1, 2].map((item) => <SkeletonBlock key={item} opacity={opacity} style={styles.skeletonStat} />)}
            </View>
            <SkeletonBlock opacity={opacity} style={styles.skeletonHouseholdCard} />
            <SkeletonBlock opacity={opacity} style={styles.skeletonListCard} />
          </>
        )}
      </ScrollView>
      <View style={styles.skeletonTabBar}>
        {Array.from({ length: household ? 5 : 4 }, (_, item) => (
          <SkeletonBlock key={item} opacity={opacity} style={styles.skeletonTab} />
        ))}
      </View>
    </SafeAreaView>
  );
}

function SkeletonBlock({ opacity, style }: { opacity: Animated.Value; style: StyleProp<ViewStyle> }) {
  return <Animated.View style={[styles.skeletonBlock, style, { opacity }]} />;
}

function authErrorMessage(error: any, fallback: string) {
  if (error?.response?.status === 429) {
    return 'Unable to log in right now. Please wait a moment and try again.';
  }
  const responseData = error?.response?.data;
  const validationMessage = Object.values(responseData?.errors || {}).flat()[0];
  return String(validationMessage || error?.userMessage || responseData?.message || error?.message || fallback);
}

const styles = StyleSheet.create({
  skeletonSafe: {
    flex: 1,
    backgroundColor: palette.page,
  },
  skeletonHeader: { minHeight: 72, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: spacing.lg, backgroundColor: palette.nav },
  skeletonHeaderAction: { width: 42, height: 34, borderRadius: radius.pill, backgroundColor: palette.navActive },
  skeletonContent: { flexGrow: 1, gap: spacing.md, padding: spacing.md, paddingBottom: 112 },
  skeletonBlock: { backgroundColor: palette.border, borderRadius: radius.md },
  skeletonBroadcastCard: { gap: spacing.md, overflow: 'hidden', borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.md, backgroundColor: palette.card },
  skeletonBroadcastHeading: { height: 42, margin: -spacing.md, marginBottom: 0, borderRadius: 0, backgroundColor: palette.nav },
  skeletonLineLong: { width: '88%', height: 16 },
  skeletonLineMedium: { width: '62%', height: 14 },
  skeletonLineShort: { width: '38%', height: 12 },
  skeletonStats: { flexDirection: 'row', gap: spacing.sm },
  skeletonStat: { flex: 1, height: 92, borderWidth: 1, borderColor: palette.border, backgroundColor: palette.card },
  skeletonIdentityCard: { minHeight: 126, flexDirection: 'row', flexWrap: 'wrap', alignContent: 'center', alignItems: 'center', gap: spacing.md, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.md, backgroundColor: palette.card },
  skeletonIdentityText: { flex: 1, gap: spacing.sm },
  skeletonStatus: { width: 78, height: 32, borderRadius: radius.pill },
  skeletonHouseholdCard: { height: 190, borderWidth: 1, borderColor: palette.border, backgroundColor: palette.card },
  skeletonListCard: { height: 170, borderWidth: 1, borderColor: palette.border, backgroundColor: palette.card },
  skeletonTabBar: { position: 'absolute', right: spacing.md, bottom: spacing.md, left: spacing.md, minHeight: 70, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-around', borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, paddingHorizontal: spacing.sm, backgroundColor: palette.nav },
  skeletonTab: { width: 34, height: 34, borderRadius: radius.md, backgroundColor: palette.navActive },
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
  modeButtonText: {
    color: palette.navText,
    fontSize: 13,
    fontWeight: '700',
  },
  rememberText: { color: palette.navMuted, fontSize: 13, fontWeight: '600' },
});
