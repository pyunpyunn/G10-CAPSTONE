import { useEffect, useRef, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { randomUUID } from 'expo-crypto';
import { getAssignedHousehold, getAssignedMemberProfilePhoto } from '@/api/rescuer';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { ActionButton, SectionHeader } from './RescuerUI';
import { ReportSelect, type ReportOption } from './ReportSelect';

export function FieldReportScreen({ reports, assignmentId, householdId, onSubmitReport, onCorrectReport, onCompleteHousehold }: {
  reports: any[]; assignmentId: number; householdId: string; onSubmitReport: (payload: any) => Promise<void>;
  onCorrectReport: (reportId: number, payload: any) => Promise<void>; onCompleteHousehold?: () => void;
}) {
  const [household, setHousehold] = useState<any>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [memberPhotos, setMemberPhotos] = useState<Record<string, any>>({});
  const [statusOptions, setStatusOptions] = useState<ReportOption[]>([]);
  const [damageOptions, setDamageOptions] = useState<ReportOption[]>([]);
  const [damage, setDamage] = useState('');
  const [notes, setNotes] = useState('');
  const [counts, setCounts] = useState<Partial<Record<'death_count' | 'injured_count' | 'missing_count', number>>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const [savedReportId, setSavedReportId] = useState<number | null>(null);
  const [editingSaved, setEditingSaved] = useState(false);
  const [revision, setRevision] = useState(0);
  const requestKey = useRef('');
  const submitted = saved || savedReportId !== null || reports.some(report => String(report.assignment_id) === String(assignmentId) && String(report.household_id) === String(householdId));
  const fieldsDisabled = busy || (submitted && !editingSaved);
  const deathCount = counts.death_count ?? members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_death).length;
  const injuredCount = counts.injured_count ?? members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_injury).length;

  const missingCount = counts.missing_count ?? members.filter(member => member.statusKey === 'missing').length;
  function countControls(key: 'death_count' | 'injured_count' | 'missing_count', label: string, value: number) {
    return <View style={styles.counterControls}>{([-1, 1] as const).map(delta => <Pressable key={delta}
      accessibilityRole="button" accessibilityLabel={`${delta < 0 ? 'Decrease' : 'Increase'} ${label.toLowerCase()}`}
      disabled={fieldsDisabled || (delta < 0 && value === 0) || (delta > 0 && value >= 4294967295)}
      onPress={() => setCounts(current => ({ ...current, [key]: Math.max(0, Math.min(4294967295, (current[key] ?? value) + delta)) }))}
      style={[styles.counterButton, (fieldsDisabled || (delta < 0 && value === 0)) && { opacity: 0.4 }]}><Text style={styles.counterText}>{delta < 0 ? '-' : '+'}</Text></Pressable>)}</View>;
  }
  function countTile(label: string, key: 'death_count' | 'injured_count' | 'missing_count', value: number, tone: 'danger' | 'warning') {
    return <View key={key} style={styles.countTile}>
      <Text style={styles.countLabel}>{label}</Text>
      <View style={styles.countValueRow}>
        <Text accessibilityLabel={`${label} count`} style={[styles.countValue, tone === 'danger' ? styles.countDanger : styles.countWarning]}>{value}</Text>
        {countControls(key, label, value)}
      </View>
    </View>;
  }

  useEffect(() => {
    let active = true;
    setHousehold(null); setMembers([]); setMemberPhotos({}); setStatusOptions([]); setDamageOptions([]); setDamage(''); setNotes(''); setCounts({}); setSaved(false); setSavedReportId(null); setEditingSaved(false); setError('');
    requestKey.current = randomUUID();
    if (!assignmentId || !householdId) { setLoading(false); setError('No current household.'); return; }
    setLoading(true);
    getAssignedHousehold(assignmentId).then(data => {
      if (!active) return;
      if (String(data.household?.household_id) !== String(householdId)) throw new Error('The current household changed. Refresh your queue.');
      setHousehold(data.household); setStatusOptions(data.member_status_options || []); setDamageOptions(data.property_damage_options || []); setDamage(data.saved_property_damage || '');
      setCounts(data.saved_counts || {}); setNotes(data.saved_notes || ''); setSavedReportId(data.saved_report_id ? Number(data.saved_report_id) : null);
      const assignedMembers = data.members || [];
      setMembers(assignedMembers.map((member: any) => ({ id: String(member.member_id), name: member.name, head: member.is_household_head, statusKey: member.member_status?.key || '' })));
      void Promise.all(assignedMembers
        .filter((member: any) => member.has_profile_photo)
        .map(async (member: any) => [String(member.member_id), await getAssignedMemberProfilePhoto(assignmentId, String(member.member_id))] as const))
        .then(photos => { if (active) setMemberPhotos(Object.fromEntries(photos.filter(([, source]) => source))); })
        .catch(photoError => { if (active) console.warn('Assigned household profile photos could not be loaded.', photoError); });
    }).catch(failure => { if (active) setError(failure.message || 'Unable to load report.'); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [assignmentId, householdId, revision]);

  async function submit() {
    if (busy || (submitted && !editingSaved)) return;
    if (!damage || !members.length || members.some(member => !statusOptions.some(option => option.key === member.statusKey))) {
      Alert.alert('Report incomplete', 'Select a status for each member and a property damage level.'); return;
    }
    setBusy(true); setError('');
    try {
      const payload = { assignment_id: assignmentId, household_id: householdId, idempotency_key: requestKey.current, property_damage: damage,
        death_count: deathCount, injured_count: injuredCount, missing_count: missingCount, notes: notes.trim() || undefined, members: members.map(member => ({ member_id: member.id, status_key: member.statusKey })) };
      if (editingSaved && savedReportId !== null) await onCorrectReport(savedReportId, payload);
      else await onSubmitReport(payload);
      setSaved(true); setEditingSaved(false);
    } catch (failure: any) { setError(failure.response?.data?.message || 'Unable to save report.'); }
    finally { setBusy(false); }
  }

  function startCorrection() {
    if (savedReportId === null) return;
    requestKey.current = randomUUID();
    setEditingSaved(true);
    setSaved(false);
  }

  function cancelCorrection() {
    setRevision(value => value + 1);
  }

  return <View style={styles.stack}>
    <View style={styles.card}><SectionHeader title="Field report" />
      {loading && <Text style={styles.meta}>Loading…</Text>}
      {household && <><Text style={styles.name}>{household.household_name || household.household_id}</Text><Text style={styles.meta}>{household.household_id}</Text></>}
      {!!members.length && <View style={styles.columns}><View style={styles.memberPhotoSlot} /><Text style={styles.columnLabel}>Member</Text><Text style={styles.columnLabel}>Status check-in</Text></View>}
      {members.map(member => <View style={styles.member} key={member.id}>
        <View style={styles.memberPhotoSlot}>
          {memberPhotos[member.id] && <Image source={memberPhotos[member.id]} style={styles.memberPhoto} accessibilityLabel={`${member.name} profile picture`} />}
        </View>
        <Text style={styles.memberName}>{member.name}{member.head ? ' · Head' : ''}</Text>
        <View style={styles.selectColumn}><ReportSelect label={`${member.name} status`} showLabel={false} value={member.statusKey} options={statusOptions} disabled={fieldsDisabled} onChange={statusKey => setMembers(current => current.map(item => item.id === member.id ? { ...item, statusKey } : item))} /></View>
      </View>)}
    </View>
    {household && <><View style={styles.card}><SectionHeader title="Property damage" /><ReportSelect label="Damage level" value={damage} options={damageOptions} onChange={setDamage} disabled={fieldsDisabled} />{!damageOptions.length && <Text style={styles.error}>Damage levels are unavailable.</Text>}</View>
      <View style={styles.counts}>
        {countTile('Deaths', 'death_count', deathCount, 'danger')}
        {countTile('Injured', 'injured_count', injuredCount, 'warning')}
        {countTile('Missing', 'missing_count', missingCount, 'warning')}
      </View>
      <View style={styles.card}><SectionHeader title="Notes (optional)" /><TextInput accessibilityLabel="Notes for HQ" style={styles.input} value={notes} onChangeText={setNotes} editable={!fieldsDisabled} multiline maxLength={2000} textAlignVertical="top" />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <ActionButton label={busy ? 'Saving…' : editingSaved ? 'Save correction' : submitted ? 'Report saved' : 'Submit report'} icon="send-outline" onPress={submit} disabled={busy || (submitted && !editingSaved) || !damageOptions.length} />
        {submitted && !editingSaved && savedReportId !== null ? <ActionButton label="Edit saved report" tone="light" onPress={startCorrection} /> : null}
        {submitted && editingSaved ? <ActionButton label="Cancel correction" tone="light" onPress={cancelCorrection} disabled={busy} /> : null}
        {submitted && !editingSaved && onCompleteHousehold && <ActionButton label="Complete household" icon="flag-outline" onPress={onCompleteHousehold} />}
      </View></>}
    {!household && error ? <View style={styles.card}><Text style={styles.error}>{error}</Text><ActionButton label="Retry" onPress={() => setRevision(value => value + 1)} /></View> : null}
  </View>;
}
const styles = StyleSheet.create({
  stack: { gap: spacing.md }, card: { backgroundColor: palette.card, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.lg, gap: spacing.md },
  name: { color: palette.text, fontSize: 18, fontWeight: '700' }, meta: { color: palette.textSoft, fontSize: 13 },
  columns: { flexDirection: 'row', gap: 8, marginTop: 8, alignItems: 'center' }, columnLabel: { flex: 1, minWidth: 0, color: palette.textSoft, fontSize: 12 },
  member: { flexDirection: 'row', alignItems: 'center', paddingTop: spacing.sm, gap: 8, borderTopWidth: 1, borderColor: palette.border }, memberPhotoSlot: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center' }, memberPhoto: { width: 36, height: 36, borderRadius: 18 }, memberName: { flex: 1, minWidth: 0, color: palette.text, fontSize: 15, fontWeight: '600' }, selectColumn: { flex: 1, minWidth: 0 },
  counterControls: { flexDirection: 'row', gap: 3 }, counterButton: { width: 28, height: 34, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: palette.border, borderRadius: radius.sm }, counterText: { color: palette.text, fontSize: 18, fontWeight: '700' },
  counts: { flexDirection: 'row', flexWrap: 'nowrap', gap: spacing.xs }, countTile: { flex: 1, minWidth: 0, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, padding: spacing.sm, backgroundColor: palette.card, gap: spacing.sm }, countLabel: { color: palette.textSoft, fontSize: 10, fontWeight: '800', textTransform: 'uppercase' }, countValueRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 4 }, countValue: { fontSize: 22, fontWeight: '900' }, countDanger: { color: palette.unsafe }, countWarning: { color: '#9a6700' },
  input: { minHeight: 78, borderWidth: 1, borderColor: palette.borderStrong, borderRadius: radius.md, padding: spacing.sm, color: palette.text, backgroundColor: palette.page }, error: { color: palette.unsafe, fontSize: 13 },
});
