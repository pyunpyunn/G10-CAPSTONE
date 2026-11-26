import { statusColor as unifiedStatusColor } from '@/constants/statusPalette';
import { ActivityIndicator, FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
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

export function StatTile({ label, value, tone = 'neutral', action }: { label: string; value: string | number; tone?: string; action?: ReactNode }) {
  const color = toneColor(tone);

  return (
    <View style={[styles.statTile, !!action && { minWidth: 160 }]}>
      <View style={[styles.sectionHeader, !!action && { gap: 4 }]}><Text style={styles.statLabel}>{label}</Text>{action}</View>
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
  highlightSelectedOption = false,
  triggerTone = 'default',
  triggerLabel,
  triggerVariant = 'default',
}: {
  title: string;
  placeholder: string;
  options: {
    key: string;
    label: string;
    icon?: keyof typeof MaterialCommunityIcons.glyphMap;
    travelDistanceKm?: number;
    travelDistanceEstimated?: boolean;
    travelTimes?: { mode: 'taxi' | 'motorcycle' | 'walk'; minutes: number }[];
  }[];
  value: string;
  onChange: (value: string) => void;
  accessibilityLabel?: string;
  showLabel?: boolean;
  highlightSelectedOption?: boolean;
  triggerTone?: 'default' | 'nav';
  triggerLabel?: string;
  triggerVariant?: 'default' | 'trustedAction';
}) {
  const [open, setOpen] = useState(false);
  const [hoveredKey, setHoveredKey] = useState<string | null>(null);
  const selected = options.find((option) => option.key === value)?.label || placeholder;

  return (
    <View style={styles.selectField}>
      {showLabel ? <Text style={styles.selectLabel}>{title}</Text> : null}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel || `${title}: ${selected}`}
        style={[
          styles.select,
          triggerTone === 'nav' && styles.selectNav,
          triggerVariant === 'trustedAction' && styles.selectTrustedAction,
        ]}
        onPress={() => setOpen(true)}
      >
        <Text style={[styles.selectValue, triggerTone === 'nav' && styles.selectValueNav]} numberOfLines={2}>{triggerLabel || selected}</Text>
        <Ionicons
          name={triggerVariant === 'trustedAction' ? 'arrow-forward' : 'chevron-down'}
          size={18}
          color={triggerTone === 'nav' ? palette.card : palette.textSoft}
        />
      </Pressable>

      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <View style={[styles.selectOverlay, highlightSelectedOption && styles.selectOverlayWide]}>
          <Pressable style={StyleSheet.absoluteFill} accessibilityLabel="Close list" onPress={() => setOpen(false)} />
          <View style={[styles.selectDialog, highlightSelectedOption && styles.selectDialogWide]} accessibilityViewIsModal>
            {highlightSelectedOption ? <View style={styles.selectHandle} /> : null}
            <View style={[styles.selectHeader, highlightSelectedOption && styles.selectHeaderWide]}>
              <Text style={[styles.selectTitle, highlightSelectedOption && styles.selectTitleWide]}>{title}</Text>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Close list"
                onPress={() => setOpen(false)}
                style={[styles.selectClose, highlightSelectedOption && styles.selectCloseWide]}
              >
                <Ionicons name="close" size={22} color={highlightSelectedOption ? palette.textSoft : palette.text} />
              </Pressable>
            </View>
            <FlatList
              style={highlightSelectedOption ? styles.selectListWide : undefined}
              data={options}
              keyExtractor={(option) => option.key}
              contentContainerStyle={highlightSelectedOption ? styles.selectListContentWide : undefined}
              showsVerticalScrollIndicator={highlightSelectedOption}
              scrollIndicatorInsets={highlightSelectedOption ? { top: 8, bottom: 8 } : undefined}
              renderItem={({ item }) => (
                <Pressable
                  accessibilityRole="radio"
                  accessibilityState={{ checked: value === item.key }}
                  style={[
                    styles.selectOption,
                    highlightSelectedOption && styles.selectOptionWide,
                    highlightSelectedOption && value === item.key && styles.selectOptionSelected,
                    highlightSelectedOption && hoveredKey === item.key && styles.selectOptionHovered,
                  ]}
                  onHoverIn={() => setHoveredKey(item.key)}
                  onHoverOut={() => setHoveredKey(null)}
                  onPress={() => {
                    onChange(item.key);
                    setOpen(false);
                  }}
                >
                  {highlightSelectedOption ? (
                    <>
                      <View style={styles.selectOptionIcon}>
                        <MaterialCommunityIcons name={item.icon || 'domain'} size={29} color={palette.navActive} />
                      </View>
                      <View style={styles.selectOptionContent}>
                        <Text style={[
                          styles.selectOptionText,
                          styles.selectOptionTextWide,
                          value === item.key && styles.selectOptionTextSelected,
                        ]} numberOfLines={2}>{item.label}</Text>
                        <View style={styles.selectOptionFooter}>
                          {item.travelDistanceKm != null || item.travelTimes?.length ? (
                            <View style={styles.selectTravelTimes}>
                              {item.travelDistanceKm != null ? (
                                <View style={[styles.selectTravelTime, styles.selectTravelTimeDivider]}>
                                  <MaterialCommunityIcons name="map-marker-distance" size={13} color={palette.textSoft} />
                                  <Text style={styles.selectTravelTimeText}>
                                    {item.travelDistanceEstimated ? '~' : ''}{item.travelDistanceKm.toFixed(2)} km
                                  </Text>
                                </View>
                              ) : null}
                              {item.travelTimes?.map((travelTime) => (
                                <View
                                  key={travelTime.mode}
                                  style={[
                                    styles.selectTravelTime,
                                    travelTime.mode !== 'walk' && styles.selectTravelTimeDivider,
                                  ]}
                                >
                                  <MaterialCommunityIcons
                                    name={travelModeIcons[travelTime.mode]}
                                    size={13}
                                    color={palette.textSoft}
                                  />
                                  <Text style={styles.selectTravelTimeText}>~{travelTime.minutes} min</Text>
                                </View>
                              ))}
                            </View>
                          ) : (
                            <Text style={styles.selectTravelUnavailable}>Starting point unavailable</Text>
                          )}
                        </View>
                      </View>
                    </>
                  ) : (
                    <Text style={styles.selectOptionText}>{item.label}</Text>
                  )}
                  {value === item.key ? (
                    highlightSelectedOption ? (
                      <View style={styles.selectOptionCheck}>
                        <Ionicons name="checkmark" size={16} color={palette.card} />
                      </View>
                    ) : <Ionicons name="checkmark" size={20} color={palette.navActive} />
                  ) : null}
                </Pressable>
              )}
            />
          </View>
        </View>
      </Modal>
    </View>
  );
}

