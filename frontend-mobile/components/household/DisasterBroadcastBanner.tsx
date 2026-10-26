import { Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, radius, spacing } from '@/constants/resqTheme';

type Broadcast = {
  title?: string | null;
  message?: string | null;
  estimated_duration?: string | null;
};

type Props = {
  event: { name?: string | null; type?: string | null };
  broadcast?: Broadcast | null;
  onOpenHistory?: () => void;
};

export function DisasterBroadcastBanner({ event, broadcast, onOpenHistory }: Props) {
  return (
    <View style={styles.card} accessibilityRole="alert">
      <View style={styles.heading}>
        <View style={styles.event}>
          <Text style={styles.eventName}>{event.name || '—'}</Text>
          {event.type ? <Text style={styles.eventType}>{event.type}</Text> : null}
        </View>
        {onOpenHistory ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Broadcast history" onPress={onOpenHistory} style={styles.history}>
            <Ionicons name="time-outline" size={20} color={palette.navText} />
          </Pressable>
        ) : null}
      </View>
      <View style={styles.body}>
        {broadcast?.title ? <Text style={styles.title}>{broadcast.title}</Text> : null}
        <View style={styles.instruction}>
          <Text style={styles.label}>Official instruction</Text>
          <Text style={styles.message} selectable>{broadcast?.message?.trim() || '—'}</Text>
        </View>
        <View style={styles.duration}>
          <Ionicons name="hourglass-outline" size={17} color={palette.textSoft} />
          <Text style={styles.durationLabel}>Est. duration</Text>
          <Text style={styles.durationValue}>{broadcast?.estimated_duration?.trim() || '—'}</Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, overflow: 'hidden', backgroundColor: palette.card },
  heading: { backgroundColor: palette.nav, padding: spacing.md, flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  event: { flex: 1, gap: 4 },
  eventName: { color: palette.navText, fontSize: 20, fontWeight: '700' },
  eventType: { color: palette.navMuted, fontSize: 13, fontWeight: '500' },
  history: { width: 44, height: 44, borderWidth: 1, borderColor: palette.navHover, borderRadius: radius.md, backgroundColor: palette.navActive, alignItems: 'center', justifyContent: 'center' },
  body: { padding: spacing.md, gap: spacing.md },
  title: { color: palette.text, fontSize: 17, fontWeight: '700', lineHeight: 24 },
  instruction: { gap: spacing.xs },
  label: { color: palette.textSoft, fontSize: 12, fontWeight: '600' },
  message: { color: palette.text, fontSize: 15, lineHeight: 23 },
  duration: { borderTopWidth: 1, borderTopColor: palette.border, paddingTop: spacing.sm, flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: spacing.xs },
  durationLabel: { color: palette.textSoft, fontSize: 12 },
  durationValue: { color: palette.text, fontSize: 13, fontWeight: '600', flexShrink: 1, marginLeft: 'auto' },
});
