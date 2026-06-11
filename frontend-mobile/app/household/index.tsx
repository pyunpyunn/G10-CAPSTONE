import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
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
import * as Network from 'expo-network';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { logoutMobile } from '@/api/auth';
import {
  completeHouseholdSetup,
  createTrustedHousehold,
  getHouseholdOverview,
  lookupTrustedHousehold,
  saveHouseholdMemberStatus,
  saveHouseholdStatus,
  updateHouseholdDeviceLocation,
  updateHouseholdMember,
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

const deviceUuidKey = 'resq_household_device_uuid';
const trustedPinKey = 'resq_household_trusted_pin';
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
  const [activeTab, setActiveTab] = useState<TabKey>('home');
  const [overview, setOverview] = useState<HouseholdOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [deviceUuid, setDeviceUuid] = useState('');
  const [realBatteryLevel, setRealBatteryLevel] = useState<number | null>(null);
  const [connectionLabel, setConnectionLabel] = useState('Offline');
  const [pendingStatus, setPendingStatus] = useState('safe');
  const [editingStatus, setEditingStatus] = useState(true);
  const [showHistory, setShowHistory] = useState(false);
  const [showQr, setShowQr] = useState(false);
  const [trustedPin, setTrustedPin] = useState('');
  const [pinError, setPinError] = useState('');
  const [showPin, setShowPin] = useState(false);
  const [pinAction, setPinAction] = useState<'open' | 'add'>('open');
  const [selectedTrusted, setSelectedTrusted] = useState<any>(null);
  const [viewingTrusted, setViewingTrusted] = useState<any>(null);
  const [showAddTrusted, setShowAddTrusted] = useState(false);
  const [trustedLookup, setTrustedLookup] = useState<any>(null);
  const [trustedLoading, setTrustedLoading] = useState(false);

  const loadLocalKeys = useCallback(async () => {
    const existingDeviceUuid = await getStoredItem(deviceUuidKey);
    const existingPin = await getStoredItem(trustedPinKey);

    if (existingDeviceUuid) {
      setDeviceUuid(existingDeviceUuid);
    } else {
      const nextUuid = `hh-${Date.now()}-${Math.random().toString(16).slice(2)}`;
      await setStoredItem(deviceUuidKey, nextUuid);
      setDeviceUuid(nextUuid);
    }

    setTrustedPin(existingPin || '');
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
      const savedStatus = data.current_status?.status_key || data.status_options?.[0]?.key || 'safe';
      setPendingStatus(savedStatus);
      setEditingStatus(!data.current_status);
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

    try {
      const network = await Network.getNetworkStateAsync();

      if (!network.isConnected) {
        setConnectionLabel('Offline');
      } else {
        setConnectionLabel(labelizeNetwork(network.type));
      }
    } catch {
      setConnectionLabel('Online');
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

  async function handleSaveDeviceUser(memberId: string) {
    const payload: any = {
      device_uuid: deviceUuid,
      member_id: memberId,
      location_permission_status: 'granted',
      battery_level: realBatteryLevel ?? undefined,
    };

    if (currentDevice?.latitude && currentDevice?.longitude) {
      payload.latitude = currentDevice.latitude;
      payload.longitude = currentDevice.longitude;
      payload.location_label = currentDevice.last_location_label;
    } else if (overview?.geotag?.latitude && overview?.geotag?.longitude) {
      payload.latitude = overview.geotag.latitude;
      payload.longitude = overview.geotag.longitude;
      payload.location_label = overview.geotag.location_label;
    }

    try {
      await updateHouseholdDeviceLocation(payload);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save device user', errorMessage(error));
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

    try {
      await saveHouseholdStatus({
        status_key: pendingStatus,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
        notes: pendingStatus === 'needs_help' ? 'Household requested assistance from mobile.' : null,
      });
      Alert.alert('Status saved', 'Your household status update was sent to HQ.');
      setEditingStatus(false);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save status', errorMessage(error));
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

  function openTrusted(household: any) {
    const status = String(household.validation_status || '').toLowerCase();

    if (!['validated', 'approved'].includes(status)) {
      Alert.alert(
        'Trusted household pending',
        'This connection must be validated before you can open its member and status details.'
      );
      return;
    }

    setSelectedTrusted(household);
    setPinAction('open');
    setPinError('');
    setShowPin(true);
  }

  function openAddTrusted() {
    setPinAction('add');
    setPinError('');

    if (trustedPin) {
      setShowAddTrusted(true);
      return;
    }

    setShowPin(true);
  }

  async function handlePinConfirm(pin: string) {
    if (pin.length !== 4) {
      setPinError('Enter a 4-digit PIN.');
      return;
    }

    if (!trustedPin) {
      await setStoredItem(trustedPinKey, pin);
      setTrustedPin(pin);
      setShowPin(false);

      if (pinAction === 'add') {
        setShowAddTrusted(true);
      } else if (selectedTrusted) {
        setViewingTrusted(selectedTrusted);
      }

      return;
    }

    if (pin !== trustedPin) {
      setPinError('PIN did not match.');
      return;
    }

    setShowPin(false);
    setPinError('');

    if (pinAction === 'add') {
      setShowAddTrusted(true);
    } else if (selectedTrusted) {
      setViewingTrusted(selectedTrusted);
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
      Alert.alert('Trusted household', response.message || 'This trusted household request is pending validation.');
      setShowAddTrusted(false);
      setTrustedLookup(null);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to save trusted household', errorMessage(error));
    } finally {
      setTrustedLoading(false);
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
          onOpenQr={() => setShowQr(true)}
          onAddTrusted={openAddTrusted}
          onOpenTrusted={openTrusted}
          onBackFamily={() => setViewingTrusted(null)}
        />
      );
    }

    if (activeTab === 'profile') {
      return (
        <HouseholdProfileScreen
          overview={overview}
          deviceUuid={deviceUuid}
          currentDevice={currentDevice}
          realBatteryLevel={realBatteryLevel}
          connectionLabel={connectionLabel}
          onSaveDeviceUser={handleSaveDeviceUser}
          onUpdateMember={handleUpdateMember}
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
        showHistory={showHistory}
        onSelectStatus={setPendingStatus}
        onSaveStatus={handleSaveStatus}
        onEditStatus={() => setEditingStatus(true)}
        onToggleHistory={() => setShowHistory((value) => !value)}
        onOpenQr={() => setShowQr(true)}
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
      <HouseholdHeader
        connectionLabel={connectionLabel}
        onRefresh={() => loadOverview(true)}
      />

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={[styles.content, overview.setup?.is_setup_complete && styles.contentWithTabs]}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
      >
        {renderContent()}
      </ScrollView>

      {overview.setup?.is_setup_complete ? (
        <View style={styles.tabBar}>
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
        hasPin={Boolean(trustedPin)}
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

function labelizeNetwork(type?: Network.NetworkStateType) {
  if (type === Network.NetworkStateType.WIFI) return 'Wi-Fi';
  if (type === Network.NetworkStateType.CELLULAR) return 'Cellular';
  if (type === Network.NetworkStateType.ETHERNET) return 'Ethernet';
  if (type === Network.NetworkStateType.NONE) return 'Offline';
  return 'Online';
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
  contentWithTabs: {
    paddingBottom: 100,
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
