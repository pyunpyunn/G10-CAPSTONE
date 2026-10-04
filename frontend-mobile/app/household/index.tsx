import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Battery from 'expo-battery';
import * as Location from 'expo-location';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { logoutMobile } from '@/api/auth';
import { savePushRegistration } from '@/api/device';
import {
  completeHouseholdSetup,
  createTrustedHousehold,
  deleteTrustedHousehold,
  getHouseholdOverview,
  lookupTrustedHousehold,
  saveHouseholdMemberStatus,
  saveTrustedHouseholdMemberStatus,
  saveHouseholdStatus,
  saveTrustedPin,
  updateHouseholdDeviceLocation,
  updateHouseholdMember,
  verifyTrustedPin,
} from '@/api/household';
import type { HouseholdOverview } from '@/api/household';
import { HouseholdDashboardScreen, HouseholdTrustedScreen } from '@/components/household/HouseholdDashboardScreen';
import { HouseholdHeader } from '@/components/household/HouseholdHeader';
import {
  AddTrustedHouseholdModal,
  HouseholdQrModal,
  TrustedPinModal,
} from '@/components/household/HouseholdModals';
import { HouseholdProfileScreen } from '@/components/household/HouseholdProfileScreen';
import { HouseholdRouteScreen } from '@/components/household/HouseholdRouteScreen';
import { HouseholdSetupScreen } from '@/components/household/HouseholdSetupScreen';
import { HouseholdLoading } from '@/components/household/HouseholdUI';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { getPushRegistration } from '@/utils/pushNotifications';

const deviceUuidKey = 'resq_household_device_uuid';
type TabKey = 'home' | 'route' | 'trusted' | 'profile';
type TabButtonKey = TabKey | 'qr';

const tabs: { key: TabButtonKey; label: string; icon: keyof typeof Ionicons.glyphMap; center?: boolean }[] = [
  { key: 'home', label: 'Home', icon: 'home-outline' },
  { key: 'route', label: 'Map', icon: 'map-outline' },
  { key: 'qr', label: 'QR', icon: 'qr-code-outline', center: true },
  { key: 'trusted', label: 'Trusted', icon: 'people-outline' },
  { key: 'profile', label: 'Profile', icon: 'person-outline' },
];

