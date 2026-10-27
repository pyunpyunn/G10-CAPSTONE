import { Text, View } from 'react-native';
import { useNetworkState } from 'expo-network';

export function OfflineBanner({ offline, lastSyncedAt }: { offline: boolean; lastSyncedAt: number | null }) {
  const network = useNetworkState();
  if (!offline && network.isConnected !== false && network.isInternetReachable !== false) return null;
  return <View accessibilityLiveRegion="polite" style={{ backgroundColor: '#fff3cd', padding: 12 }}>
    <Text style={{ color: '#664d03', fontWeight: '700' }}>Offline mode</Text>
    <Text style={{ color: '#664d03' }}>{lastSyncedAt
      ? `Showing saved data. Last synced ${new Date(lastSyncedAt).toLocaleString()}.`
      : 'No synced data is available on this device yet. Connect to load this page.'}</Text>
  </View>;
}
