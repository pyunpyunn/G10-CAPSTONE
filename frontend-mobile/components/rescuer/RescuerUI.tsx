import { ActivityIndicator, Modal, Pressable, ScrollView, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useState, type ReactNode } from 'react';
import { palette, radius, spacing, typography } from '@/constants/resqTheme';
import { statusColor as unifiedStatusColor } from '@/constants/statusPalette';

type ButtonProps = {
  label: string;
  icon?: keyof typeof Ionicons.glyphMap;
  tone?: 'primary' | 'light' | 'danger';
  disabled?: boolean;
  onPress: () => void;
};

export function ActionButton({ label, icon, tone = 'primary', disabled = false, onPress }: ButtonProps) {
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
      disabled={disabled}
    >
      {icon ? <Ionicons name={icon} size={17} color={tone === 'light' ? palette.nav : '#fff'} /> : null}
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

export type SelectOption = {
  key: string;
  label: string;
  description?: string;
};

export function RescuerSelect({
  title,
  placeholder,
  options,
  value,
  onChange,
  disabled = false,
  style,
  accessibilityLabel,
}: {
  title: string;
  placeholder: string;
  options: SelectOption[];
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
}) {
  const [visible, setVisible] = useState(false);
  const selected = options.find((option) => option.key === value);

  return (
    <View style={style}>
      <Pressable
        style={({ pressed }) => [
          styles.selectTrigger,
          disabled && styles.selectDisabled,
          pressed && !disabled && styles.selectTriggerPressed,
        ]}
        onPress={() => setVisible(true)}
        disabled={disabled}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel || title}
        accessibilityState={{ disabled, expanded: visible }}
      >
        <View style={styles.selectValueWrap}>
          <Text style={[styles.selectValue, !selected && styles.selectPlaceholder]} numberOfLines={1}>
            {selected?.label || placeholder}
          </Text>
        </View>
        <View style={styles.selectChevron}>
          <Ionicons name="chevron-down" size={17} color={palette.navActive} />
        </View>
      </Pressable>

      <Modal
        transparent
        visible={visible}
        animationType="fade"
        onRequestClose={() => setVisible(false)}
        statusBarTranslucent
      >
        <View style={styles.selectOverlay}>
          <Pressable style={StyleSheet.absoluteFill} onPress={() => setVisible(false)} accessibilityLabel="Close selector" />
          <View style={styles.selectSheet}>
            <View style={styles.selectHeader}>
              <View style={styles.selectHeaderCopy}>
                <Text style={styles.selectTitle}>{title}</Text>
                <Text style={styles.selectSubtitle}>
                  {selected ? `Selected: ${selected.label}` : 'Choose one option to continue.'}
                </Text>
              </View>
              <Pressable
                style={styles.selectClose}
                onPress={() => setVisible(false)}
                accessibilityRole="button"
                accessibilityLabel="Close choices"
              >
                <Ionicons name="close" size={19} color={palette.textSoft} />
              </Pressable>
            </View>
            <ScrollView style={styles.selectList} showsVerticalScrollIndicator={false}>
              {options.map((option, index) => {
                const isSelected = value === option.key;
                return (
                  <Pressable
                    key={option.key}
                    style={[
                      styles.selectOption,
                      index === 0 && styles.selectOptionFirst,
                      isSelected && styles.selectOptionActive,
                    ]}
                    onPress={() => {
                      onChange(option.key);
                      setVisible(false);
                    }}
                    accessibilityRole="radio"
                    accessibilityState={{ selected: isSelected }}
                  >
                    <View style={styles.selectOptionCopy}>
                      <Text style={[styles.selectOptionText, isSelected && styles.selectOptionTextActive]}>
                        {option.label}
                      </Text>
                      {option.description ? (
                        <Text style={styles.selectOptionDescription}>{option.description}</Text>
                      ) : null}
                    </View>
                    <View style={[styles.selectRadio, isSelected && styles.selectRadioActive]}>
                      {isSelected ? <View style={styles.selectRadioDot} /> : null}
                    </View>
                  </Pressable>
                );
              })}
            </ScrollView>
          </View>
        </View>
      </Modal>
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

function toneColor(tone: string) {
  return unifiedStatusColor(tone);
}

const styles = StyleSheet.create({
  button: {
    minHeight: 48,
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
    transform: [{ scale: 0.97 }],
    opacity: 0.78,
  },
  buttonText: {
    color: '#fff',
    fontSize: typography.sizes.body,
    fontWeight: typography.weights.semibold,
    letterSpacing: 0.1,
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
    fontSize: typography.sizes.caption,
    fontWeight: typography.weights.semibold,
    letterSpacing: 0.1,
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
    fontSize: typography.sizes.caption,
    fontWeight: typography.weights.medium,
    letterSpacing: 0.5,
  },
  statValue: {
    marginTop: 6,
    fontSize: typography.sizes.display,
    fontWeight: typography.weights.bold,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  sectionTitle: {
    color: palette.text,
    fontSize: typography.sizes.subtitle,
    fontWeight: typography.weights.semibold,
    letterSpacing: -0.2,
  },
  selectTrigger: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingVertical: spacing.xs,
    paddingLeft: spacing.md,
    paddingRight: spacing.sm,
    backgroundColor: palette.card,
  },
  selectDisabled: {
    opacity: 0.55,
  },
  selectTriggerPressed: {
    borderColor: palette.navActive,
    backgroundColor: palette.secondary,
  },
  selectValueWrap: {
    flex: 1,
    gap: 2,
  },
  selectValue: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  selectPlaceholder: {
    color: palette.textSoft,
    fontWeight: '500',
  },
  selectChevron: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.sm,
    backgroundColor: palette.secondary,
  },
  selectOverlay: {
    flex: 1,
    justifyContent: 'center',
    backgroundColor: 'rgba(10, 21, 32, 0.48)',
    paddingHorizontal: spacing.lg,
  },
  selectSheet: {
    maxHeight: '78%',
    borderRadius: 24,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.sm,
    paddingBottom: spacing.lg,
    backgroundColor: palette.page,
  },
  selectHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.md,
  },
  selectHeaderCopy: {
    flex: 1,
    gap: 4,
  },
  selectTitle: {
    color: palette.text,
    fontSize: 19,
    fontWeight: '800',
  },
  selectSubtitle: {
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
  },
  selectClose: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  selectList: {
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.card,
  },
  selectOption: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
  },
  selectOptionFirst: {
    borderTopWidth: 0,
  },
  selectOptionActive: {
    backgroundColor: palette.secondary,
  },
  selectOptionCopy: {
    flex: 1,
    gap: 3,
  },
  selectOptionText: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '600',
  },
  selectOptionTextActive: {
    color: palette.navActive,
    fontWeight: '700',
  },
  selectOptionDescription: {
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
  },
  selectRadio: {
    width: 21,
    height: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderColor: palette.borderStrong,
    borderRadius: radius.pill,
  },
  selectRadioActive: {
    borderColor: palette.navActive,
  },
  selectRadioDot: {
    width: 11,
    height: 11,
    borderRadius: radius.pill,
    backgroundColor: palette.navActive,
  },
  centerState: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    padding: spacing.xl,
  },
  centerText: {
    color: palette.textSoft,
    fontWeight: typography.weights.medium,
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
    fontWeight: typography.weights.semibold,
  },
  emptyBody: {
    marginTop: 6,
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: typography.weights.regular,
    lineHeight: 18,
    textAlign: 'center',
  },
});
