import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';

type HeaderProps = {
  connectionLabel?: string;
  onRefresh: () => void;
};

export function HouseholdHeader({
  connectionLabel,
  onRefresh,
}: HeaderProps) {
  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>R</Text>
        </View>
        <Text style={styles.brand}>RESQPERATION</Text>
        <View style={styles.actions}>
          <View style={styles.signalPill}>
            <Ionicons name="cellular-outline" size={14} color={palette.navText} />
            <Text style={styles.signalText}>{connectionLabel || 'Offline'}</Text>
          </View>
          <Pressable style={styles.iconButton} onPress={onRefresh}>
            <Ionicons name="refresh-outline" size={20} color={palette.navText} />
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
  signalPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    borderWidth: 1,
    borderColor: '#2a4f72',
    borderRadius: radius.pill,
    paddingHorizontal: spacing.sm,
    paddingVertical: 7,
    backgroundColor: palette.navActive,
  },
  signalText: {
    color: palette.navText,
    fontSize: 11,
    fontWeight: '900',
  },
  logo: {
    width: 42,
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#8bd5dc',
    borderRadius: 21,
    backgroundColor: '#4bbbc4',
  },
  logoText: {
    color: '#fff',
    fontSize: 17,
    fontWeight: '900',
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
  iconButton: {
    width: 42,
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#2a4f72',
    borderRadius: radius.md,
    backgroundColor: palette.navActive,
  },
});
