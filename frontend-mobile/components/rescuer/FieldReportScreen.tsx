import { useEffect, useRef, useState } from 'react';
import { Alert, StyleSheet, Text, TextInput, View } from 'react-native';
import { getAssignedHousehold } from '@/api/rescuer';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { ActionButton, SectionHeader, StatTile } from './RescuerUI';
import { ReportSelect, type ReportOption } from './ReportSelect';

export function FieldReportScreen({ reports, assignmentId, householdId, onSubmitReport, onCompleteHousehold }: {
  reports: any[]; assignmentId: number; householdId: string; onSubmitReport: (payload: any) => Promise<void>; onCompleteHousehold?: () => void;
}) {
  const [household, setHousehold] = useState<any>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [statusOptions, setStatusOptions] = useState<ReportOption[]>([]);
  const [damageOptions, setDamageOptions] = useState<ReportOption[]>([]);
  const [damage, setDamage] = useState('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const [revision, setRevision] = useState(0);
  const requestKey = useRef('');
  const submitted = saved || reports.some(report => String(report.assignment_id) === String(assignmentId) && String(report.household_id) === String(householdId));
  const deathCount = members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_death).length;
  const injuredCount = members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_injury).length;

  useEffect(() => {
    let active = true;
    setHousehold(null); setMembers([]); setStatusOptions([]); setDamageOptions([]); setDamage(''); setNotes(''); setSaved(false); setError('');
    requestKey.current = `field-report-${assignmentId}-${householdId}`;
    if (!assignmentId || !householdId) { setLoading(false); setError('No current household.'); return; }
    setLoading(true);
    getAssignedHousehold(assignmentId).then(data => {
      if (!active) return;
      if (String(data.household?.household_id) !== String(householdId)) throw new Error('The current household changed. Refresh your queue.');
      setHousehold(data.household); setStatusOptions(data.member_status_options || []); setDamageOptions(data.property_damage_options || []); setDamage(data.saved_property_damage || '');
      setMembers((data.members || []).map((member: any) => ({ id: String(member.member_id), name: member.name, head: member.is_household_head, statusKey: member.member_status?.key || '' })));
    }).catch(failure => { if (active) setError(failure.message || 'Unable to load report.'); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [assignmentId, householdId, revision]);

  async function submit() {
    if (busy || submitted) return;
    if (!damage || !members.length || members.some(member => !statusOptions.some(option => option.key === member.statusKey))) {
      Alert.alert('Report incomplete', 'Select a status for each member and a property damage level.'); return;
    }
    setBusy(true); setError('');
    try {
      await onSubmitReport({ assignment_id: assignmentId, household_id: householdId, idempotency_key: requestKey.current, property_damage: damage,
        death_count: deathCount, injured_count: injuredCount, notes: notes.trim() || undefined, members: members.map(member => ({ member_id: member.id, status_key: member.statusKey })) });
      setSaved(true);
    } catch (failure: any) { setError(failure.response?.data?.message || 'Unable to save report.'); }
    finally { setBusy(false); }
  }

  return <View style={styles.stack}>
    <View style={styles.card}><SectionHeader title="Field report" />
      {loading && <Text style={styles.meta}>Loading…</Text>}
      {household && <><Text style={styles.name}>{household.household_name || household.household_id}</Text><Text style={styles.meta}>{household.household_id}</Text></>}
      {!!members.length && <View style={styles.columns}><Text style={styles.columnLabel}>Member</Text><Text style={styles.columnLabel}>Status check-in</Text></View>}
      {members.map(member => <View style={styles.member} key={member.id}><Text style={styles.memberName}>{member.name}{member.head ? ' · Head' : ''}</Text>
        <View style={styles.selectColumn}><ReportSelect label={`${member.name} status`} showLabel={false} value={member.statusKey} options={statusOptions} disabled={busy || submitted} onChange={statusKey => setMembers(current => current.map(item => item.id === member.id ? { ...item, statusKey } : item))} /></View>
      </View>)}
    </View>
    {household && <><View style={styles.card}><SectionHeader title="Property damage" /><ReportSelect label="Damage level" value={damage} options={damageOptions} onChange={setDamage} disabled={busy || submitted} />{!damageOptions.length && <Text style={styles.error}>Damage levels are unavailable.</Text>}</View>
      <View style={styles.counts}><StatTile label="Deaths" value={deathCount} tone="danger" /><StatTile label="Injured" value={injuredCount} tone="warning" /></View>
      <View style={styles.card}><Text style={styles.meta}>Notes (optional)</Text><TextInput accessibilityLabel="Notes for HQ" style={styles.input} value={notes} onChangeText={setNotes} editable={!busy && !submitted} multiline maxLength={2000} textAlignVertical="top" />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <ActionButton label={submitted ? 'Report saved' : busy ? 'Saving…' : 'Submit report'} icon="send-outline" onPress={submit} disabled={busy || submitted || !damageOptions.length} />
        {submitted && onCompleteHousehold && <ActionButton label="Complete household" icon="flag-outline" onPress={onCompleteHousehold} />}
      </View></>}
    {!household && error ? <View style={styles.card}><Text style={styles.error}>{error}</Text><ActionButton label="Retry" onPress={() => setRevision(value => value + 1)} /></View> : null}
  </View>;
}
const styles = StyleSheet.create({
  stack: { gap: spacing.md }, card: { backgroundColor: palette.card, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.md, gap: spacing.sm },
  name: { color: palette.text, fontSize: 18, fontWeight: '700' }, meta: { color: palette.textSoft, fontSize: 13 },
  columns: { flexDirection: 'row', gap: 8, marginTop: 8 }, columnLabel: { flex: 1, color: palette.textSoft, fontSize: 12 },
  member: { flexDirection: 'row', alignItems: 'center', paddingTop: 6, gap: 8, borderTopWidth: 1, borderColor: palette.border }, memberName: { flex: 1, color: palette.text, fontSize: 15, fontWeight: '600' }, selectColumn: { flex: 1, minWidth: 0 },
  counts: { flexDirection: 'row', gap: spacing.sm }, input: { minHeight: 78, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, padding: spacing.sm, color: palette.text }, error: { color: palette.unsafe, fontSize: 13 },
});
