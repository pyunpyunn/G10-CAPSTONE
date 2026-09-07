import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import type { ReactNode } from 'react';
import { palette, radius, spacing } from '@/constants/resqTheme';

type ButtonProps = {
  label: string;
  icon?: keyof typeof Ionicons.glyphMap;
  tone?: 'primary' | 'light' | 'danger' | 'gray';
  disabled?: boolean;
  onPress: () => void;
};

export function HouseholdButton({ label, icon, tone = 'primary', disabled = false, onPress }: ButtonProps) {
  return (
    <Pressable
      style={[styles.button, styles[tone], disabled && styles.disabled]}
      onPress={onPress}
      disabled={disabled}
    >
      {icon ? <Ionicons name={icon} size={17} color={tone === 'light' || tone === 'gray' ? palette.nav : '#fff'} /> : null}
      <Text style={[styles.buttonText, (tone === 'light' || tone === 'gray') && styles.darkButtonText]}>{label}</Text>
    </Pressable>
  );
}

export function HouseholdBadge({ label, tone = 'neutral' }: { label: string; tone?: string }) {
  const color = statusColor(tone);

  return (
    <View style={[styles.badge, { borderColor: `${color}55`, backgroundColor: `${color}18` }]}>
      <Text style={[styles.badgeText, { color }]}>{label}</Text>
    </View>
  );
}

export function HouseholdSection({ title, action }: { title: string; action?: ReactNode }) {
  return (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {action}
    </View>
  );
}

export function HouseholdLoading({ label = 'Loading...' }: { label?: string }) {
  return (
    <View style={styles.center}>
      <ActivityIndicator color={palette.navActive} />
      <Text style={styles.centerText}>{label}</Text>
    </View>
  );
}

export function HouseholdEmpty({ icon, title, body }: { icon: keyof typeof Ionicons.glyphMap; title: string; body?: string }) {
  return (
    <View style={styles.empty}>
      <Ionicons name={icon} size={28} color={palette.navMuted} />
      <Text style={styles.emptyTitle}>{title}</Text>
      {body ? <Text style={styles.emptyBody}>{body}</Text> : null}
    </View>
  );
}

export function statusColor(tone: string) {
  if (['safe', 'validated', 'active'].includes(tone)) return palette.safe;
  if (['evacuated', 'info'].includes(tone)) return palette.evacuated;
  if (['unsafe', 'needs_help', 'critical', 'danger'].includes(tone)) return palette.unsafe;
  if (['pending', 'warning'].includes(tone)) return palette.warning;
  return palette.textSoft;
}

const styles = StyleSheet.create({
  button: {
    minHeight: 46,
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
  gray: {
    backgroundColor: palette.secondary,
  },
  disabled: {
    opacity: 0.55,
  },
  buttonText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '900',
  },
  darkButtonText: {
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
  center: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    padding: spacing.xl,
  },
  centerText: {
    color: palette.textSoft,
    fontWeight: '800',
  },
  empty: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  emptyTitle: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '900',
  },
  emptyBody: {
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'center',
  },
});
