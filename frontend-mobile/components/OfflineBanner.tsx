import { Text, View } from 'react-native';
import { useNetworkState } from 'expo-network';

export function OfflineBanner({ offline, lastSyncedAt, loading }: { offline: boolean; lastSyncedAt: number | null; loading: boolean }) {
  const network = useNetworkState();
  if (loading && !lastSyncedAt) return null;
  if (!offline && network.isConnected !== false && network.isInternetReachable !== false) return null;
  return <View accessibilityLiveRegion="polite" style={{ backgroundColor: '#fff3cd', padding: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
    <Text style={{ color: '#664d03', fontWeight: '700' }}>Offline mode</Text>
    <Text style={{ color: '#664d03', textAlign: 'right', flex: 1 }}>{lastSyncedAt ? `Last synced: ${new Date(lastSyncedAt).toLocaleString()}` : 'No saved data yet'}</Text>
  </View>;
}
