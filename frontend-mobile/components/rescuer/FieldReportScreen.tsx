import { useEffect, useRef, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { getAssignedHousehold, getAssignedMemberProfilePhoto } from '@/api/rescuer';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { ActionButton, SectionHeader, StatTile } from './RescuerUI';
import { ReportSelect, type ReportOption } from './ReportSelect';

export function FieldReportScreen({ reports, assignmentId, householdId, onSubmitReport, onCompleteHousehold }: {
  reports: any[]; assignmentId: number; householdId: string; onSubmitReport: (payload: any) => Promise<void>; onCompleteHousehold?: () => void;
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
  const [revision, setRevision] = useState(0);
  const requestKey = useRef('');
  const submitted = saved || reports.some(report => String(report.assignment_id) === String(assignmentId) && String(report.household_id) === String(householdId));
  const deathCount = counts.death_count ?? members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_death).length;
  const injuredCount = counts.injured_count ?? members.filter(member => statusOptions.find(option => option.key === member.statusKey)?.counts_as_injury).length;

  const missingCount = counts.missing_count ?? members.filter(member => member.statusKey === 'missing').length;
  function countControls(key: 'death_count' | 'injured_count' | 'missing_count', label: string, value: number) {
    return <View style={styles.counterControls}>{([-1, 1] as const).map(delta => <Pressable key={delta}
      accessibilityRole="button" accessibilityLabel={`${delta < 0 ? 'Decrease' : 'Increase'} ${label.toLowerCase()}`}
      disabled={busy || submitted || (delta < 0 && value === 0) || (delta > 0 && value >= 4294967295)}
      onPress={() => setCounts(current => ({ ...current, [key]: Math.max(0, Math.min(4294967295, (current[key] ?? value) + delta)) }))}
      style={[styles.counterButton, (busy || submitted || (delta < 0 && value === 0)) && { opacity: 0.4 }]}><Text style={styles.counterText}>{delta < 0 ? '-' : '+'}</Text></Pressable>)}</View>;
  }

  useEffect(() => {
    let active = true;
    setHousehold(null); setMembers([]); setMemberPhotos({}); setStatusOptions([]); setDamageOptions([]); setDamage(''); setNotes(''); setCounts({}); setSaved(false); setError('');
    requestKey.current = `field-report-${assignmentId}-${householdId}`;
    if (!assignmentId || !householdId) { setLoading(false); setError('No current household.'); return; }
    setLoading(true);
    getAssignedHousehold(assignmentId).then(data => {
      if (!active) return;
      if (String(data.household?.household_id) !== String(householdId)) throw new Error('The current household changed. Refresh your queue.');
      setHousehold(data.household); setStatusOptions(data.member_status_options || []); setDamageOptions(data.property_damage_options || []); setDamage(data.saved_property_damage || '');
      setCounts(data.saved_counts || {});
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
    if (busy || submitted) return;
    if (!damage || !members.length || members.some(member => !statusOptions.some(option => option.key === member.statusKey))) {
      Alert.alert('Report incomplete', 'Select a status for each member and a property damage level.'); return;
    }
    setBusy(true); setError('');
    try {
      await onSubmitReport({ assignment_id: assignmentId, household_id: householdId, idempotency_key: requestKey.current, property_damage: damage,
        death_count: deathCount, injured_count: injuredCount, missing_count: missingCount, notes: notes.trim() || undefined, members: members.map(member => ({ member_id: member.id, status_key: member.statusKey })) });
      setSaved(true);
    } catch (failure: any) { setError(failure.response?.data?.message || 'Unable to save report.'); }
    finally { setBusy(false); }
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
        <View style={styles.selectColumn}><ReportSelect label={`${member.name} status`} showLabel={false} value={member.statusKey} options={statusOptions} disabled={busy || submitted} onChange={statusKey => setMembers(current => current.map(item => item.id === member.id ? { ...item, statusKey } : item))} /></View>
      </View>)}
    </View>
    {household && <><View style={styles.card}><SectionHeader title="Property damage" /><ReportSelect label="Damage level" value={damage} options={damageOptions} onChange={setDamage} disabled={busy || submitted} />{!damageOptions.length && <Text style={styles.error}>Damage levels are unavailable.</Text>}</View>
      <View style={styles.counts}><StatTile label="Deaths" value={deathCount} tone="danger" action={countControls('death_count', 'Deaths', deathCount)} /><StatTile label="Injured" value={injuredCount} tone="warning" action={countControls('injured_count', 'Injured', injuredCount)} /><StatTile label="Missing" value={missingCount} tone="warning" action={countControls('missing_count', 'Missing', missingCount)} /></View>
      <View style={styles.card}><SectionHeader title="Notes (optional)" /><TextInput accessibilityLabel="Notes for HQ" style={styles.input} value={notes} onChangeText={setNotes} editable={!busy && !submitted} multiline maxLength={2000} textAlignVertical="top" />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <ActionButton label={submitted ? 'Report saved' : busy ? 'Saving…' : 'Submit report'} icon="send-outline" onPress={submit} disabled={busy || submitted || !damageOptions.length} />
        {submitted && onCompleteHousehold && <ActionButton label="Complete household" icon="flag-outline" onPress={onCompleteHousehold} />}
      </View></>}
    {!household && error ? <View style={styles.card}><Text style={styles.error}>{error}</Text><ActionButton label="Retry" onPress={() => setRevision(value => value + 1)} /></View> : null}
  </View>;
}
const styles = StyleSheet.create({
  stack: { gap: spacing.md }, card: { backgroundColor: palette.card, borderWidth: 1, borderColor: palette.border, borderRadius: radius.lg, padding: spacing.lg, gap: spacing.md },
  name: { color: palette.text, fontSize: 18, fontWeight: '700' }, meta: { color: palette.textSoft, fontSize: 13 },
  columns: { flexDirection: 'row', gap: 8, marginTop: 8, alignItems: 'center' }, columnLabel: { flex: 1, minWidth: 0, color: palette.textSoft, fontSize: 12 },
  member: { flexDirection: 'row', alignItems: 'center', paddingTop: spacing.sm, gap: 8, borderTopWidth: 1, borderColor: palette.border }, memberPhotoSlot: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center' }, memberPhoto: { width: 36, height: 36, borderRadius: 18 }, memberName: { flex: 1, minWidth: 0, color: palette.text, fontSize: 15, fontWeight: '600' }, selectColumn: { flex: 1, minWidth: 0 },
  counterControls: { flexDirection: 'row', gap: 4 }, counterButton: { minWidth: 32, minHeight: 36, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: palette.border, borderRadius: radius.sm }, counterText: { color: palette.text, fontSize: 20, fontWeight: '700' },
  counts: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm }, input: { minHeight: 78, borderWidth: 1, borderColor: palette.borderStrong, borderRadius: radius.md, padding: spacing.sm, color: palette.text, backgroundColor: palette.page }, error: { color: palette.unsafe, fontSize: 13 },
});