const travelModeIcons = {
  taxi: 'taxi',
  motorcycle: 'motorbike',
  walk: 'walk',
} as const;

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
  selectNav: {
    minHeight: 50,
    borderWidth: 0,
    borderRadius: 12,
    paddingHorizontal: 16,
    backgroundColor: palette.nav,
  },
  selectTrustedAction: {
    width: '100%',
    minHeight: 56,
    justifyContent: 'flex-start',
    gap: spacing.sm,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
  },
  selectValue: {
    flex: 1,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  selectValueNav: {
    color: palette.card,
    fontSize: 15,
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
  selectOverlayWide: {
    paddingTop: 12,
    paddingHorizontal: 8,
    paddingBottom: 8,
  },
  selectDialogWide: {
    width: '100%',
    maxWidth: 560,
    maxHeight: '100%',
    alignSelf: 'center',
    borderRadius: 22,
    backgroundColor: palette.card,
  },
  selectHandle: {
    width: 42,
    height: 5,
    alignSelf: 'center',
    borderRadius: radius.pill,
    marginTop: spacing.sm,
    marginBottom: 4,
    backgroundColor: palette.borderStrong,
  },
  selectHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderColor: palette.border,
    paddingLeft: spacing.md,
  },
  selectHeaderWide: {
    minHeight: 60,
    paddingLeft: spacing.md,
  },
  selectTitle: {
    flex: 1,
    color: palette.text,
    fontSize: 17,
    fontWeight: '800',
  },
  selectTitleWide: {
    color: palette.nav,
    fontSize: 17,
  },
  selectClose: {
    padding: 14,
  },
  selectCloseWide: {
    padding: 14,
  },
  selectListWide: {
    flexGrow: 0,
    flexShrink: 1,
    backgroundColor: palette.page,
  },
  selectListContentWide: {
    paddingHorizontal: 6,
    paddingVertical: spacing.xs,
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
  selectOptionWide: {
    minHeight: 96,
    borderBottomWidth: 0,
    borderRadius: radius.lg,
    marginHorizontal: 4,
    paddingHorizontal: 16,
    paddingVertical: 16,
    marginVertical: 3,
    backgroundColor: palette.card,
    shadowColor: palette.nav,
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 2,
  },
  selectOptionSelected: {
    borderWidth: 1,
    borderColor: palette.navActive,
    backgroundColor: palette.secondary,
  },
  selectOptionHovered: {
    backgroundColor: palette.secondary,
  },
  selectOptionText: {
    flex: 1,
    color: palette.text,
    fontSize: 14,
  },
  selectOptionTextWide: {
    color: palette.nav,
    fontSize: 14,
    fontWeight: '800',
    lineHeight: 18,
    textAlign: 'left',
  },
  selectOptionTextSelected: {
    fontWeight: '900',
  },
  selectOptionIcon: {
    width: 64,
    height: 64,
    alignSelf: 'center',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: palette.secondary,
    marginRight: 10,
  },
  selectOptionContent: {
    flex: 1,
    alignSelf: 'center',
    justifyContent: 'space-between',
    minHeight: 64,
    paddingTop: spacing.sm,
    paddingBottom: spacing.xs,
  },
  selectOptionFooter: {
    alignItems: 'stretch',
    marginTop: 7,
    paddingTop: 2,
  },
  selectOptionCheck: {
    width: 24,
    height: 24,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.pill,
    marginLeft: 8,
    backgroundColor: palette.navActive,
  },
  selectTravelTimes: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'flex-start',
    columnGap: 5,
    rowGap: 4,
  },
  selectTravelTime: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 0,
    gap: 3,
  },
  selectTravelTimeDivider: {
    borderRightWidth: 1,
    borderRightColor: palette.borderStrong,
    marginRight: 0,
    paddingRight: 5,
  },
  selectTravelTimeText: {
    color: palette.textSoft,
    fontSize: 10,
    fontWeight: '700',
  },
  selectTravelUnavailable: {
    marginTop: 5,
    color: palette.textSoft,
    fontSize: 12,
  },
});
