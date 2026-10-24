import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, AppState, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette } from '@/constants/resqTheme';
import { enableNotifications, readNotificationPermission } from '@/utils/pushNotifications';

export function NotificationPermissionNotice() {
  const [permission, setPermission] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [dismissed, setDismissed] = useState(false);
  const refresh = useCallback(() => { void readNotificationPermission().then(setPermission).catch(() => {}); }, []);

  useEffect(() => {
    refresh();
    const listener = AppState.addEventListener('change', (state) => { if (state === 'active') refresh(); });
    return () => listener.remove();
  }, [refresh]);

  const dismiss = () => { if (!busy) setDismissed(true); };
  return <Modal visible={permission === false && !dismissed} transparent animationType="fade"
    statusBarTranslucent onRequestClose={dismiss}>
    <SafeAreaView style={styles.overlay}>
      <ScrollView contentContainerStyle={styles.center} bounces={false}>
        <View style={styles.notice} accessibilityViewIsModal>
          <Pressable style={styles.close} accessibilityRole="button" accessibilityLabel="Dismiss notification reminder"
            disabled={busy} onPress={dismiss}><Ionicons name="close" size={23} color={palette.navMuted} /></Pressable>
          <View style={styles.icon}><Ionicons name="notifications-outline" size={30} color="#fff" /></View>
          <Text style={styles.eyebrow}>STAY CONNECTED</Text>
          <Text style={styles.title} accessibilityRole="header">Turn on notifications</Text>
          <Text style={styles.body}>Get disaster alerts, rescue assignments and important updates from your team.</Text>
          {!!error && <Text style={styles.error} accessibilityLiveRegion="polite">{error}</Text>}
          <Pressable accessibilityRole="button" accessibilityLabel="Enable notifications or open notification settings"
            disabled={busy} style={[styles.button, busy && styles.disabled]} onPress={async () => {
              setBusy(true); setError('');
              try {
                const granted = await enableNotifications();
                setPermission(granted);
                if (!granted) setError('Notifications are still off. Allow them in your phone settings, or continue for now.');
              } catch { setError('Open your phone settings to allow RESQPERATION notifications.'); }
              finally { setBusy(false); }
            }}>
            {busy && <ActivityIndicator color="#fff" size="small" />}
            <Text style={styles.buttonText}>{busy ? 'Opening…' : 'Enable notifications'}</Text>
          </Pressable>
          <Pressable accessibilityRole="button" disabled={busy} style={styles.later} onPress={dismiss}>
            <Text style={styles.laterText}>Not now</Text>
          </Pressable>
        </View>
      </ScrollView>
    </SafeAreaView>
  </Modal>;
}

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(3, 10, 19, 0.76)' },
  center: { flexGrow: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  notice: { width: '100%', maxWidth: 420, backgroundColor: palette.nav, borderWidth: 1, borderColor: '#385670', borderRadius: 22, padding: 26, paddingTop: 32 },
  close: { position: 'absolute', top: 6, right: 6, width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  icon: { width: 60, height: 60, borderRadius: 18, backgroundColor: palette.brandRed, alignItems: 'center', justifyContent: 'center', marginBottom: 22 },
  eyebrow: { color: palette.navMuted, fontSize: 11, fontWeight: '800', letterSpacing: 1.4, marginBottom: 8 },
  title: { color: palette.navText, fontSize: 25, lineHeight: 32, fontWeight: '800', marginBottom: 12 },
  body: { color: palette.navMuted, fontSize: 15, lineHeight: 23, marginBottom: 24 },
  error: { color: '#ffb4b7', fontSize: 13, lineHeight: 20, marginBottom: 16 },
  button: { minHeight: 52, flexDirection: 'row', gap: 10, alignItems: 'center', justifyContent: 'center', padding: 14, borderRadius: 11, backgroundColor: palette.brandRed },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '800', flexShrink: 1 },
  disabled: { opacity: 0.65 },
  later: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 8 },
  laterText: { color: palette.navMuted, fontSize: 14, fontWeight: '600' },
});
