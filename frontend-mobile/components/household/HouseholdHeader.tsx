import { Image, StyleSheet, Text, View } from 'react-native';
import { palette, spacing } from '@/constants/resqTheme';
import { HouseholdBadge } from './HouseholdUI';

export function HouseholdHeader({ isDisasterMode }: { isDisasterMode: boolean }) {
  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <Image
          source={require('@/assets/images/resqperation-logo.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <Text style={styles.brand}>RESQPERATION</Text>
        <View style={styles.status}>
          <HouseholdBadge label={isDisasterMode ? 'Disaster mode' : 'Standby'} tone={isDisasterMode ? 'danger' : 'safe'} />
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
  status: {
    marginLeft: 'auto',
  },
});
