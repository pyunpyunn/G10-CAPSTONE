import { useEffect, useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { getRescuerDispatchHistory } from '@/api/rescuer';
import { ActionButton, EmptyState, SectionHeader, StatTile, StatusBadge } from './RescuerUI';

type Props = { overview: any; pendingAssignmentId?: number | null; onOpenMap: (assignment?: any) => void; onOpenReport: (assignment?: any) => void; onStatusChange: (id: number, status: string) => void };
export function RescuerDashboardScreen({ overview, pendingAssignmentId, onOpenMap, onOpenReport, onStatusChange }: Props) {
  const [historyOpen, setHistoryOpen] = useState(false);
  const [history, setHistory] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [historyError, setHistoryError] = useState('');
  const [revision, setRevision] = useState(0);
  const assignments = overview?.assignments || [];
  const responder = overview?.profile?.responder || {};
  const summary = overview?.summary || {};
  const current = assignments.find((item: any) => ['dispatched','accepted','en_route','on_scene'].includes(item.status_key) && item.household_id);
  const queue = assignments.filter((item: any) => !['cancelled','completed'].includes(item.status_key));
  const household = current?.route_notes?.assigned_households?.find((item: any) => item.household_id === current.household_id);
  useEffect(() => {
    if (!historyOpen) return;
    let active = true;
    setLoading(true); setHistoryError('');
    getRescuerDispatchHistory(page).then(data => {
      if (!active) return;
      setHistory(rows => page === 1 ? data.assignments : [...rows.filter(row => !data.assignments.some((item: any) => item.assignment_id === row.assignment_id)), ...data.assignments]);
      setLastPage(data.pagination.last_page);
    }).catch(() => { if (active) setHistoryError('History unavailable.'); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [historyOpen, page, revision]);
  function openHistory() { setHistory([]); setPage(1); setHistoryOpen(true); }
  const next = current?.status_key === 'dispatched' ? ['Accept','accepted'] : current?.status_key === 'accepted' ? ['En route','en_route'] : current?.status_key === 'en_route' ? ['Arrived','on_scene'] : null;
  const updating = pendingAssignmentId != null && Number(pendingAssignmentId) === Number(current?.assignment_id);
  return <View style={styles.stack}>
    <View style={styles.identity}><View style={styles.flex}><Text style={styles.name}>{responder.full_name || overview?.profile?.user?.full_name || 'Rescuer'}</Text><Text style={styles.meta}>{responder.team_name || 'No team'}</Text></View><StatusBadge label={responder.duty_status || 'Standby'} tone={responder.duty_status} /></View>
    {overview?.active_event && <Text style={styles.event}>{overview.active_event.name}</Text>}
    <View style={styles.stats}><StatTile label="Assigned" value={summary.total_assignments || 0} /><StatTile label="Active" value={summary.active_assignments || 0} /><StatTile label="Done" value={summary.completed_assignments || 0} tone="completed" /></View>
    {current ? <View style={styles.card}>
      <SectionHeader title="Current assignment" action={<StatusBadge label={current.status_label} tone={current.status_key} />} />
      <Text style={styles.household}>{household?.household_name || current.household_id}</Text>
      <View style={styles.info}><Text style={styles.meta}>Household <Text style={styles.data}>{current.household_id}</Text></Text><StatusBadge label={current.priority_level || 'medium'} tone={current.priority_level} /></View>
      {next && <ActionButton label={updating ? 'Updating…' : next[0]} loading={updating} icon="navigate-outline" onPress={() => onStatusChange(current.assignment_id,next[1])} />}
      <View style={styles.actions}>
        <TaskAction label="Map" icon="map-outline" onPress={() => onOpenMap(current)} />
        <TaskAction label="Field report" icon="document-text-outline" onPress={() => onOpenReport(current)} />
        <TaskAction label="Complete household" icon="checkmark-circle-outline" primary disabled={updating} onPress={() => onStatusChange(current.assignment_id,'completed')} />
      </View>
    </View> : <EmptyState icon="radio-outline" title="No current household" />}
    <View style={styles.card}>
      <SectionHeader title="Household queue" action={<Pressable accessibilityRole="button" accessibilityLabel="Dispatch history" style={styles.iconButton} onPress={openHistory}><Ionicons name="time-outline" size={22} color={palette.navActive} /></Pressable>} />
      {queue.flatMap((assignment: any) => (assignment.route_notes?.assigned_households || []).map((item: any) => {
        const done = !(assignment.route_notes?.pending_household_ids || []).includes(item.household_id);
        return <View style={styles.queueRow} key={`${assignment.assignment_id}-${item.household_id}`}><View style={styles.flex}><Text style={styles.queueName}>{item.household_name || item.household_id}</Text><Text style={styles.meta}>{item.address || item.household_id}</Text></View><StatusBadge label={done ? 'Completed' : item.household_id === current?.household_id ? 'Current' : 'Waiting'} tone={done ? 'completed' : 'neutral'} /></View>;
      }))}
      {!queue.length && <Text style={styles.meta}>No queued households</Text>}
    </View>
    <Modal visible={historyOpen} animationType="slide" transparent onRequestClose={() => setHistoryOpen(false)}><View style={styles.overlay}><View style={styles.history}>
      <SectionHeader title="Dispatch history" action={<Pressable accessibilityRole="button" accessibilityLabel="Close dispatch history" style={styles.iconButton} onPress={() => setHistoryOpen(false)}><Ionicons name="close" size={24} color={palette.text} /></Pressable>} />
      <FlatList data={history} keyExtractor={item => String(item.assignment_id)} onEndReached={() => { if (!loading && !historyError && page < lastPage) setPage(value => value + 1); }} onEndReachedThreshold={0.4}
        renderItem={({ item }) => <View style={styles.queueRow}><View style={styles.flex}><Text style={styles.queueName}>{item.assignment_code}</Text><Text style={styles.meta}>{item.assigned_area}</Text><Text style={styles.meta}>{formatPhilippineDateTime(item.assigned_at)}</Text></View><StatusBadge label={item.status_label} tone={item.status_key} /></View>}
        ListEmptyComponent={!loading && !historyError ? <Text style={styles.meta}>No dispatch history</Text> : null}
        ListFooterComponent={<>{loading && <Text style={styles.meta}>Loading…</Text>}{historyError ? <><Text style={styles.error}>{historyError}</Text><ActionButton label="Retry" onPress={() => setRevision(value => value + 1)} /></> : null}</>} />
    </View></View></Modal>
  </View>;
}
function TaskAction({ label, icon, primary, disabled, onPress }: { label: string; icon: keyof typeof Ionicons.glyphMap; primary?: boolean; disabled?: boolean; onPress: () => void }) {
  return <Pressable accessibilityRole="button" accessibilityLabel={label} disabled={disabled} android_ripple={{ color: '#236a7333' }} onPress={onPress} style={[styles.task, primary && styles.primary, disabled && { opacity: 0.5 }]}><Ionicons name={icon} size={20} color={primary ? '#fff' : palette.navActive} /><Text style={[styles.taskText, primary && styles.white]} numberOfLines={2}>{label}</Text></Pressable>;
}
const styles = StyleSheet.create({
  stack: { gap: spacing.md }, identity: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm }, flex: { flex: 1, minWidth: 0 }, name: { color: palette.text, fontSize: 20, fontWeight: '700' }, meta: { color: palette.textSoft, fontSize: 12, lineHeight: 18 }, event: { color: palette.text, fontSize: 14, fontWeight: '600' },
  stats: { flexDirection: 'row', gap: spacing.sm }, card: { backgroundColor: palette.card, borderColor: palette.border, borderWidth: 1, borderRadius: radius.lg, padding: spacing.md, gap: spacing.sm }, household: { color: palette.text, fontSize: 19, fontWeight: '700' }, info: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.sm }, data: { color: palette.text, fontWeight: '600' },
  actions: { flexDirection: 'row', gap: 8 }, task: { flex: 1, minWidth: 0, minHeight: 64, padding: 6, justifyContent: 'center', alignItems: 'center', gap: 4, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md }, taskText: { fontSize: 12, fontWeight: '600', color: palette.text, textAlign: 'center' }, primary: { backgroundColor: palette.navActive, borderColor: palette.navActive }, white: { color: '#fff' },
  queueRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, paddingVertical: 10, borderBottomWidth: 1, borderColor: palette.border }, queueName: { color: palette.text, fontSize: 14, fontWeight: '600' }, iconButton: { padding: 10, minWidth: 44, minHeight: 44, justifyContent: 'center', alignItems: 'center' }, overlay: { flex: 1, justifyContent: 'center', padding: spacing.lg, backgroundColor: '#0008' }, history: { backgroundColor: palette.card, borderRadius: radius.lg, padding: spacing.md, height: '80%' }, error: { color: palette.unsafe },
});
