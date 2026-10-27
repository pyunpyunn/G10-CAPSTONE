import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type ScrollView as ScrollViewType,
} from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { type Href, useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  lookupHouseholdRecoveryContact,
  resetHouseholdPassword,
  sendHouseholdRecoveryCode,
  validateHouseholdRecoveryContact,
} from '@/api/auth';
import { palette, spacing } from '@/constants/resqTheme';

export default function ForgotPasswordScreen() {
  const router = useRouter();
  const scrollView = useRef<ScrollViewType>(null);
  const params = useLocalSearchParams<{ login?: string }>();
  const initialLogin = typeof params.login === 'string' ? params.login : '';
  const [login, setLogin] = useState(initialLogin);
  const [maskedContact, setMaskedContact] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [validatedNumber, setValidatedNumber] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [accountError, setAccountError] = useState('');
  const [contactError, setContactError] = useState('');
  const [requestError, setRequestError] = useState('');
  const [loading, setLoading] = useState(false);
  const [codeSent, setCodeSent] = useState(false);
  const [sendCooldownSeconds, setSendCooldownSeconds] = useState(0);
  const sendAttempts = useRef(0);
  const sendInFlight = useRef(false);

  function scrollToFocusedInput(target: number) {
    requestAnimationFrame(() => {
      scrollView.current?.scrollResponderScrollNativeHandleToKeyboard(target, 28, true);
    });
  }

  useEffect(() => {
    if (initialLogin.trim()) void lookupAccount(initialLogin.trim());
  }, [initialLogin]);

  useEffect(() => {
    if (sendCooldownSeconds <= 0) {
      sendAttempts.current = 0;
      return;
    }

    const timer = setTimeout(() => {
      setSendCooldownSeconds((seconds) => Math.max(0, seconds - 1));
    }, 1000);
    return () => clearTimeout(timer);
  }, [sendCooldownSeconds]);

  function resetSendCooldown() {
    sendAttempts.current = 0;
    setSendCooldownSeconds(0);
  }

  async function lookupAccount(accountLogin: string) {
    setLoading(true);
    setAccountError('');
    setMaskedContact('');
    setValidatedNumber('');
    setCodeSent(false);
    setRequestError('');
    try {
      const response = await lookupHouseholdRecoveryContact(accountLogin);
      setMaskedContact(response.masked_contact_number);
    } catch (error: any) {
      setAccountError(authErrorMessage(error, 'Could not verify this account ID. Check the account ID and try again.'));
    } finally {
      setLoading(false);
    }
  }

  function handleLoginChange(value: string) {
    resetSendCooldown();
    setLogin(value);
    setMaskedContact('');
    setValidatedNumber('');
    setCodeSent(false);
    setContactError('');
    setRequestError('');
  }

  function handleContactChange(value: string) {
    resetSendCooldown();
    setContactNumber(value);
    setValidatedNumber('');
    setCodeSent(false);
    setContactError('');
    setRequestError('');
  }

  async function validateContact() {
    const accountLogin = login.trim();
    const digits = contactNumber.replace(/\D/g, '');
    if (!accountLogin) {
      setContactError('Enter your user ID first.');
      return;
    }
    if (!(digits.length === 11 && digits.startsWith('09'))
      && !(digits.length === 10 && digits.startsWith('9'))
      && !(digits.length === 12 && digits.startsWith('63'))) {
      setContactError('Enter a valid Philippine mobile number.');
      return;
    }

    setLoading(true);
    setContactError('');
    setValidatedNumber('');
    setCodeSent(false);
    try {
      await validateHouseholdRecoveryContact(accountLogin, contactNumber);
      setValidatedNumber(contactNumber.trim());
    } catch (error: any) {
      setContactError(authErrorMessage(error, 'This number does not match a phone number registered to this account.'));
    } finally {
      setLoading(false);
    }
  }

  async function requestCode() {
    if (sendInFlight.current || sendCooldownSeconds > 0) return;
    if (!validatedNumber || validatedNumber !== contactNumber.trim()) {
      setContactError('Verify your registered phone number before requesting a code.');
      return;
    }
    sendInFlight.current = true;
    const attempt = ++sendAttempts.current;
    if (attempt >= 3) setSendCooldownSeconds(60);
    setLoading(true);
    setRequestError('');
    try {
      const response = await sendHouseholdRecoveryCode(login.trim(), validatedNumber);
      if (response.cooldown) {
        setSendCooldownSeconds(Math.max(1, Number(response.cooldown_seconds) || 60));
        setRequestError('Please wait before requesting another verification code.');
        return;
      }
      setCodeSent(true);
      setRequestError(attempt >= 3
        ? 'A verification code was sent. Please wait one minute before requesting another code.'
        : 'A verification code was sent by SMS.');
    } catch (error: any) {
      if (error?.response?.status === 429) {
        setSendCooldownSeconds(60);
        setRequestError('Please wait one minute before requesting another verification code.');
      } else {
        setRequestError(authErrorMessage(error, 'Unable to send the code. Please try again later.'));
      }
    } finally {
      sendInFlight.current = false;
      setLoading(false);
    }
  }

  async function handleResetPassword() {
    setRequestError('');
    if (!/^\d{6}$/.test(code)) {
      setRequestError('Enter the 6-digit code from the SMS.');
      return;
    }
    if (password.length < 8 || password.length > 128) {
      setRequestError('Your new password must contain 8 to 128 characters.');
      return;
    }
    if (password !== confirmation) {
      setRequestError('The new passwords do not match.');
      return;
    }

    setLoading(true);
    try {
      const response = await resetHouseholdPassword({
        login: login.trim(),
        contact_number: validatedNumber,
        code,
        password,
        password_confirmation: confirmation,
      });
      Alert.alert(
        'Password changed',
        `Password for ${response.account_identifier || response.household_id} is changed!`,
        [{ text: 'Close', onPress: () => router.replace('/' as Href) }],
        { cancelable: false },
      );
    } catch (error: any) {
      setRequestError(authErrorMessage(error, 'Unable to change your password. Check your code and try again.'));
    } finally {
      setLoading(false);
    }
  }

  const numberHint = maskedContact
    ? `enter your phone number ${maskedContact}`
    : 'Enter your user ID to find your registered phone number.';

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView style={styles.keyboard} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          ref={scrollView}
          style={styles.scroll}
          contentContainerStyle={styles.screen}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
        >
          <View style={styles.card}>
            <View style={styles.header}>
              <Ionicons name="key-outline" size={30} color={palette.navText} />
              <Text style={styles.title}>Forgot password</Text>
              <Text style={styles.help}>Verify the phone number registered to your system account to receive a password reset code.</Text>
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>User ID</Text>
              <View style={styles.inputShell}>
                <Ionicons name="person-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={login}
                  onFocus={(event) => scrollToFocusedInput(event.nativeEvent.target)}
                  onChangeText={handleLoginChange}
                  onBlur={() => { if (login.trim()) void lookupAccount(login.trim()); }}
                  placeholder="Enter your account ID"
                  placeholderTextColor={palette.navMuted}
                  autoCapitalize="none"
                  autoCorrect={false}
                  editable={!loading}
                />
              </View>
              {!!accountError && <Text accessibilityLiveRegion="polite" style={styles.error}>{accountError}</Text>}
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.hint}>{numberHint}</Text>
              <View style={styles.inputShell}>
                <Ionicons name="call-outline" size={19} color={palette.navMuted} />
                <TextInput
                  style={styles.input}
                  value={contactNumber}
                  onFocus={(event) => scrollToFocusedInput(event.nativeEvent.target)}
                  onChangeText={handleContactChange}
                  onBlur={() => { if (maskedContact) void validateContact(); }}
                  placeholder="09XX XXX XXXX"
                  placeholderTextColor={palette.navMuted}
                  keyboardType="phone-pad"
                  autoComplete="tel"
                  editable={Boolean(maskedContact) && !loading}
                />
              </View>
              {!!contactError && <Text accessibilityLiveRegion="polite" style={styles.error}>{contactError}</Text>}
            </View>

            {validatedNumber && !codeSent ? (
              <Pressable
                style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, (loading || sendCooldownSeconds > 0) && styles.buttonDisabled]}
                onPress={() => { void requestCode(); }}
                disabled={loading || sendCooldownSeconds > 0}
              >
                {loading ? <ActivityIndicator color="#fff" /> : (
                  <>
                    <Ionicons name="chatbubble-ellipses-outline" size={19} color="#fff" />
                    <Text style={styles.buttonText}>{sendCooldownSeconds > 0
                      ? `Try again in ${sendCooldownSeconds}s`
                      : 'Get code via SMS'}</Text>
                  </>
                )}
              </Pressable>
            ) : null}

            {codeSent ? (
              <>
                <Pressable
                  style={[styles.resendButton, (loading || sendCooldownSeconds > 0) && styles.buttonDisabled]}
                  onPress={() => { void requestCode(); }}
                  disabled={loading || sendCooldownSeconds > 0}
                >
                  <Text style={styles.resendText}>{sendCooldownSeconds > 0
                    ? `Try again in ${sendCooldownSeconds}s`
                    : 'Get code'}</Text>
                </Pressable>
                <View style={styles.fieldGroup}>
                  <Text style={styles.label}>SMS verification code</Text>
                  <View style={styles.inputShell}>
                    <Ionicons name="shield-checkmark-outline" size={19} color={palette.navMuted} />
                    <TextInput
                      style={styles.input}
                      value={code}
                      onFocus={(event) => scrollToFocusedInput(event.nativeEvent.target)}
                      onChangeText={(value) => setCode(value.replace(/\D/g, '').slice(0, 6))}
                      placeholder="Enter 6-digit code"
                      placeholderTextColor={palette.navMuted}
                      keyboardType="number-pad"
                      autoComplete="sms-otp"
                      textContentType="oneTimeCode"
                      maxLength={6}
                    />
                  </View>
                </View>
                <View style={styles.fieldGroup}>
                  <Text style={styles.label}>New password</Text>
                  <View style={styles.inputShell}>
                    <TextInput
                      style={styles.input}
                      value={password}
                      onFocus={(event) => scrollToFocusedInput(event.nativeEvent.target)}
                      onChangeText={setPassword}
                      placeholder="8 to 128 characters"
                      placeholderTextColor={palette.navMuted}
                      secureTextEntry
                      autoComplete="new-password"
                      maxLength={128}
                    />
                  </View>
                </View>
                <View style={styles.fieldGroup}>
                  <Text style={styles.label}>Confirm new password</Text>
                  <View style={styles.inputShell}>
                    <TextInput
                      style={styles.input}
                      value={confirmation}
                      onFocus={(event) => scrollToFocusedInput(event.nativeEvent.target)}
                      onChangeText={setConfirmation}
                      placeholder="Re-enter your new password"
                      placeholderTextColor={palette.navMuted}
                      secureTextEntry
                      autoComplete="new-password"
                      maxLength={128}
                    />
                  </View>
                </View>
                <Pressable
                  style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, loading && styles.buttonDisabled]}
                  onPress={() => { void handleResetPassword(); }}
                  disabled={loading}
                >
                  {loading ? <ActivityIndicator color="#fff" /> : (
                    <>
                      <Ionicons name="lock-closed-outline" size={19} color="#fff" />
                      <Text style={styles.buttonText}>Change password</Text>
                    </>
                  )}
                </Pressable>
              </>
            ) : null}

            {!!requestError && <Text accessibilityLiveRegion="polite" style={styles.status}>{requestError}</Text>}
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
  safe: { flex: 1, backgroundColor: palette.nav },
  keyboard: { flex: 1 },
  scroll: { flex: 1 },
  screen: { flexGrow: 1, justifyContent: 'flex-start', gap: spacing.lg, paddingHorizontal: spacing.lg, paddingTop: spacing.md, paddingBottom: spacing.xl },
  card: { gap: spacing.md },
  header: { gap: spacing.sm, marginBottom: spacing.sm },
  title: { color: palette.navText, fontSize: 28, fontWeight: '800' },
  help: { color: palette.navMuted, fontSize: 13, lineHeight: 20 },
  fieldGroup: { gap: 8 },
  label: { color: palette.navMuted, fontSize: 11, fontWeight: '800', letterSpacing: 0.8, textTransform: 'uppercase' },
  hint: { color: palette.navText, fontSize: 14, fontWeight: '700' },
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
  input: { flex: 1, minHeight: 52, color: palette.navText, fontSize: 15, fontWeight: '600' },
  error: { color: '#ff9797', fontSize: 13, lineHeight: 18 },
  status: { color: palette.navMuted, fontSize: 13, lineHeight: 19 },
  button: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderRadius: 11,
    backgroundColor: palette.brandRed,
  },
  buttonPressed: { backgroundColor: '#a5262b' },
  buttonDisabled: { opacity: 0.7 },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '800' },
  resendButton: { minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  resendText: { color: palette.navText, fontSize: 14, fontWeight: '700', textDecorationLine: 'underline' },
});
