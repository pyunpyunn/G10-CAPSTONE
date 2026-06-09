import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, SectionHeader, StatusBadge } from './RescuerUI';

type ResourceRequestProps = {
  requests: any[];
  categoryOptions: any[];
  onSubmitRequest: (payload: any) => Promise<void>;
  onCancelRequest: (requestId: string) => Promise<void>;
};

const defaultCategories = [
  { key: 'resource', label: 'Resource' },
  { key: 'personnel', label: 'Personnel' },
  { key: 'vehicle', label: 'Transport' },
];

export function ResourceRequestScreen({
  requests,
  categoryOptions,
  onSubmitRequest,
  onCancelRequest,
}: ResourceRequestProps) {
  const categories = categoryOptions?.length ? categoryOptions : defaultCategories;
  const [location, setLocation] = useState('');
  const [cluster, setCluster] = useState('');
  const [requestCategory, setRequestCategory] = useState(categories[0]?.key || 'resource');
  const [resourceType, setResourceType] = useState('');
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [unit, setUnit] = useState('');
  const [urgencyKey, setUrgencyKey] = useState('medium');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    if (!location.trim() || !resourceType.trim() || !quantity.trim()) {
      Alert.alert('Missing request details', 'Enter location, need type, and quantity.');
      return;
    }

    setSubmitting(true);

    try {
      await onSubmitRequest({
        location: location.trim(),
        cluster: cluster.trim(),
        request_category: requestCategory,
        resource_type: resourceType.trim(),
        item_name: itemName.trim(),
        quantity: Number(quantity || 1),
        unit: unit.trim(),
        urgency_key: urgencyKey,
        description: description.trim(),
      });

      setLocation('');
      setCluster('');
      setResourceType('');
      setItemName('');
      setQuantity('1');
      setUnit('');
      setUrgencyKey('medium');
      setDescription('');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader title="Request logistics" />

        <View style={styles.chipRow}>
          {categories.map((category) => (
            <Pressable
              key={category.key}
              style={[styles.chip, requestCategory === category.key && styles.chipActive]}
              onPress={() => setRequestCategory(category.key)}
            >
              <Text style={[styles.chipText, requestCategory === category.key && styles.chipTextActive]}>
                {category.label}
              </Text>
            </Pressable>
          ))}
        </View>

        <TextInput
          style={styles.input}
          value={location}
          onChangeText={setLocation}
          placeholder="Needed at location"
          placeholderTextColor="#7d8da0"
        />

        <TextInput
          style={styles.input}
          value={cluster}
          onChangeText={setCluster}
          placeholder="Purok / cluster"
          placeholderTextColor="#7d8da0"
        />

        <View style={styles.twoColumn}>
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={resourceType}
            onChangeText={setResourceType}
            placeholder="Need type"
            placeholderTextColor="#7d8da0"
          />
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={itemName}
            onChangeText={setItemName}
            placeholder="Item name"
            placeholderTextColor="#7d8da0"
          />
        </View>

        <View style={styles.twoColumn}>
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={quantity}
            onChangeText={setQuantity}
            placeholder="Quantity"
            placeholderTextColor="#7d8da0"
            keyboardType="number-pad"
          />
          <TextInput
            style={[styles.input, styles.flexInput]}
            value={unit}
            onChangeText={setUnit}
            placeholder="Unit"
            placeholderTextColor="#7d8da0"
          />
        </View>

        <View style={styles.chipRow}>
          {['low', 'medium', 'high', 'urgent'].map((urgency) => (
            <Pressable
              key={urgency}
              style={[styles.chip, urgencyKey === urgency && styles.chipActive]}
              onPress={() => setUrgencyKey(urgency)}
            >
              <Text style={[styles.chipText, urgencyKey === urgency && styles.chipTextActive]}>
                {labelize(urgency)}
              </Text>
            </Pressable>
          ))}
        </View>

        <TextInput
          style={[styles.input, styles.textArea]}
          value={description}
          onChangeText={setDescription}
          placeholder="Short reason or field note"
          placeholderTextColor="#7d8da0"
          multiline
          textAlignVertical="top"
        />

        <ActionButton
          label={submitting ? 'Submitting...' : 'Submit for validation'}
          icon="cube-outline"
          disabled={submitting}
          onPress={handleSubmit}
        />
      </View>

      <View style={styles.card}>
        <SectionHeader title="My requests" />
        {requests.length === 0 ? (
          <EmptyState icon="cube-outline" title="No resource requests yet" />
        ) : (
          requests.map((request) => (
            <View key={request.request_id} style={styles.requestRow}>
              <View style={styles.requestIcon}>
                <Ionicons name="cube-outline" size={18} color={palette.navActive} />
              </View>
              <View style={styles.requestText}>
                <Text style={styles.requestTitle}>
                  {request.item_name || request.resource_type} · {request.quantity} {request.unit || ''}
                </Text>
                <Text style={styles.requestMeta}>
                  {request.location || 'Field location'} · {formatDate(request.created_at)}
                </Text>
              </View>
              <View style={styles.requestActions}>
                <StatusBadge label={labelize(request.validation_status)} tone={request.validation_status} />
                {request.validation_status === 'needs_validation' ? (
                  <Pressable style={styles.cancelButton} onPress={() => onCancelRequest(request.request_id)}>
                    <Text style={styles.cancelText}>Cancel</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function labelize(value?: string) {
  if (!value) return 'Pending';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value?: string) {
  return formatPhilippineDateTime(value);
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  chip: {
    minHeight: 38,
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.card,
  },
  chipActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  chipText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '900',
  },
  chipTextActive: {
    color: '#fff',
  },
  input: {
    minHeight: 46,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
    backgroundColor: '#fff',
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  flexInput: {
    flex: 1,
  },
  textArea: {
    minHeight: 90,
    paddingTop: spacing.md,
  },
  requestRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  requestIcon: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  requestText: {
    flex: 1,
  },
  requestTitle: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  requestMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
  requestActions: {
    alignItems: 'flex-end',
    gap: 6,
  },
  cancelButton: {
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  cancelText: {
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '900',
  },
});
