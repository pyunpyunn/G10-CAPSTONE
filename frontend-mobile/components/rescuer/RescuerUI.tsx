import { statusColor as unifiedStatusColor } from '@/constants/statusPalette';
import { ActivityIndicator, FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useState, type ReactNode } from 'react';
import { palette, radius, spacing } from '@/constants/resqTheme';

type ButtonProps = {
  label: string;
  icon?: keyof typeof Ionicons.glyphMap;
  tone?: 'primary' | 'light' | 'danger';
  disabled?: boolean;
  loading?: boolean;
  onPress: () => void;
};

export function ActionButton({ label, icon, tone = 'primary', disabled = false, loading = false, onPress }: ButtonProps) {
  const [isHovered, setIsHovered] = useState(false);
  const [isPressed, setIsPressed] = useState(false);

  return (
    <Pressable
      style={[
        styles.button,
        styles[tone],
        disabled && styles.disabled,
        isHovered && !disabled && styles.hovered,
        isPressed && !disabled && styles.pressed,
      ]}
      onPress={onPress}
      onPressIn={() => setIsPressed(true)}
      onPressOut={() => setIsPressed(false)}
      onHoverIn={() => setIsHovered(true)}
      onHoverOut={() => setIsHovered(false)}
      disabled={disabled || loading}
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
      android_ripple={{ color: '#ffffff33' }}
    >
      {loading ? <ActivityIndicator size="small" color={tone === 'light' ? palette.nav : '#fff'} /> : icon ? <Ionicons name={icon} size={17} color={tone === 'light' ? palette.nav : '#fff'} /> : null}
      <Text style={[styles.buttonText, tone === 'light' && styles.lightText]}>{label}</Text>
    </Pressable>
  );
}

export function StatusBadge({ label, tone = 'neutral' }: { label: string; tone?: string }) {
  const color = toneColor(tone);

  return (
    <View style={[styles.badge, { backgroundColor: `${color}18`, borderColor: `${color}55` }]}>
      <Text style={[styles.badgeText, { color }]}>{label}</Text>
    </View>
  );
}

export function StatTile({ label, value, tone = 'neutral' }: { label: string; value: string | number; tone?: string }) {
  const color = toneColor(tone);

  return (
    <View style={styles.statTile}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );
}

export function SectionHeader({ title, action }: { title: string; action?: ReactNode }) {
  return (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {action}
    </View>
  );
}

export function LoadingState({ label = 'Loading...' }: { label?: string }) {
  return (
    <View style={styles.centerState}>
      <ActivityIndicator color={palette.navActive} />
      <Text style={styles.centerText}>{label}</Text>
    </View>
  );
}

export function EmptyState({ icon, title, body }: { icon: keyof typeof Ionicons.glyphMap; title: string; body?: string }) {
  return (
    <View style={styles.empty}>
      <Ionicons name={icon} size={28} color={palette.navMuted} />
      <Text style={styles.emptyTitle}>{title}</Text>
      {body ? <Text style={styles.emptyBody}>{body}</Text> : null}
    </View>
  );
}

export function RescuerSelect({
  title,
  placeholder,
  options,
  value,
  onChange,
  accessibilityLabel,
  showLabel = true,
}: {
  title: string;
  placeholder: string;
  options: Array<{ key: string; label: string }>;
  value: string;
  onChange: (value: string) => void;
  accessibilityLabel?: string;
  showLabel?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.key === value)?.label || placeholder;

  return (
    <View style={styles.selectField}>
      {showLabel ? <Text style={styles.selectLabel}>{title}</Text> : null}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel || `${title}: ${selected}`}
        style={styles.select}
        onPress={() => setOpen(true)}
      >
        <Text style={styles.selectValue} numberOfLines={2}>{selected}</Text>
        <Ionicons name="chevron-down" size={18} color={palette.textSoft} />
      </Pressable>

      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <View style={styles.selectOverlay}>
          <Pressable style={StyleSheet.absoluteFill} accessibilityLabel="Close list" onPress={() => setOpen(false)} />
          <View style={styles.selectDialog} accessibilityViewIsModal>
            <View style={styles.selectHeader}>
              <Text style={styles.selectTitle}>{title}</Text>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Close list"
                onPress={() => setOpen(false)}
                style={styles.selectClose}
              >
                <Ionicons name="close" size={22} color={palette.text} />
              </Pressable>
            </View>

            <FlatList
              data={options}
              keyExtractor={(option) => option.key}
              renderItem={({ item }) => (
                <Pressable
                  accessibilityRole="radio"
                  accessibilityState={{ checked: value === item.key }}
                  style={styles.selectOption}
                  onPress={() => {
                    onChange(item.key);
                    setOpen(false);
                  }}
                >
                  <Text style={styles.selectOptionText}>{item.label}</Text>
                  {value === item.key ? <Ionicons name="checkmark" size={20} color={palette.navActive} /> : null}
                </Pressable>
              )}
            />
          </View>
        </View>
      </Modal>
    </View>
  );
}

function toneColor(tone: string) {
  return unifiedStatusColor(tone);
}

const styles = StyleSheet.create({
  button: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
  },
  primary: {
    backgroundColor: palette.navActive,
  },
  light: {
    borderWidth: 1,
    borderColor: palette.border,
    backgroundColor: palette.card,
  },
  danger: {
    backgroundColor: palette.unsafe,
  },
  disabled: {
    opacity: 0.6,
  },
  hovered: {
    borderWidth: 1,
    borderColor: palette.navActive,
    shadowColor: palette.navActive,
    shadowOpacity: 0.2,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
  },
  pressed: {
    transform: [{ scale: 0.98 }],
    opacity: 0.96,
  },
  buttonText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '900',
  },
  lightText: {
    color: palette.nav,
  },
  badge: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 9,
    paddingVertical: 4,
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '900',
  },
  statTile: {
    flex: 1,
    minWidth: 96,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  statLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  statValue: {
    marginTop: 6,
    fontSize: 24,
    fontWeight: '900',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  sectionTitle: {
    color: palette.text,
    fontSize: 16,
    fontWeight: '900',
  },
  centerState: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    padding: spacing.xl,
  },
  centerText: {
    color: palette.textSoft,
    fontWeight: '800',
  },
  empty: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.xl,
    backgroundColor: palette.card,
  },
  emptyTitle: {
    marginTop: 10,
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
  },
  emptyBody: {
    marginTop: 6,
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
  },
  selectField: {
    gap: 6,
  },
  selectLabel: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  select: {
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: '#fff',
  },
  selectValue: {
    flex: 1,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  selectOverlay: {
    flex: 1,
    justifyContent: 'center',
    padding: spacing.lg,
    backgroundColor: '#0008',
  },
  selectDialog: {
    backgroundColor: palette.card,
    borderRadius: radius.lg,
    maxHeight: '75%',
    overflow: 'hidden',
  },
  selectHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderColor: palette.border,
    paddingLeft: spacing.md,
  },
  selectTitle: {
    flex: 1,
    color: palette.text,
    fontSize: 17,
    fontWeight: '800',
  },
  selectClose: {
    padding: 14,
  },
  selectOption: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  selectOptionText: {
    flex: 1,
    color: palette.text,
    fontSize: 14,
  },
});
