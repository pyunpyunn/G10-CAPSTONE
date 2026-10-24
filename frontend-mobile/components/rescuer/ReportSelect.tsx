import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { palette, radius, spacing } from '@/constants/resqTheme';

export type ReportOption = { key: string; label: string; counts_as_death?: boolean; counts_as_injury?: boolean };
export function ReportSelect({ label, value, options, onChange, disabled = false, showLabel = true }: {
  label: string; value: string; options: ReportOption[]; onChange: (key: string) => void; disabled?: boolean; showLabel?: boolean;
}) {
  const [open, setOpen] = useState(false);
  return <View style={styles.field}>
    {showLabel && <Text style={styles.label}>{label}</Text>}
    <Pressable accessibilityRole="button" accessibilityLabel={`${label}: ${options.find(option => option.key === value)?.label || 'Select'}`} accessibilityState={{ expanded: open, disabled }} disabled={disabled} style={styles.select} onPress={() => setOpen(true)}>
      <Text style={styles.value} numberOfLines={2}>{options.find(option => option.key === value)?.label || 'Select'}</Text><Ionicons name="chevron-down" size={18} color={palette.textSoft} />
    </Pressable>
    <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
      <View style={styles.overlay}><Pressable style={StyleSheet.absoluteFill} accessibilityLabel="Close list" onPress={() => setOpen(false)} />
        <View style={styles.dialog} accessibilityViewIsModal>
          <View style={styles.heading}><Text style={styles.title}>{label}</Text><Pressable accessibilityRole="button" accessibilityLabel="Close list" onPress={() => setOpen(false)} style={styles.close}><Ionicons name="close" size={22} color={palette.text} /></Pressable></View>
          <FlatList data={options} keyExtractor={option => option.key} renderItem={({ item }) => <Pressable accessibilityRole="radio" accessibilityState={{ checked: value === item.key }} style={styles.option} onPress={() => { onChange(item.key); setOpen(false); }}><Text style={styles.value}>{item.label}</Text>{value === item.key && <Ionicons name="checkmark" size={20} color={palette.navActive} />}</Pressable>} />
        </View>
      </View>
    </Modal>
  </View>;
}
const styles = StyleSheet.create({
  field: { gap: 6 }, label: { color: palette.textSoft, fontSize: 12, fontWeight: '600' },
  select: { minHeight: 46, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: palette.card },
  value: { color: palette.text, fontSize: 14, flexShrink: 1 }, overlay: { flex: 1, justifyContent: 'center', padding: spacing.lg, backgroundColor: '#0008' },
  dialog: { backgroundColor: palette.card, borderRadius: radius.lg, maxHeight: '75%', overflow: 'hidden' },
  heading: { paddingLeft: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderColor: palette.border },
  title: { fontSize: 17, fontWeight: '700', color: palette.text, flex: 1 }, close: { padding: 14 },
  option: { minHeight: 48, padding: spacing.md, borderBottomWidth: 1, borderColor: palette.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
});
