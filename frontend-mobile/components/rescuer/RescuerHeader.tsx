import { SafeAreaView } from 'react-native-safe-area-context';
import ResQperationLogo from '@/components/ResQperationLogo';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, radius, spacing } from '@/constants/resqTheme';

type HeaderProps = {
  onOpenRadio?: () => void;
};

export function RescuerHeader({ onOpenRadio }: HeaderProps) {
  return (
    <SafeAreaView edges={['top']} style={styles.header}>
      <View style={styles.brandRow}>
        <View style={styles.wordmark}>
          <ResQperationLogo width="100%" height={44} />
        </View>
        <View style={styles.actions}>
          {onOpenRadio ? (
            <Pressable style={styles.iconButton} onPress={onOpenRadio}>
              <Ionicons name="call-outline" size={20} color={palette.navText} />
            </Pressable>
          ) : null}
        </View>
      </View>
    </SafeAreaView>
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
