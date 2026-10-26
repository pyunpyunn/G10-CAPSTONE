import { ActivityIndicator, Modal, StyleSheet, Text, View } from 'react-native';
import { palette, radius, spacing } from '@/constants/resqTheme';

export function MobileLogoutOverlay({ visible }: { visible: boolean }) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={() => {}}>
      <View style={styles.backdrop}>
        <View style={styles.card} accessibilityRole="alert">
          <ActivityIndicator size="large" color={palette.navActive} />
          <Text style={styles.label}>Logging out...</Text>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
    backgroundColor: '#0f1d2dcc',
  },
  card: {
    minWidth: 200,
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.xl,
    borderRadius: radius.lg,
    backgroundColor: palette.card,
  },
  label: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '800',
  },
});
