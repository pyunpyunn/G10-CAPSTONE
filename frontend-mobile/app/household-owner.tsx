import { useEffect, useRef, useState } from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { type Href, useRouter } from 'expo-router';
import { getHouseholdOwnerOptions, logoutMobile, selectHouseholdDeviceOwner } from '@/api/auth';
import { getViewState } from '@/state/apiStore';
import { HouseholdButton, HouseholdLoading } from '@/components/household/HouseholdUI';
import { askStandardMobilePermissions } from '@/utils/mobilePermissions';
import { palette, radius, spacing } from '@/constants/resqTheme';

type OwnerOptions = { household_id: string; household_name: string; members: { member_id: string; name: string; relationship: string }[] };

export default function HouseholdOwnerScreen() {
  const router = useRouter();
  const [options, setOptions] = useState<OwnerOptions | null>(() => getViewState('mobile:household-owner')?.value || null);
  const [loading, setLoading] = useState(!options);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState('');
  const submitting = useRef(false);

  async function load() {
    setLoading(true);
    setError('');
    try { setOptions(await getHouseholdOwnerOptions()); }
    catch (failure: any) { setError(failure?.response?.data?.message || 'Cannot load household members. Check your connection and retry.'); }
    finally { setLoading(false); }
  }

  useEffect(() => { if (!options) void load(); }, [options]);

  async function choose(memberId: string) {
    if (submitting.current) return;
    submitting.current = true;
    setBusy(memberId);
    try {
      await selectHouseholdDeviceOwner(memberId);
      await askStandardMobilePermissions('household_resident');
      router.replace('/household' as Href);
    } catch (failure: any) {
      Alert.alert('Owner not confirmed', failure?.response?.data?.message || 'The phone owner could not be confirmed. Your session is kept; retry when connected.');
    } finally { submitting.current = false; setBusy(''); }
  }

  async function signOut() {
    if (submitting.current) return;
    submitting.current = true;
    setBusy('logout');
    try { await logoutMobile(); router.replace('/' as Href); }
    finally { submitting.current = false; setBusy(''); }
  }

  return <SafeAreaView style={styles.safe}>
    <FlatList data={options?.members || []} numColumns={2} keyExtractor={(member) => member.member_id}
      contentContainerStyle={styles.content} columnWrapperStyle={styles.row} initialNumToRender={8}
      ListHeaderComponent={<View style={styles.header}>
        <Text style={styles.brand}>RESQPERATION</Text>
        <Text style={styles.title}>Who is using this phone?</Text>
        <Text style={styles.help}>{options?.household_name || 'Household account'}{options ? ` · ${options.household_id}` : ''}</Text>
        <Text style={styles.help}>Choose once. This phone will remember its owner. You can reset the owner in Profile settings.</Text>
        {loading && !options ? <HouseholdLoading label="Loading household members..." /> : null}
        {error ? <><Text accessibilityRole="alert" style={styles.error}>{error}</Text><HouseholdButton label="Retry" onPress={load} disabled={loading} /></> : null}
      </View>}
      renderItem={({ item }) => <Pressable accessibilityRole="button" accessibilityLabel={`Use this phone as ${item.name}`}
        disabled={!!busy} onPress={() => { void choose(item.member_id); }} style={[styles.member, busy === item.member_id && styles.selected]}>
        <View style={styles.avatar}><Text style={styles.initials}>{item.name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('')}</Text></View>
        <Text style={styles.name}>{item.name}</Text><Text style={styles.help}>{busy === item.member_id ? 'Saving phone owner...' : item.relationship}</Text>
      </Pressable>}
      ListEmptyComponent={!loading && !error ? <Text style={styles.help}>No active household members are available. Contact HQ/Admin.</Text> : null}
      ListFooterComponent={<View style={styles.footer}><HouseholdButton label={busy === 'logout' ? 'Signing out...' : 'Sign out'} tone="light" disabled={!!busy} onPress={signOut} /></View>} />
  </SafeAreaView>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.page },
  content: { padding: spacing.lg, gap: spacing.md },
  header: { gap: spacing.md, paddingVertical: spacing.xl },
  brand: { color: palette.brandRed, fontWeight: '900', letterSpacing: 1 },
  title: { color: palette.text, fontSize: 28, fontWeight: '900' },
  help: { color: palette.textSoft, textAlign: 'center' },
  row: { gap: spacing.md },
  member: { flex: 1, maxWidth: '49%', padding: spacing.lg, borderRadius: radius.lg, backgroundColor: palette.card, alignItems: 'center', gap: spacing.sm, borderWidth: 2, borderColor: palette.border },
  selected: { borderColor: palette.navActive },
  avatar: { width: 64, height: 64, borderRadius: radius.lg, backgroundColor: palette.navActive, alignItems: 'center', justifyContent: 'center' },
  initials: { color: '#fff', fontSize: 24, fontWeight: '900' },
  name: { color: palette.text, fontWeight: '800', textAlign: 'center' },
  error: { color: palette.unsafe },
  footer: { marginVertical: spacing.xl },
});
