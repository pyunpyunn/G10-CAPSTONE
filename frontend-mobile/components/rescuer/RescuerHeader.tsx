import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing, typography } from '@/constants/resqTheme';

type HeaderProps = {
  onOpenRadio?: () => void;
};

export function RescuerHeader({ onOpenRadio }: HeaderProps) {
  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <Image
          source={require('@/assets/images/resqperation-logo.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <View style={styles.brandCopy}>
          <Text style={styles.brand}>RESQPERATION</Text>
          <Text style={styles.subtitle}>FIELD RESPONSE</Text>
        </View>
        <View style={styles.actions}>
          {onOpenRadio ? (
            <Pressable style={styles.iconButton} onPress={onOpenRadio}>
              <Ionicons name="call-outline" size={20} color={palette.navText} />
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    minHeight: 76,
    paddingTop: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.sm,
    backgroundColor: palette.nav,
    borderBottomWidth: 1,
    borderBottomColor: palette.navActive,
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
    fontSize: 16,
    fontWeight: typography.weights.bold,
    letterSpacing: 0.9,
  },
  brandCopy: {
    gap: 2,
  },
  subtitle: {
    color: palette.navMuted,
    fontSize: 9,
    fontWeight: typography.weights.medium,
    letterSpacing: 1.4,
  },
  actions: {
    marginLeft: 'auto',
    flexDirection: 'row',
    gap: spacing.sm,
  },
  iconButton: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#2a4f72',
    borderRadius: radius.md,
    backgroundColor: palette.navActive,
  },
});
