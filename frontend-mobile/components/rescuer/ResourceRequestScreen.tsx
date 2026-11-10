import { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, RescuerSelect, SectionHeader, StatusBadge } from './RescuerUI';

type ResourceRequestProps = {
  requests: any[];
  categoryOptions: any[];
  onSubmitRequest: (payload: any) => Promise<boolean>;
  onCancelRequest: (requestId: string) => Promise<void>;
};

const defaultCategories = [
  { key: 'resource', label: 'Resource' },
  { key: 'personnel', label: 'Personnel' },
];

const resourceTypeOptions = [
  'Tools and equipment',
  'Medical Supplies',
  'Vehicles and Transportation',
  'Others',
];

export function ResourceRequestScreen({
  requests,
  categoryOptions,
  onSubmitRequest,
  onCancelRequest,
}: ResourceRequestProps) {
  const categories = (categoryOptions?.length ? categoryOptions : defaultCategories)
    .filter((category) => category.key !== 'vehicle');
  const [location, setLocation] = useState('');
  const [requestCategory, setRequestCategory] = useState(categories[0]?.key || 'resource');
  const [resourceType, setResourceType] = useState('');
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [personnelCount, setPersonnelCount] = useState('');
  const [unit, setUnit] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showCompletedRequests, setShowCompletedRequests] = useState(false);
  const [showRequestHistory, setShowRequestHistory] = useState(false);
  const isPersonnelRequest = requestCategory === 'personnel';
  const visibleRequests = requests.filter((request) => {
    const status = String(request.validation_status || '').toLowerCase();
    const isCompleted = status === 'fulfilled' || status === 'completed';
    const isCancelled = status === 'cancelled' || status === 'canceled';

    return showCompletedRequests ? isCompleted : !isCompleted && !isCancelled;
  });

  async function handleSubmit() {
    const cleanLocation = location.trim();
    const cleanResourceType = resourceType.trim();
    const cleanItemName = itemName.trim();
    const cleanUnit = unit.trim();
    const cleanDescription = description.trim();
    const requestedQuantity = isPersonnelRequest ? personnelCount.trim() : quantity.trim();
    const quantityValue = Number(requestedQuantity);

    if (!cleanLocation) {
      Alert.alert('Missing delivery location', 'Enter where this request should be delivered.');
      return;
    }

    if (!isPersonnelRequest && !cleanResourceType) {
      Alert.alert('Missing category', 'Select a resource category before submitting.');
      return;
    }

    if (!requestedQuantity) {
      Alert.alert('Missing quantity', isPersonnelRequest
        ? 'Enter the number of personnel needed.'
        : 'Enter the quantity needed.');
      return;
    }

    if (!Number.isInteger(quantityValue) || quantityValue < 1) {
      Alert.alert('Invalid quantity', 'Quantity must be a whole number greater than zero.');
      return;
    }

    if (!isPersonnelRequest && !cleanUnit) {
      Alert.alert('Missing unit', 'Enter the unit, such as packs, kits, liters, or persons.');
      return;
    }

    if (!isPersonnelRequest && !cleanItemName) {
      Alert.alert('Missing item name', 'Enter the item name for the resource request.');
      return;
    }

    if (!cleanDescription || cleanDescription.length < 5) {
      Alert.alert('Missing reason', 'Enter a short reason so HQ can validate the request.');
      return;
    }

    setSubmitting(true);

    try {
      const submitted = await onSubmitRequest({
        complete_address: cleanLocation,
        location: cleanLocation,
        request_category: requestCategory,
        resource_type: isPersonnelRequest ? 'Personnel' : cleanResourceType,
        item_name: cleanItemName,
        quantity: quantityValue,
        unit: isPersonnelRequest ? 'persons' : cleanUnit,
        urgency_key: 'medium',
        description: cleanDescription,
      });

      if (!submitted) return;
      setLocation('');
      setResourceType('');
      setItemName('');
      setQuantity('1');
      setPersonnelCount('');
      setUnit('');
      setDescription('');
    } finally {
      setSubmitting(false);
    }
  }

  if (showRequestHistory) {
    return (
      <View style={styles.stack}>
        <View style={styles.card}>
          <SectionHeader
            title={showCompletedRequests ? 'Completed requests' : 'My requests'}
            action={(
              <Pressable
                style={styles.requestViewButton}
                onPress={() => setShowRequestHistory(false)}
                accessibilityRole="button"
                accessibilityLabel="Back to request form"
              >
                <Ionicons name="arrow-back" size={16} color="#fff" />
                <Text style={styles.requestViewButtonText}>Back</Text>
              </Pressable>
            )}
          />
          <View style={styles.historyFilterRow}>
            <Pressable
              style={[styles.historyFilterButton, !showCompletedRequests && styles.historyFilterButtonActive]}
              onPress={() => setShowCompletedRequests(false)}
              accessibilityRole="button"
              accessibilityState={{ selected: !showCompletedRequests }}
            >
              <Text style={[styles.historyFilterText, !showCompletedRequests && styles.historyFilterTextActive]}>
                My requests
              </Text>
            </Pressable>
            <Pressable
              style={[styles.historyFilterButton, showCompletedRequests && styles.historyFilterButtonActive]}
              onPress={() => setShowCompletedRequests(true)}
              accessibilityRole="button"
              accessibilityState={{ selected: showCompletedRequests }}
            >
              <Text style={[styles.historyFilterText, showCompletedRequests && styles.historyFilterTextActive]}>
                Completed
              </Text>
            </Pressable>
          </View>
          {visibleRequests.length === 0 ? (
            <EmptyState
              icon="cube-outline"
              title={showCompletedRequests ? 'No completed requests' : 'No pending requests'}
            />
          ) : (
            visibleRequests.map((request) => (
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

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader
          title="Request logistics"
          action={(
            <Pressable
              style={styles.requestViewButton}
              onPress={() => setShowRequestHistory(true)}
              accessibilityRole="button"
              accessibilityLabel="View my requests"
            >
              <Ionicons name="list-outline" size={16} color="#fff" />
              <Text style={styles.requestViewButtonText}>My requests</Text>
            </Pressable>
          )}
        />

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

        <View style={styles.locationSection}>
          <Text style={styles.fieldLabel}>Location</Text>
          <TextInput
            style={styles.input}
            value={location}
            onChangeText={setLocation}
            placeholder="Enter location"
            placeholderTextColor="#7d8da0"
            autoCapitalize="words"
            autoCorrect={false}
            multiline={false}
            returnKeyType="next"
            accessibilityLabel="Location"
          />
        </View>

        {isPersonnelRequest ? (
          <>
            <TextInput
              style={styles.input}
              value={itemName}
              onChangeText={setItemName}
              placeholder="Role or position"
              placeholderTextColor="#7d8da0"
            />

            <QuantityStepper
              value={personnelCount}
              onChange={setPersonnelCount}
              placeholder="Number of personnel needed"
            />
          </>
        ) : (
          <>
            <RescuerSelect
              title="Resource category"
              placeholder="Select category"
              options={resourceTypeOptions.map((option) => ({ key: option, label: option }))}
              value={resourceType}
              onChange={setResourceType}
              accessibilityLabel="Choose resource category"
            />

            <TextInput
              style={styles.input}
              value={itemName}
              onChangeText={setItemName}
              placeholder="Item name"
              placeholderTextColor="#7d8da0"
            />

            <View style={styles.twoColumn}>
              <QuantityStepper value={quantity} onChange={setQuantity} placeholder="Quantity" />
              <TextInput
                style={[styles.input, styles.flexInput]}
                value={unit}
                onChangeText={setUnit}
                placeholder="Unit"
                placeholderTextColor="#7d8da0"
              />
            </View>
          </>
        )}

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
          label={submitting ? 'Submitting...' : 'Submit to HQ'}
          icon="cube-outline"
          disabled={submitting}
          onPress={handleSubmit}
        />
      </View>
    </View>
  );
}

function labelize(value?: string) {
  if (!value) return 'Pending';
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function QuantityStepper({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
}) {
  const quantityValue = Number(value);
  const canDecrease = Number.isFinite(quantityValue) && quantityValue > 1;

  return (
    <View style={styles.quantityControl}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Decrease quantity"
        disabled={!canDecrease}
        style={[styles.quantityButton, !canDecrease && styles.quantityButtonDisabled]}
        onPress={() => onChange(String(quantityValue - 1))}
      >
        <Ionicons name="remove" size={18} color={palette.text} />
      </Pressable>
      <TextInput
        style={styles.quantityInput}
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor="#7d8da0"
        keyboardType="number-pad"
        textAlign="center"
      />
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Increase quantity"
        style={styles.quantityButton}
        onPress={() => onChange(String((Number.isInteger(quantityValue) && quantityValue > 0 ? quantityValue : 0) + 1))}
      >
        <Ionicons name="add" size={18} color={palette.text} />
      </Pressable>
    </View>
  );
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
    ...shadow,
  },
  requestViewButton: {
    minHeight: 36,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xs,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.navActive,
  },
  requestViewButtonText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '900',
  },
  historyFilterRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  historyFilterButton: {
    flex: 1,
    minHeight: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    backgroundColor: palette.page,
  },
  historyFilterButtonActive: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  historyFilterText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '900',
  },
  historyFilterTextActive: {
    color: '#fff',
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
  fieldLabel: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '900',
  },
  locationSection: {
    gap: spacing.xs,
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  flexInput: {
    flex: 1,
  },
  quantityControl: {
    flex: 1,
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    backgroundColor: '#fff',
  },
  quantityButton: {
    width: 40,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  quantityButtonDisabled: {
    opacity: 0.4,
  },
  quantityInput: {
    flex: 1,
    minWidth: 40,
    paddingHorizontal: spacing.xs,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
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