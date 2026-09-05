import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';

type HeaderProps = {
  onOpenRadio?: () => void;
};

export function RescuerHeader({ onOpenRadio }: HeaderProps) {
  return (
    <View style={styles.header}>
      <View style={styles.brandRow}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>R</Text>
        </View>
        <Text style={styles.brand}>RESQPERATION</Text>
        {onOpenRadio ? (
          <View style={styles.actions}>
            <Pressable style={styles.iconButton} onPress={onOpenRadio} accessibilityLabel="Open radio">
              <Ionicons name="call-outline" size={20} color={palette.navText} />
            </Pressable>
          </View>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    minHeight: 56,
    paddingTop: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.sm,
    backgroundColor: palette.nav,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
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
    flex: 1,
    color: '#fff',
    fontSize: 19,
    fontWeight: '900',
    letterSpacing: 0,
  },
  actions: {
    marginLeft: 'auto',
    flexDirection: 'row',
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
