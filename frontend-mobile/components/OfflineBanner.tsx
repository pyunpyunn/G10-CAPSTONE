import { Pressable, Text, View } from 'react-native';
import { useNetworkState } from 'expo-network';

export function OfflineBanner({
  offline,
  lastSyncedAt,
  loading,
  pendingStatuses = 0,
  failedStatuses = 0,
  onDiscardFailedStatuses,
}: {
  offline: boolean;
  lastSyncedAt: number | null;
  loading: boolean;
  pendingStatuses?: number;
  failedStatuses?: number;
  onDiscardFailedStatuses?: () => void;
}) {
  const network = useNetworkState();
  if (loading && !lastSyncedAt && !pendingStatuses && !failedStatuses) return null;
  if (!offline && network.isConnected !== false && network.isInternetReachable !== false && !pendingStatuses && !failedStatuses) return null;
  const isOffline = offline || network.isConnected === false || network.isInternetReachable === false;
  if (!isOffline && !failedStatuses) return null;
  const title = failedStatuses ? 'Status needs attention' : pendingStatuses && isOffline ? 'Status not synced' : 'Offline mode';
  const message = failedStatuses
    ? `${failedStatuses} report${failedStatuses === 1 ? '' : 's'} could not be saved to the server. Submit a fresh report after reviewing.`
    : pendingStatuses
      ? `${pendingStatuses} report${pendingStatuses === 1 ? '' : 's'} stored on this device and waiting to sync.`
      : lastSyncedAt ? `Last synced: ${new Date(lastSyncedAt).toLocaleString()}` : 'No saved data yet';
  return <View accessibilityLiveRegion="polite" style={{ backgroundColor: '#fff3cd', padding: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
    <Text style={{ color: '#664d03', fontWeight: '700' }}>{title}</Text>
    <Text style={{ color: '#664d03', textAlign: 'right', flex: 1 }}>{message}</Text>
    {failedStatuses > 0 && onDiscardFailedStatuses ? (
      <Pressable accessibilityRole="button" onPress={onDiscardFailedStatuses}>
        <Text style={{ color: '#664d03', fontWeight: '700' }}>Dismiss</Text>
      </Pressable>
    ) : null}
  </View>;
}
