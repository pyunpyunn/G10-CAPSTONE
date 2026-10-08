import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, spacing } from '@/constants/resqTheme';
import { HouseholdBadge } from './HouseholdUI';

export function HouseholdHeader({ isDisasterMode, notificationCount = 0, onOpenNotifications }: {
  isDisasterMode: boolean;
  notificationCount?: number;
  onOpenNotifications?: () => void;
}) {
  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <Image
          source={require('@/assets/images/resqperation-logo.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <Text style={styles.brand}>RESQPERATION</Text>
        <View style={styles.actions}>
          <HouseholdBadge label={isDisasterMode ? 'Disaster mode' : 'Standby'} tone={isDisasterMode ? 'danger' : 'safe'} />
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
  logo: {
    width: 48,
    height: 42,
  },
  brand: {
    color: '#fff',
    fontSize: 19,
    fontWeight: '900',
  },
  actions: {
    marginLeft: 'auto',
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
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
