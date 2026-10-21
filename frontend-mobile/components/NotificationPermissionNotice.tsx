import { useCallback, useEffect, useState } from 'react';
import { AppState, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { enableNotifications, readNotificationPermission } from '@/utils/pushNotifications';

export function NotificationPermissionNotice() {
  const [permission, setPermission] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const refresh = useCallback(() => { void readNotificationPermission().then(setPermission).catch(() => {}); }, []);

  useEffect(() => {
    refresh();
    const listener = AppState.addEventListener('change', (state) => { if (state === 'active') refresh(); });
    return () => listener.remove();
  }, [refresh]);

  if (permission !== false) return null;
  return <SafeAreaView edges={['top']} style={styles.container}>
    <View style={styles.notice} accessibilityRole="alert">
      <View style={styles.copy}>
        <Text style={styles.title}>Turn on notifications</Text>
        <Text style={styles.body}>Receive disaster broadcasts and rescue assignments.</Text>
        {!!error && <Text style={styles.body}>{error}</Text>}
      </View>
      <Pressable accessibilityRole="button" accessibilityLabel="Enable notifications or open notification settings"
        disabled={busy} style={styles.button} onPress={async () => {
          setBusy(true); setError('');
          try { setPermission(await enableNotifications()); }
          catch { setError('Open your phone settings to allow RESQPERATION notifications.'); }
          finally { setBusy(false); }
        }}>
        <Text style={styles.buttonText}>{busy ? 'Opening…' : 'Enable'}</Text>
      </Pressable>
    </View>
  </SafeAreaView>;
}

const styles = StyleSheet.create({
  container: { backgroundColor: '#fff3ce' },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 10 },
  copy: { flex: 1 },
  title: { color: '#473600', fontSize: 14, fontWeight: '700' },
  body: { color: '#473600', fontSize: 12, marginTop: 3 },
  button: { minHeight: 44, justifyContent: 'center', paddingHorizontal: 14, borderRadius: 8, backgroundColor: '#1358a4' },
  buttonText: { color: '#fff', fontWeight: '700' },
});