export default function HouseholdHomeScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [activeTab, setActiveTab] = useState<TabKey>('home');
  const [overview, setOverview] = useState<HouseholdOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [deviceUuid, setDeviceUuid] = useState('');
  const [realBatteryLevel, setRealBatteryLevel] = useState<number | null>(null);
  const [pendingStatus, setPendingStatus] = useState('safe');
  const [editingStatus, setEditingStatus] = useState(true);
  const [savingStatus, setSavingStatus] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [showQr, setShowQr] = useState(false);
  const [trustedPinConfigured, setTrustedPinConfigured] = useState(false);
  const [pinError, setPinError] = useState('');
  const [pinSaving, setPinSaving] = useState(false);
  const [showPin, setShowPin] = useState(false);
  const [pinAction, setPinAction] = useState<'open' | 'add' | 'change'>('open');
  const [selectedTrusted, setSelectedTrusted] = useState<any>(null);
  const [viewingTrusted, setViewingTrusted] = useState<any>(null);
  const [showAddTrusted, setShowAddTrusted] = useState(false);
  const [trustedLookup, setTrustedLookup] = useState<any>(null);
  const [trustedLoading, setTrustedLoading] = useState(false);

  const loadLocalKeys = useCallback(async () => {
    const existingDeviceUuid = await getStoredItem(deviceUuidKey);
    if (existingDeviceUuid) {
      setDeviceUuid(existingDeviceUuid);
    } else {
      const nextUuid = `hh-${Date.now()}-${Math.random().toString(16).slice(2)}`;
      await setStoredItem(deviceUuidKey, nextUuid);
      setDeviceUuid(nextUuid);
    }

  }, []);

  const loadOverview = useCallback(async (isRefresh = false) => {
    if (isRefresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    try {
      const data = await getHouseholdOverview();
      setOverview(data);
      setTrustedPinConfigured(Boolean(data.trusted?.pin_configured));
      setViewingTrusted((current: any) =>
        current
          ? data.trusted?.households?.find((household: any) => household.connection_id === current.connection_id) || null
          : null
      );
      const savedStatus = data.current_status?.status_key || data.status_options?.[0]?.key || 'safe';
      setPendingStatus(savedStatus);
      setEditingStatus(true);
    } catch (error: any) {
      Alert.alert('Unable to load household data', errorMessage(error));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const refreshDeviceSensors = useCallback(async () => {
    try {
      const battery = await Battery.getBatteryLevelAsync();

      if (battery >= 0) {
        setRealBatteryLevel(Math.round(battery * 100));
      }
    } catch {
      setRealBatteryLevel(null);
    }

  }, []);

  const syncDeviceLocation = useCallback(async () => {
    try {
      const permission = await Location.getForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        return;
      }

      const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });

      await updateHouseholdDeviceLocation({
        device_uuid: deviceUuid,
        latitude: current.coords.latitude,
        longitude: current.coords.longitude,
        accuracy_m: current.coords.accuracy,
        location_label: 'Device live location',
        location_permission_status: 'granted',
        battery_level: realBatteryLevel ?? undefined,
      });
    } catch {
      // Silent heartbeat failure is acceptable; the screen still shows last saved data.
    }
  }, [deviceUuid, realBatteryLevel]);

  useEffect(() => {
    loadLocalKeys();
    loadOverview();
    refreshDeviceSensors();
  }, [loadLocalKeys, loadOverview, refreshDeviceSensors]);

  useEffect(() => {
    if (activeTab === 'trusted') {
      void loadOverview(true);
    }
  }, [activeTab, loadOverview]);

  useEffect(() => {
    refreshDeviceSensors();

    const batterySubscription = Battery.addBatteryLevelListener(({ batteryLevel }) => {
      if (batteryLevel >= 0) {
        setRealBatteryLevel(Math.round(batteryLevel * 100));
      }
    });

    const intervalId = setInterval(refreshDeviceSensors, 60000);

    return () => {
      batterySubscription.remove();
      clearInterval(intervalId);
    };
  }, [refreshDeviceSensors]);

  useEffect(() => {
    if (overview?.setup?.is_setup_complete && deviceUuid) {
      syncDeviceLocation();
    }
  }, [overview?.setup?.is_setup_complete, deviceUuid, syncDeviceLocation]);

  useEffect(() => {
    if (!deviceUuid) {
      return;
    }

    async function registerNotifications() {
      const registration = await getPushRegistration(deviceUuid);

      try {
        await savePushRegistration({
          device_uuid: deviceUuid,
          device_name: 'Household mobile',
          platform: Platform.OS as 'android' | 'ios',
          player_id: registration.playerId,
          push_token: registration.pushToken,
          push_provider: registration.pushProvider,
          one_signal_user_id: registration.oneSignalUserId,
          battery_level: realBatteryLevel,
          notification_permission_status: registration.permissionStatus,
        });
      } catch {
        // Registration retries the next time the authenticated mobile screen opens.
      }
    }

    registerNotifications();
  }, [deviceUuid, realBatteryLevel]);

  const currentDevice = useMemo(() => {
    if (!overview?.devices?.length || !deviceUuid) {
      return null;
    }

    return overview.devices.find((device: any) => device.device_uuid === deviceUuid) || null;
  }, [deviceUuid, overview?.devices]);

  async function handleLogout() {
    await logoutMobile();
    router.replace('/' as Href);
  }

  async function handleSetupComplete(payload: any) {
    try {
      await completeHouseholdSetup(payload);
      Alert.alert('Setup saved', 'Your household mobile setup is complete.');
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save setup', errorMessage(error));
    }
  }

  async function handleUpdateGeotag(payload: any) {
    try {
      await completeHouseholdSetup({
        ...payload,
        device_uuid: deviceUuid,
        device_name: 'Household mobile',
        platform: 'expo',
        photo_uri: null,
      });
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to update geotag', errorMessage(error));
      throw error;
    }
  }

  async function handleUpdateMember(memberId: string, payload: any) {
    try {
      await updateHouseholdMember(memberId, payload);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save member', errorMessage(error));
      throw error;
    }
  }

  async function handleSaveStatus() {
    if (!overview?.active_event) {
      Alert.alert('No active disaster', 'Status updates can only be saved during an active disaster event.');
      return;
    }

    const locationPayload: any = {};

    if (currentDevice?.latitude && currentDevice?.longitude) {
      locationPayload.latitude = currentDevice.latitude;
      locationPayload.longitude = currentDevice.longitude;
      locationPayload.location_label = currentDevice.last_location_label;
    } else if (overview?.geotag?.latitude && overview?.geotag?.longitude) {
      locationPayload.latitude = overview.geotag.latitude;
      locationPayload.longitude = overview.geotag.longitude;
      locationPayload.location_label = overview.geotag.location_label;
      locationPayload.location_accuracy_m = overview.geotag.accuracy_m;
    }

    setSavingStatus(true);

    try {
      await saveHouseholdStatus({
        status_key: pendingStatus,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
        notes: pendingStatus === 'needs_help' ? 'Household requested assistance from mobile.' : null,
      });
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save status', errorMessage(error));
    } finally {
      setSavingStatus(false);
    }
  }

  async function handleSaveMemberStatus(memberId: string, statusKey: string) {
    if (!overview?.active_event) {
      Alert.alert('No active disaster', 'Family member status can only be saved during an active disaster event.');
      return;
    }

    const locationPayload: any = {};

    if (currentDevice?.latitude && currentDevice?.longitude) {
      locationPayload.latitude = currentDevice.latitude;
      locationPayload.longitude = currentDevice.longitude;
      locationPayload.location_label = currentDevice.last_location_label;
    } else if (overview?.geotag?.latitude && overview?.geotag?.longitude) {
      locationPayload.latitude = overview.geotag.latitude;
      locationPayload.longitude = overview.geotag.longitude;
      locationPayload.location_label = overview.geotag.location_label;
      locationPayload.location_accuracy_m = overview.geotag.accuracy_m;
    }

    try {
      await saveHouseholdMemberStatus(memberId, {
        status_key: statusKey,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
      });
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save member status', errorMessage(error));
      throw error;
    }
  }

  async function handleSaveTrustedMemberStatus(connectionId: string, memberId: string, statusKey: string) {
    if (!overview?.active_event) {
      Alert.alert('No active disaster', 'Family member status can only be saved during an active disaster event.');
      return;
    }

    const locationPayload: any = {};

    if (currentDevice?.latitude && currentDevice?.longitude) {
      locationPayload.latitude = currentDevice.latitude;
      locationPayload.longitude = currentDevice.longitude;
      locationPayload.location_label = currentDevice.last_location_label;
    } else if (overview?.geotag?.latitude && overview?.geotag?.longitude) {
      locationPayload.latitude = overview.geotag.latitude;
      locationPayload.longitude = overview.geotag.longitude;
      locationPayload.location_label = overview.geotag.location_label;
      locationPayload.location_accuracy_m = overview.geotag.accuracy_m;
    }

    try {
      await saveTrustedHouseholdMemberStatus(connectionId, memberId, {
        status_key: statusKey,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
      });
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save trusted member status', errorMessage(error));
      throw error;
    }
  }

  function openTrusted(household: any) {
    setSelectedTrusted(household);
    setPinAction('open');
    setPinError('');
    setShowPin(true);
  }

  function openAddTrusted() {
    setPinAction('add');
    setPinError('');
    setShowPin(true);
  }

  function openChangeTrustedPin() {
    setPinAction('change');
    setPinError('');
    setShowPin(true);
  }

  async function handlePinConfirm({ pin, currentPin }: { pin: string; currentPin?: string }) {
    setPinSaving(true);
    setPinError('');

    try {
      if (!trustedPinConfigured || pinAction === 'change') {
        await saveTrustedPin({
          pin,
          pin_confirmation: pin,
          current_pin: pinAction === 'change' ? currentPin : undefined,
        });
        setTrustedPinConfigured(true);
      } else {
        await verifyTrustedPin(pin);
      }

      setShowPin(false);

      if (pinAction === 'add') {
        setShowAddTrusted(true);
      } else if (pinAction === 'open' && selectedTrusted) {
        setViewingTrusted(selectedTrusted);
      } else if (pinAction === 'change') {
        Alert.alert('Household PIN', 'Your household PIN was changed.');
      }
    } catch (error: any) {
      setPinError(errorMessage(error));
    } finally {
      setPinSaving(false);
    }
  }

  async function handleLookupTrusted(householdId: string) {
    if (!householdId) {
      Alert.alert('Missing household ID', 'Enter the household ID first.');
      return;
    }

    setTrustedLoading(true);

    try {
      const result = await lookupTrustedHousehold(householdId);
      setTrustedLookup(result);
    } catch (error: any) {
      Alert.alert('Unable to find household', errorMessage(error));
    } finally {
      setTrustedLoading(false);
    }
  }

  async function handleCreateTrusted(payload: any) {
    if (!payload.trusted_household_id || !payload.reason) {
      Alert.alert('Missing details', 'Enter the household ID and reason.');
      return;
    }

    setTrustedLoading(true);

    try {
      const response = await createTrustedHousehold(payload);
      Alert.alert('Trusted household', response.message || 'Trusted household added.');
      setShowAddTrusted(false);
      setTrustedLookup(null);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save trusted household', errorMessage(error));
    } finally {
      setTrustedLoading(false);
    }
  }

  function handleDeleteTrusted(household: any) {
    const householdName = household.household_name || household.family_name || 'this trusted household';

    Alert.alert(
      'Trusted household options',
      householdName,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: () => confirmDeleteTrusted(household),
        },
      ]
    );
  }

  function confirmDeleteTrusted(household: any) {
    const householdName = household.household_name || household.family_name || 'this trusted household';

    Alert.alert(
      'Delete trusted household?',
      `Remove ${householdName} from your trusted households? This only removes the trusted connection.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete household',
          style: 'destructive',
          onPress: () => {
            void deleteTrusted(household);
          },
        },
      ]
    );
  }

  async function deleteTrusted(household: any) {
    try {
      const response = await deleteTrustedHousehold(household.connection_id);
      setViewingTrusted(null);
      await loadOverview(true);
      Alert.alert('Trusted household removed', response.message || 'The trusted household was removed successfully.');
    } catch (error: any) {
      Alert.alert('Unable to delete trusted household', errorMessage(error));
    }
  }

  function renderContent() {
    if (!overview) {
      return null;
    }

    if (!overview.setup?.is_setup_complete) {
      return <HouseholdSetupScreen overview={overview} deviceUuid={deviceUuid} onComplete={handleSetupComplete} />;
    }

    if (activeTab === 'route') {
      return (
        <HouseholdRouteScreen
          geotag={overview.geotag}
          evacuationCenters={overview.evacuation_centers || []}
        />
      );
    }

    if (activeTab === 'trusted') {
      return (
        <HouseholdTrustedScreen
          overview={overview}
          viewingTrusted={viewingTrusted}
          onAddTrusted={openAddTrusted}
          onChangeTrustedPin={openChangeTrustedPin}
          onOpenTrusted={openTrusted}
          onDeleteTrusted={handleDeleteTrusted}
          onBackFamily={() => setViewingTrusted(null)}
          onSaveTrustedMemberStatus={handleSaveTrustedMemberStatus}
        />
      );
    }

    if (activeTab === 'profile') {
      return (
        <HouseholdProfileScreen
          overview={overview}
          onUpdateGeotag={handleUpdateGeotag}
          onLogout={handleLogout}
        />
      );
    }

    return (
      <HouseholdDashboardScreen
        overview={overview}
        pendingStatus={pendingStatus}
        editingStatus={editingStatus}
        savingStatus={savingStatus}
        showHistory={showHistory}
        onSelectStatus={setPendingStatus}
        onSaveStatus={handleSaveStatus}
        onEditStatus={() => setEditingStatus(true)}
        onToggleHistory={() => setShowHistory((value) => !value)}
        onOpenMap={() => setActiveTab('route')}
        onSaveMemberStatus={handleSaveMemberStatus}
      />
    );
  }

  if (loading || !overview || !deviceUuid) {
    return (
      <SafeAreaView style={styles.safe}>
        <HouseholdLoading label="Loading household mobile..." />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <HouseholdHeader isDisasterMode={Boolean(overview.active_event)} />

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={[
          styles.content,
          overview.setup?.is_setup_complete && { paddingBottom: 104 + insets.bottom },
        ]}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
      >
        {renderContent()}
      </ScrollView>

      {overview.setup?.is_setup_complete ? (
        <View style={[styles.tabBar, { bottom: Math.max(insets.bottom + 8, spacing.md) }]}>
          {tabs.map((tab) => {
            const isQrAction = tab.key === 'qr';
            const isActive = !isQrAction && activeTab === tab.key;

            return (
              <Pressable
                key={tab.key}
                style={[styles.tabButton, tab.center && styles.centerTab, isActive && !tab.center && styles.activeTab]}
                onPress={() => {
                  if (isQrAction) {
                    setShowQr(true);
                    return;
                  }

                  setActiveTab(tab.key as TabKey);
                }}
              >
                <View style={tab.center ? styles.centerTabCircle : undefined}>
                  <Ionicons
                    name={tab.icon}
                    size={tab.center ? 28 : 20}
                    color={isActive || tab.center ? '#fff' : palette.navMuted}
                  />
                </View>
                {!tab.center ? (
                  <Text style={[styles.tabLabel, isActive && styles.activeTabLabel]}>{tab.label}</Text>
                ) : null}
              </Pressable>
            );
          })}
        </View>
      ) : null}

      <HouseholdQrModal visible={showQr} qr={overview.qr} onClose={() => setShowQr(false)} />
      <TrustedPinModal
        visible={showPin}
        mode={pinAction === 'change' ? 'change' : trustedPinConfigured ? 'verify' : 'set'}
        saving={pinSaving}
        error={pinError}
        onClose={() => setShowPin(false)}
        onConfirm={handlePinConfirm}
      />
      <AddTrustedHouseholdModal
        visible={showAddTrusted}
        loading={trustedLoading}
        lookupResult={trustedLookup}
        onClose={() => setShowAddTrusted(false)}
        onLookup={handleLookupTrusted}
        onSubmit={handleCreateTrusted}
      />
    </SafeAreaView>
  );
}

function errorMessage(error: any) {
  if (error?.userMessage) {
    return error.userMessage;
  }

  const errors = error?.response?.data?.errors;

  if (errors) {
    const firstKey = Object.keys(errors)[0];
    return errors[firstKey]?.[0] || 'Please check the submitted details.';
  }

  return error?.response?.data?.message || 'Please check the API connection and try again.';
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: palette.page,
  },
  scroll: {
    flex: 1,
  },
  content: {
    padding: spacing.md,
    paddingBottom: spacing.xl,
  },
  tabBar: {
    position: 'absolute',
    right: spacing.lg,
    bottom: spacing.md,
    left: spacing.lg,
    height: 64,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    borderWidth: 1,
    borderColor: '#1f3e5a',
    borderRadius: radius.lg,
    paddingHorizontal: 8,
    backgroundColor: palette.nav,
  },
  tabButton: {
    width: 58,
    height: 52,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    borderRadius: radius.md,
  },
  activeTab: {
    backgroundColor: palette.navActive,
  },
  centerTab: {
    width: 70,
    height: 70,
    marginTop: -34,
  },
  centerTabCircle: {
    width: 58,
    height: 58,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 4,
    borderColor: palette.card,
    borderRadius: 29,
    backgroundColor: palette.unsafe,
  },
  tabLabel: {
    color: palette.navMuted,
    fontSize: 9,
    fontWeight: '900',
  },
  activeTabLabel: {
    color: '#fff',
  },
});
