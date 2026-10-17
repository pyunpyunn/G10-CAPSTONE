import { useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
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
  const [isResourceTypeMenuOpen, setIsResourceTypeMenuOpen] = useState(false);
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [personnelCount, setPersonnelCount] = useState('');
  const [cluster, setCluster] = useState('');
  const [unit, setUnit] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showCompletedRequests, setShowCompletedRequests] = useState(false);
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
    const cleanCluster = cluster.trim();
    const cleanUnit = unit.trim();
    const cleanDescription = description.trim();
    const quantityValue = Number(quantity.trim());
    const personnelCountValue = Number(personnelCount.trim());

    if (!cleanLocation) {
      Alert.alert('Missing location', 'Enter the location where the request is needed.');
      return;
    }

    if (!cleanResourceType) {
      Alert.alert('Missing category', 'Select a resource category before submitting.');
      return;
    }

    if (isPersonnelRequest && !cleanCluster) {
      Alert.alert('Missing cluster', 'Enter the purok or cluster for this request.');
      return;
    }

    if (!isPersonnelRequest && !quantity.trim()) {
      Alert.alert('Missing quantity', 'Enter the quantity needed.');
      return;
    }

    if (!isPersonnelRequest && (!Number.isInteger(quantityValue) || quantityValue < 1)) {
      Alert.alert('Invalid quantity', 'Quantity must be a whole number greater than zero.');
      return;
    }

    if (isPersonnelRequest && (!Number.isInteger(personnelCountValue) || personnelCountValue < 1)) {
      Alert.alert('Invalid personnel count', 'Enter the whole number of rescuers needed.');
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
      await onSubmitRequest({
        location: cleanLocation,
        request_category: requestCategory,
        resource_type: cleanResourceType,
        cluster: isPersonnelRequest ? cleanCluster : undefined,
        item_name: isPersonnelRequest ? null : cleanItemName,
        quantity: isPersonnelRequest ? personnelCountValue : quantityValue,
        unit: isPersonnelRequest ? 'persons' : cleanUnit,
        urgency_key: 'medium',
        description: cleanDescription,
      });

      setLocation('');
      setResourceType('');
      setItemName('');
      setQuantity('1');
      setPersonnelCount('');
      setCluster('');
      setUnit('');
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

        {isPersonnelRequest && (
          <TextInput
            style={styles.input}
            value={cluster}
            onChangeText={setCluster}
            placeholder="Purok or cluster"
            placeholderTextColor="#7d8da0"
          />
        )}

        {isPersonnelRequest ? (
          <>
            <TextInput
              style={styles.input}
              value={resourceType}
              onChangeText={setResourceType}
              placeholder="Type of rescuer needed"
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
            <Pressable
              style={styles.dropdownButton}
              onPress={() => setIsResourceTypeMenuOpen(true)}
              accessibilityRole="button"
              accessibilityLabel="Choose resource category"
            >
              <View style={styles.dropdownValueRow}>
                <Text style={[styles.dropdownText, !resourceType && styles.dropdownPlaceholder]} numberOfLines={1}>
                  {resourceType || 'Select category'}
                </Text>
                <Ionicons name="chevron-down" size={18} color={palette.textSoft} />
              </View>
            </Pressable>

            <TextInput
              style={styles.input}
              value={itemName}
              onChangeText={setItemName}
              placeholder="Item name"
              placeholderTextColor="#7d8da0"
            />

            <Modal
              transparent
              visible={isResourceTypeMenuOpen}
              animationType="fade"
              onRequestClose={() => setIsResourceTypeMenuOpen(false)}
            >
              <Pressable style={styles.modalOverlay} onPress={() => setIsResourceTypeMenuOpen(false)}>
                <Pressable style={styles.modalContent} onPress={() => undefined}>
                  <ScrollView style={styles.modalList}>
                    {resourceTypeOptions.map((option) => (
                      <Pressable
                        key={option}
                        style={[styles.modalOption, resourceType === option && styles.modalOptionActive]}
                        onPress={() => {
                          setResourceType(option);
                          setIsResourceTypeMenuOpen(false);
                        }}
                      >
                        <Text style={[styles.modalOptionText, resourceType === option && styles.modalOptionTextActive]}>
                          {option}
                        </Text>
                      </Pressable>
                    ))}
                  </ScrollView>
                </Pressable>
              </Pressable>
            </Modal>

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

      <View style={styles.card}>
        <SectionHeader
          title={showCompletedRequests ? 'Completed requests' : 'My requests'}
          action={(
            <Pressable
              style={styles.requestViewButton}
              onPress={() => setShowCompletedRequests((current) => !current)}
              accessibilityRole="button"
              accessibilityLabel={showCompletedRequests ? 'Show my requests' : 'Show completed requests'}
            >
              <Ionicons
                name={showCompletedRequests ? 'list-outline' : 'checkmark-done-outline'}
                size={16}
                color="#fff"
              />
              <Text style={styles.requestViewButtonText}>
                {showCompletedRequests ? 'My requests' : 'Completed'}
              </Text>
            </Pressable>
          )}
        />
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
  dropdownButton: {
    minHeight: 46,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: '#fff',
    justifyContent: 'center',
  },
  dropdownValueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.xs,
  },
  dropdownText: {
    flex: 1,
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  dropdownPlaceholder: {
    color: '#7d8da0',
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
    padding: spacing.lg,
  },
  modalContent: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    padding: spacing.md,
  },
  modalList: {
    maxHeight: 320,
  },
  modalOption: {
    minHeight: 48,
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
  },
  modalOptionActive: {
    backgroundColor: palette.secondary,
  },
  modalOptionText: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  modalOptionTextActive: {
    color: palette.navActive,
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
