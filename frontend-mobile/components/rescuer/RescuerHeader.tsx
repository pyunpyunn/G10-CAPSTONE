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
        <View style={styles.actions}>
          {onOpenRadio ? (
            <Pressable style={styles.iconButton} onPress={onOpenRadio}>
              <Ionicons name="radio-outline" size={20} color={palette.navText} />
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    minHeight: 72,
    paddingTop: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
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
