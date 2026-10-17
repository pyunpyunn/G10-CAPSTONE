import { Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import ResQperationLogo from '@/components/ResQperationLogo';
import { palette, spacing } from '@/constants/resqTheme';
import { HouseholdBadge } from './HouseholdUI';

export function HouseholdHeader({ isDisasterMode, notificationCount = 0, onOpenNotifications }: {
  isDisasterMode: boolean;
  notificationCount?: number;
  onOpenNotifications?: () => void;
}) {
  const { width: viewportWidth } = useWindowDimensions();

  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <ResQperationLogo width={Math.min(180, Math.max(110, viewportWidth - 210))} />
        <View style={styles.actions}>
          <View style={styles.disasterStatusBadge}>
            <HouseholdBadge label={isDisasterMode ? 'Disaster mode' : 'Standby'} tone={isDisasterMode ? 'danger' : 'safe'} size="large" />
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
    </View>
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
  actions: {
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
    backgroundColor: palette.brandRed,
  },
});
