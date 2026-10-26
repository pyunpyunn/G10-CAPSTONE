import { SafeAreaView } from 'react-native-safe-area-context';
import ResQperationLogo from '@/components/ResQperationLogo';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, spacing } from '@/constants/resqTheme';
import { HouseholdBadge } from './HouseholdUI';

export function HouseholdHeader({ isDisasterMode, notificationCount = 0, onOpenNotifications }: {
  isDisasterMode: boolean;
  notificationCount?: number;
  onOpenNotifications?: () => void;
}) {
  return (
    <SafeAreaView edges={['top']} style={styles.header}>
      <View style={styles.brandRow}>
        <View style={styles.wordmark}>
          <ResQperationLogo width="100%" height={44} />
        </View>
        <View style={styles.actions}>
          <View style={styles.disasterStatusBadge}>
            <HouseholdBadge label={isDisasterMode ? 'Disaster mode' : 'Standby'} tone={isDisasterMode ? 'danger' : 'safe'} />
          </View>
          <Pressable
            style={styles.notificationButton}
            onPress={onOpenNotifications}
            accessibilityRole="button"
            accessibilityLabel={`Notifications${notificationCount ? `, ${notificationCount} unread` : ''}`}
          >
            <Ionicons name="notifications-outline" size={21} color="#fff" />
            {notificationCount > 0 ? <View style={styles.notificationDot} /> : null}
          </Pressable>
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingTop: spacing.xs,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xs,
    backgroundColor: palette.nav,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  wordmark: {
    flex: 1,
    flexShrink: 1,
    minWidth: 0,
    maxWidth: 220,
    height: 44,
  },
  actions: {
    flexShrink: 0,
    marginLeft: 'auto',
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  disasterStatusBadge: {
    alignSelf: 'center',
  },
  notificationButton: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 17,
    backgroundColor: '#ffffff20',
  },
  notificationDot: {
    position: 'absolute',
    top: 6,
    right: 7,
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: palette.unsafe,
  },
});
