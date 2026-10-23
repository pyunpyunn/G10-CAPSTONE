import { useEffect, useState } from 'react';
import { Alert, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import MapView, { Marker } from 'react-native-maps';
import { getRescuerRequestLocations } from '@/api/rescuer';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { formatPhilippineDateTime } from '@/utils/time';
import { ActionButton, EmptyState, RescuerSelect, SectionHeader, StatusBadge } from './RescuerUI';

type ResourceRequestProps = {
  requests: any[];
  assignments: any[];
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

type RequestLocationOption = {
  id: string;
  type: string;
  label: string;
  address: string;
  subtitle: string;
  latitude: number | null;
  longitude: number | null;
};

type MappedRequestLocationOption = RequestLocationOption & {
  latitude: number;
  longitude: number;
};

export function ResourceRequestScreen({
  requests,
  assignments,
  categoryOptions,
  onSubmitRequest,
  onCancelRequest,
}: ResourceRequestProps) {
  const categories = (categoryOptions?.length ? categoryOptions : defaultCategories)
    .filter((category) => category.key !== 'vehicle');
  const [location, setLocation] = useState('');
  const [locationOptions, setLocationOptions] = useState<any[]>([]);
  const [locationsLoading, setLocationsLoading] = useState(true);
  const [locationsError, setLocationsError] = useState('');
  const [locationsBarangay, setLocationsBarangay] = useState('the barangay');
  const [isLocationInputFocused, setIsLocationInputFocused] = useState(false);
  const [isLocationPickerOpen, setIsLocationPickerOpen] = useState(false);
  const [locationQuery, setLocationQuery] = useState('');
  const [pinnedLocation, setPinnedLocation] = useState<{ latitude: number; longitude: number } | null>(null);
  const [requestCategory, setRequestCategory] = useState(categories[0]?.key || 'resource');
  const [resourceType, setResourceType] = useState('');
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [personnelCount, setPersonnelCount] = useState('');
  const [cluster, setCluster] = useState('');
  const [unit, setUnit] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showCompletedRequests, setShowCompletedRequests] = useState(false);
  const isPersonnelRequest = requestCategory === 'personnel';
  useEffect(() => {
    let isCurrent = true;

    getRescuerRequestLocations()
      .then((result) => {
        if (!isCurrent) return;
        setLocationOptions(result.locations || []);
        setLocationsBarangay(result.barangay || 'the barangay');
        setLocationsError('');
        setLocationsLoading(false);
      })
      .catch((error) => {
        if (!isCurrent) return;
        setLocationsError(
          error?.response?.data?.message
          || error?.userMessage
          || 'Saved barangay locations could not be loaded. You can still enter a location manually.'
        );
        setLocationsLoading(false);
      });

    return () => {
      isCurrent = false;
    };
  }, []);

  const allLocations = mergeLocationOptions(locationOptions, assignments);
  const locationSuggestions = location.trim()
    ? allLocations
        .filter((item) => `${item.label} ${item.address} ${item.subtitle || ''}`.toLowerCase().includes(location.trim().toLowerCase()))
        .slice(0, 5)
    : [];
  const filteredLocationOptions = allLocations.filter((item) =>
    `${item.label} ${item.address} ${item.subtitle || ''}`.toLowerCase().includes(locationQuery.trim().toLowerCase())
  );
  const mappedLocationOptions = allLocations.filter(hasCoordinates);
  const firstMappedLocation = mappedLocationOptions[0];
  const mapInitialRegion = firstMappedLocation
    ? {
        latitude: firstMappedLocation.latitude,
        longitude: firstMappedLocation.longitude,
        latitudeDelta: 0.06,
        longitudeDelta: 0.06,
      }
    : {
        latitude: 10.3157,
        longitude: 123.8854,
        latitudeDelta: 0.06,
        longitudeDelta: 0.06,
      };
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
      setPinnedLocation(null);
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

        <View style={styles.locationSection}>
          <Text style={styles.fieldLabel}>Request location</Text>
          <TextInput
            style={styles.input}
            value={location}
            onFocus={() => setIsLocationInputFocused(true)}
            onBlur={() => setIsLocationInputFocused(false)}
            onChangeText={(value) => {
              setLocation(value);
              setPinnedLocation(null);
            }}
            placeholder={`Search locations in ${locationsBarangay}`}
            placeholderTextColor="#7d8da0"
            returnKeyType="search"
            accessibilityLabel="Request location"
          />
          {isLocationInputFocused && location.trim().length > 0 ? (
            <View style={styles.suggestionList}>
              {locationSuggestions.length ? (
                locationSuggestions.map((item) => (
                  <Pressable
                    key={item.id}
                    style={styles.suggestionRow}
                    onPressIn={() => {
                      setLocation(item.address || item.label);
                      setPinnedLocation(item.latitude !== null && item.longitude !== null
                        ? { latitude: item.latitude, longitude: item.longitude }
                        : null);
                      setIsLocationInputFocused(false);
                    }}
                    accessibilityRole="button"
                    accessibilityLabel={`Use ${item.label}`}
                  >
                    <View style={styles.suggestionIcon}>
                      <Ionicons name="location" size={17} color={palette.navActive} />
                    </View>
                    <View style={styles.locationResultText}>
                      <Text style={styles.locationResultLabel}>{item.label}</Text>
                      <Text style={styles.locationResultMeta}>{item.address || item.subtitle || 'Barangay location'}</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={17} color={palette.textSoft} />
                  </Pressable>
                ))
              ) : (
                <Text style={styles.noSuggestions}>
                  {locationsLoading
                    ? `Loading locations in ${locationsBarangay}...`
                    : locationsError || `No saved locations match. You can keep typing in ${locationsBarangay}.`}
                </Text>
              )}
            </View>
          ) : null}
          <Pressable
            style={styles.locationPickerButton}
            onPress={() => setIsLocationPickerOpen(true)}
            accessibilityRole="button"
          >
            <Ionicons name="map-outline" size={18} color={palette.navActive} />
            <Text style={styles.locationPickerText}>Browse barangay locations or pin on map</Text>
            <Ionicons name="chevron-forward" size={16} color={palette.textSoft} />
          </Pressable>
          {pinnedLocation ? (
            <Text style={styles.locationHint}>
              Pinned at {pinnedLocation.latitude.toFixed(5)}, {pinnedLocation.longitude.toFixed(5)}
            </Text>
          ) : null}
        </View>

        <Modal
          transparent
          visible={isLocationPickerOpen}
          animationType="slide"
          onRequestClose={() => setIsLocationPickerOpen(false)}
        >
          <View style={styles.modalOverlay}>
            <View style={styles.locationModal}>
              <View style={styles.locationModalHeader}>
                <View style={styles.locationModalTitleWrap}>
                  <Text style={styles.locationModalTitle}>Choose request location</Text>
                  <Text style={styles.locationModalSubtitle}>
                    Search saved addresses and local destinations in {locationsBarangay}, or tap the map to pin.
                  </Text>
                </View>
                <Pressable
                  style={styles.closeButton}
                  onPress={() => setIsLocationPickerOpen(false)}
                  accessibilityRole="button"
                  accessibilityLabel="Close location picker"
                >
                  <Ionicons name="close" size={20} color={palette.text} />
                </Pressable>
              </View>
              <View style={styles.locationSearchWrap}>
                <Ionicons name="search-outline" size={18} color={palette.textSoft} />
                <TextInput
                  style={styles.locationSearchInput}
                  value={locationQuery}
                  onChangeText={setLocationQuery}
                  placeholder="Search by household or area"
                  placeholderTextColor="#7d8da0"
                />
              </View>
              {Platform.OS !== 'web' ? (
                <MapView
                  style={styles.locationMap}
                  initialRegion={mapInitialRegion}
                  onPress={(event) => {
                    const coordinate = event.nativeEvent.coordinate;
                    setPinnedLocation(coordinate);
                    setLocation(`Pinned location (${coordinate.latitude.toFixed(5)}, ${coordinate.longitude.toFixed(5)})`);
                    setIsLocationPickerOpen(false);
                  }}
                >
                  {mappedLocationOptions.map((item) => (
                    <Marker
                      key={item.id}
                      coordinate={{ latitude: item.latitude, longitude: item.longitude }}
                      title={item.label}
                      description={item.subtitle}
                      onPress={() => {
                        setLocation(item.address || item.label);
                        setPinnedLocation({ latitude: item.latitude, longitude: item.longitude });
                        setIsLocationPickerOpen(false);
                      }}
                    />
                  ))}
                </MapView>
              ) : null}
              <Text style={styles.locationResultsTitle}>
                {allLocations.length ? `Locations in ${locationsBarangay}` : `No saved locations for ${locationsBarangay}`}
              </Text>
              {filteredLocationOptions.length ? (
                <ScrollView style={styles.locationResults} keyboardShouldPersistTaps="handled">
                  {filteredLocationOptions.map((item) => (
                    <Pressable
                      key={item.id}
                      style={styles.locationResult}
                      onPress={() => {
                        setLocation(item.address || item.label);
                        setPinnedLocation(item.latitude !== null && item.longitude !== null
                          ? { latitude: item.latitude, longitude: item.longitude }
                          : null);
                        setIsLocationPickerOpen(false);
                      }}
                    >
                      <View style={styles.locationResultIcon}>
                        <Ionicons name="location" size={17} color={palette.navActive} />
                      </View>
                      <View style={styles.locationResultText}>
                        <Text style={styles.locationResultLabel}>{item.label}</Text>
                        <Text style={styles.locationResultMeta}>
                          {item.address || item.subtitle}
                          {item.latitude !== null && item.longitude !== null
                            ? ` · ${item.latitude.toFixed(4)}, ${item.longitude.toFixed(4)}`
                            : ''}
                        </Text>
                      </View>
                      <Ionicons name="push-outline" size={18} color={palette.navActive} />
                    </Pressable>
                  ))}
                </ScrollView>
              ) : (
                <Text style={styles.noLocationResults}>
                  {allLocations.length
                    ? 'No locations match your search.'
                    : locationsLoading
                      ? `Loading locations in ${locationsBarangay}...`
                      : locationsError || 'You can still enter a location manually above.'}
                </Text>
              )}
            </View>
          </View>
        </Modal>

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

function mergeLocationOptions(locations: any[], assignments: any[]): RequestLocationOption[] {
  const uniqueLocations = new Map<string, RequestLocationOption>();

  locations.forEach((item, index) => {
    const label = String(item.label || '').trim();
    const address = String(item.address || label).trim();
    const latitude = item.latitude === null || item.latitude === undefined ? null : Number(item.latitude);
    const longitude = item.longitude === null || item.longitude === undefined ? null : Number(item.longitude);

    if (!label) return;
    if (latitude !== null && (!Number.isFinite(latitude) || latitude < -90 || latitude > 90)) return;
    if (longitude !== null && (!Number.isFinite(longitude) || longitude < -180 || longitude > 180)) return;

    const type = String(item.type || 'location');
    const key = `${label.toLowerCase()}|${latitude ?? ''}|${longitude ?? ''}`;
    if (!uniqueLocations.has(key)) {
      uniqueLocations.set(key, {
        id: String(item.id || `${type}-${index}-${key}`),
        type,
        label,
        address,
        subtitle: String(item.subtitle || 'Barangay location'),
        latitude,
        longitude,
      });
    }
  });

  assignments.forEach((assignment) => {
    const householdId = String(assignment.household_id || '').trim();
    const latitude = Number(assignment.latitude);
    const longitude = Number(assignment.longitude);

    if (
      !householdId
      || assignment.latitude === null
      || assignment.latitude === undefined
      || assignment.longitude === null
      || assignment.longitude === undefined
      || !Number.isFinite(latitude)
      || !Number.isFinite(longitude)
      || latitude < -90
      || latitude > 90
      || longitude < -180
      || longitude > 180
    ) {
      return;
    }

    const label = String(
      assignment.destination_label
      || assignment.assigned_area
      || `Household ${householdId}`
    ).trim();
    const key = `${label.toLowerCase()}|${latitude}|${longitude}`;
    if (!uniqueLocations.has(key)) {
      uniqueLocations.set(key, {
        id: `assignment-${householdId}`,
        type: 'assigned_household',
        label,
        address: String(assignment.destination_label || assignment.assigned_area || label).trim(),
        subtitle: `Assigned household ${householdId}`,
        latitude,
        longitude,
      });
    }
  });

  return [...uniqueLocations.values()].sort((left, right) => left.label.localeCompare(right.label));
}

function hasCoordinates(
  location: RequestLocationOption
): location is MappedRequestLocationOption {
  return location.latitude !== null && location.longitude !== null;
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
    zIndex: 2,
  },
  suggestionList: {
    maxHeight: 230,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.card,
    shadowColor: palette.text,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12,
    shadowRadius: 8,
    elevation: 5,
  },
  suggestionRow: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: palette.border,
    paddingVertical: spacing.xs,
  },
  suggestionIcon: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.sm,
    backgroundColor: palette.secondary,
  },
  noSuggestions: {
    paddingHorizontal: spacing.xs,
    paddingVertical: spacing.md,
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
  },
  locationPickerButton: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.secondary,
  },
  locationPickerText: {
    flex: 1,
    color: palette.navActive,
    fontSize: 13,
    fontWeight: '800',
  },
  locationHint: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '700',
  },
  twoColumn: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  flexInput: {
    flex: 1,
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
    padding: spacing.lg,
  },
  locationModal: {
    width: '100%',
    maxWidth: 520,
    maxHeight: '92%',
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: palette.card,
  },
  locationModalHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.sm,
    marginBottom: spacing.md,
  },
  locationModalTitleWrap: {
    flex: 1,
  },
  locationModalTitle: {
    color: palette.text,
    fontSize: 17,
    fontWeight: '900',
  },
  locationModalSubtitle: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 12,
    lineHeight: 17,
  },
  closeButton: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: palette.secondary,
  },
  locationSearchWrap: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: '#fff',
  },
  locationSearchInput: {
    flex: 1,
    minHeight: 42,
    color: palette.text,
    fontSize: 14,
  },
  locationMap: {
    height: 250,
    marginTop: spacing.md,
    borderRadius: radius.md,
    overflow: 'hidden',
  },
  locationResultsTitle: {
    marginTop: spacing.md,
    marginBottom: spacing.xs,
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  locationResults: {
    flexShrink: 1,
    maxHeight: 220,
  },
  locationResult: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingVertical: spacing.xs,
  },
  locationResultIcon: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.sm,
    backgroundColor: palette.secondary,
  },
  locationResultText: {
    flex: 1,
    gap: 2,
  },
  locationResultLabel: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '800',
  },
  locationResultMeta: {
    color: palette.textSoft,
    fontSize: 11,
  },
  noLocationResults: {
    paddingVertical: spacing.md,
    color: palette.textSoft,
    fontSize: 13,
    lineHeight: 18,
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
