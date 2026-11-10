import { useMobileOverview } from '@/hooks/useMobileOverview';
import { OfflineBanner } from '@/components/OfflineBanner';
import { MobileTabPanels } from '@/components/MobileTabPanels';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import Ionicons from '@expo/vector-icons/Ionicons';
import * as Battery from 'expo-battery';
import * as Location from 'expo-location';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { logoutMobile, resetHouseholdDeviceOwner } from '@/api/auth';
import { savePushRegistration } from '@/api/device';
import {
  completeHouseholdSetup,
  createTrustedHousehold,
  deleteTrustedHousehold,
  getHouseholdOverview,
  lookupTrustedHousehold,
  saveHouseholdMemberStatus,
  saveHouseholdGeotag,
  saveTrustedHouseholdMemberStatus,
  uploadHouseholdProfilePhoto,
  saveHouseholdStatus,
  saveTrustedPin,
  respondToTrustedHousehold,
  updateHouseholdDeviceLocation,
  updateHouseholdMember,
  verifyTrustedPin,
} from '@/api/household';
import type { HouseholdOverview, HouseholdProfilePhotoAsset } from '@/api/household';
import { HouseholdDashboardScreen, HouseholdTrustedScreen } from '@/components/household/HouseholdDashboardScreen';
import { HouseholdHeader } from '@/components/household/HouseholdHeader';
import {
  AddTrustedHouseholdModal,
  HouseholdNotificationsModal,
  HouseholdQrModal,
  TrustedPinModal,
} from '@/components/household/HouseholdModals';
import { HouseholdProfileScreen } from '@/components/household/HouseholdProfileScreen';
import { MobileLogoutOverlay } from '@/components/MobileLogoutOverlay';
import { HouseholdRouteScreen } from '@/components/household/HouseholdRouteScreen';
import { HouseholdSetupScreen } from '@/components/household/HouseholdSetupScreen';
import { HouseholdBadge, HouseholdButton, HouseholdEmpty, HouseholdLoading } from '@/components/household/HouseholdUI';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { getPushRegistration } from '@/utils/pushNotifications';
import { useWebViewMode } from '@/utils/viewMode';
import { WebAppShell } from '@/components/WebAppShell';

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
  const { isDesktopSite: isDesktop, isWideDesktop } = useWebViewMode();
  const [activeTab, setActiveTab] = useState<TabKey>('home');
  const { overview, setOverview, loading, refreshing, error: overviewError, offline, lastSyncedAt, loadOverview: refreshOverview }
    = useMobileOverview<HouseholdOverview>('household', getHouseholdOverview);
  const [deviceUuid, setDeviceUuid] = useState('');
  const [deviceKeyError, setDeviceKeyError] = useState('');
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
  const [pinAction, setPinAction] = useState<'open' | 'change'>('open');
  const [selectedTrusted, setSelectedTrusted] = useState<any>(null);
  const [viewingTrusted, setViewingTrusted] = useState<any>(null);
  const [showAddTrusted, setShowAddTrusted] = useState(false);
  const [trustedLookup, setTrustedLookup] = useState<any>(null);
  const [trustedLoading, setTrustedLoading] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [notificationBusyId, setNotificationBusyId] = useState('');
  const [loggingOut, setLoggingOut] = useState(false);
  const logoutPending = useRef(false);
  const statusOptionsSignature = JSON.stringify(overview?.status_options || []);

  useEffect(() => {
    const options: { key: string }[] = JSON.parse(statusOptionsSignature);
    if (!options.length) return;
    setPendingStatus((current) => options.some((option) => option.key === current) ? current : options[0].key);
    setEditingStatus(true);
  }, [statusOptionsSignature]);

  const loadLocalKeys = useCallback(async () => {
    setDeviceKeyError('');
    try {
      const existingDeviceUuid = await getStoredItem(deviceUuidKey);
      if (existingDeviceUuid) {
        setDeviceUuid(existingDeviceUuid);
      } else {
        const nextUuid = `hh-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        await setStoredItem(deviceUuidKey, nextUuid);
        setDeviceUuid(nextUuid);
      }
    } catch {
      setDeviceKeyError('Your device identity could not be loaded. Please try again.');
    }
  }, []);

  const loadOverview = useCallback(async (isRefresh = false, silent = false, afterWrite = false) => {
    const data = await refreshOverview(isRefresh, silent, afterWrite);
    if (data && !silent) {
      setPendingStatus(data.member_status?.status_key || data.status_options?.[0]?.key || 'safe');
      setEditingStatus(true);
    }
  }, [refreshOverview]);

  useEffect(() => {
    if (!overview) return;
    setTrustedPinConfigured(Boolean(overview.trusted?.pin_configured));
    setViewingTrusted((current: any) => current
      ? overview.trusted?.households?.find((household: any) => household.connection_id === current.connection_id
        && ['active', 'validated'].includes(household.validation_status)) || null : null);
  }, [overview]);

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
    if (logoutPending.current) return;
    logoutPending.current = true;
    setLoggingOut(true);
    try {
      await logoutMobile();
      router.replace('/' as Href);
    } catch (error: any) {
      logoutPending.current = false;
      setLoggingOut(false);
      Alert.alert('Unable to log out', error?.message || 'Please try again.');
    }
  }

  async function handleSetupComplete(payload: any) {
    try {
      const result = await completeHouseholdSetup(payload);
      setOverview((current) => current ? { ...current,
        setup: { ...current.setup, is_setup_complete: true, has_geotag: true, has_device: true },
        geotag: result.data?.geotag || { latitude: payload.latitude, longitude: payload.longitude, accuracy_m: payload.accuracy_m },
      } : current);
      Alert.alert('Setup saved', 'Your household mobile setup is complete.');
      void loadOverview(true, true, true);
    } catch (error: any) {
      Alert.alert('Unable to save setup', errorMessage(error));
    }
  }

  async function handleUpdateGeotag(payload: any) {
    try {
      const response = await saveHouseholdGeotag(payload);
      void loadOverview(true, true, true);
      return response;
    } catch (error: any) {
      Alert.alert('Unable to update geotag', errorMessage(error));
      throw error;
    }
  }

  async function handleUpdateMember(memberId: string, payload: any) {
    const saved = await updateHouseholdMember(memberId, payload);
    setOverview((current) => current && saved.user ? { ...current, profile: { ...current.profile, user: saved.user } } : current);
    void loadOverview(true, true, true);
  }

  async function handleUploadProfilePhoto(asset: HouseholdProfilePhotoAsset) {
    const savedUser = await uploadHouseholdProfilePhoto(asset);
    setOverview((current) => current ? {
      ...current,
      profile: { ...current.profile, user: { ...current.profile.user, profile_photo_uploaded: savedUser.profile_photo_uploaded } },
      members: current.members.map((member) => String(member.member_id) === String(savedUser.member_id)
        ? { ...member, profile_photo_uploaded: savedUser.profile_photo_uploaded }
        : member),
    } : current);
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
      const saved = await saveHouseholdStatus({
        status_key: pendingStatus,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
        notes: pendingStatus === 'needs_help' ? 'Household requested assistance from mobile.' : null,
      });
      const selected = overview.status_options.find((option) => option.key === pendingStatus);
      setOverview((current) => current ? { ...current, member_status: {
        member_id: current.profile.user.member_id, status_log_id: saved.data?.status_log_id,
        status_key: pendingStatus, status_label: selected?.label || pendingStatus,
        submitted_label: 'Just saved',
      } } : current);
      void loadOverview(true, true, true);
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
      const saved = await saveHouseholdMemberStatus(memberId, {
        status_key: statusKey,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
      });
      setOverview((current) => current ? { ...current, members: current.members.map((member) =>
        String(member.member_id) === memberId ? { ...member, current_status: saved.member_status } : member) } : current);
      void loadOverview(true, true, true);
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
      void loadOverview(true, true, true);
    } catch (error: any) {
      Alert.alert('Unable to save trusted member status', errorMessage(error));
      throw error;
    }
  }

  function openTrusted(household: any) {
    if (!['active', 'validated'].includes(household.validation_status)) {
      Alert.alert('Pending confirmation', 'This household must accept your request before you can view its members or statuses.');
      return;
    }

    setSelectedTrusted(household);
    setPinAction('open');
    setPinError('');
    setShowPin(true);
  }

  function openAddTrusted() {
    setTrustedLookup(null);
    setShowAddTrusted(true);
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

      if (pinAction === 'open' && selectedTrusted) {
        setShowPin(false);
        setViewingTrusted(selectedTrusted);
      } else if (pinAction === 'change') {
        setShowPin(false);
        Alert.alert('Household PIN', 'Your household PIN was changed.');
      }
    } catch (error: any) {
      setPinError(errorMessage(error));
    } finally {
      setNotificationBusyId('');
      setPinSaving(false);
    }
  }

  async function handleAcceptTrustedRequest(request: any) {
    const connectionId = String(request.connection_id);
    setNotificationBusyId(connectionId);
    try {
      await respondToTrustedHousehold(connectionId, 'accept');
      setShowNotifications(false);
      void loadOverview(true, true, true);
      Alert.alert('Trusted household accepted', 'The household request was accepted and added to Trusted Household.');
    } catch (error: any) {
      Alert.alert('Unable to accept request', errorMessage(error));
    } finally {
      setNotificationBusyId('');
    }
  }

  function handleRejectTrustedRequest(request: any) {
    const connectionId = String(request.connection_id);
    Alert.alert(
      'Reject trusted household request?',
      `${request.household_name || request.family_name || 'This household'} (${request.requesting_household_id}) will not be added to your trusted households.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Reject',
          style: 'destructive',
          onPress: async () => {
            setNotificationBusyId(connectionId);
            try {
              await respondToTrustedHousehold(connectionId, 'reject');
              void loadOverview(true, true, true);
            } catch (error: any) {
              Alert.alert('Unable to reject request', errorMessage(error));
            } finally {
              setNotificationBusyId('');
            }
          },
        },
      ]
    );
  }

  async function handleLookupTrusted(householdId: string) {
    if (!householdId) {
      Alert.alert('Missing household details', 'Enter the household ID or username first.');
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
    if (!payload.trusted_household_id || !payload.relationshipID || !payload.relationshipLabel || !payload.pin) {
      Alert.alert('Missing details', 'Complete the household, relationship, and PIN steps first.');
      return;
    }

    setTrustedLoading(true);

    try {
      const response = await createTrustedHousehold(payload);
      const targetHousehold = trustedLookup?.household_name || trustedLookup?.household_id;
      const responseMessage = response.message || 'Trusted household request sent.';
      Alert.alert(
        'Trusted household',
        targetHousehold ? `${responseMessage}\nHousehold: ${targetHousehold}` : responseMessage
      );
      setShowAddTrusted(false);
      setTrustedLookup(null);
      void loadOverview(true, true, true);
    } catch (error: any) {
      Alert.alert('Unable to save trusted household', errorMessage(error));
    } finally {
      setTrustedLoading(false);
    }
  }

  function handleDeleteTrusted(household: any) {
    const householdName = household.household_name || household.family_name || 'this trusted household';
    const isPending = household.validation_status === 'pending';

    Alert.alert(
      isPending ? 'Cancel pending request?' : 'Trusted household options',
      isPending
        ? `Cancel your pending request to ${householdName}?`
        : householdName,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: isPending ? 'Cancel request' : 'Delete',
          style: 'destructive',
          onPress: () => isPending ? void deleteTrusted(household) : confirmDeleteTrusted(household),
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
      void loadOverview(true, true, true);
      Alert.alert(
        household.validation_status === 'pending' ? 'Request cancelled' : 'Trusted household removed',
        response.message || (household.validation_status === 'pending' ? 'Your trusted household request was cancelled.' : 'The trusted household was removed successfully.')
      );
    } catch (error: any) {
      Alert.alert('Unable to delete trusted household', errorMessage(error));
    }
  }

  function renderContent(tab: TabKey) {
    if (!overview) {
      return null;
    }

    if (!overview.setup?.is_setup_complete) {
      return <HouseholdSetupScreen overview={overview} deviceUuid={deviceUuid} onComplete={handleSetupComplete} onUploadProfilePhoto={handleUploadProfilePhoto} />;
    }

    if (tab === 'route') {
      return (
        <HouseholdRouteScreen
          active={activeTab === 'route'}
          geotag={overview.geotag}
          evacuationCenters={overview.evacuation_centers || []}
        />
      );
    }

    if (tab === 'trusted') {
      return (
        <HouseholdTrustedScreen
          overview={overview}
          viewingTrusted={viewingTrusted}
          onAddTrusted={openAddTrusted}
          onChangeTrustedPin={openChangeTrustedPin}
          onOpenTrusted={openTrusted}
          onDeleteTrusted={handleDeleteTrusted}
          onBackFamily={() => setViewingTrusted(null)}
          respondingConnectionId={notificationBusyId || null}
          onRespondToIncomingRequest={(request, decision) => {
            if (decision === 'accept') void handleAcceptTrustedRequest(request);
            else handleRejectTrustedRequest(request);
          }}
          onSaveTrustedMemberStatus={handleSaveTrustedMemberStatus}
        />
      );
    }

    if (tab === 'profile') {
      return (
        <HouseholdProfileScreen
          overview={overview}
          onUpdateGeotag={handleUpdateGeotag}
          onSaveContact={(contactNumber) => handleUpdateMember(String(overview.profile.user.member_id), { contact_number: contactNumber })}
          onUploadProfilePhoto={handleUploadProfilePhoto}
          onResetDeviceOwner={async () => { await resetHouseholdDeviceOwner(); router.replace('/household-owner' as Href); }}
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

  if ((!loading && !overview && overviewError) || (deviceKeyError && !deviceUuid)) {
    return <SafeAreaView style={styles.safe}>
      <OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={false} />
      <View style={{ padding: spacing.lg, gap: spacing.md }}>
        <HouseholdEmpty
          icon="cloud-offline-outline"
          title={deviceKeyError ? 'Unable to prepare this device' : 'Unable to sync household data'}
          body={deviceKeyError || overviewError || 'Connect to the internet and try again.'}
        />
        <HouseholdButton label="Try again" icon="refresh-outline" onPress={() => { if (deviceKeyError) void loadLocalKeys(); else void loadOverview(); }} />
      </View>
    </SafeAreaView>;
  }

  if ((!overview && loading) || !overview || !deviceUuid) {
    return (
      <SafeAreaView style={styles.safe}>
        <HouseholdLoading label="Loading household mobile..." />
      </SafeAreaView>
    );
  }

  const notificationCount = (overview.trusted?.incoming_requests?.length || 0) + (overview.trusted?.notifications?.length || 0);
  const householdName = overview.profile?.household?.household_name || overview.profile?.user?.full_name || 'Household';
  const householdCode = overview.profile?.household?.household_id || 'Household Resident';

  const householdPageTitles: Record<TabKey, string> = {
    home: 'Household Status',
    route: 'Mapping',
    trusted: 'Trusted Households',
    profile: 'Profile',
  };

  return (
    <SafeAreaView edges={['left', 'right', 'bottom']} style={styles.safe}>
      {isDesktop && overview.setup?.is_setup_complete ? (
        <WebAppShell
          activeKey={activeTab}
          pageTitle={householdPageTitles[activeTab] || 'Household Status'}
          dashboardItem={{
            key: 'home',
            title: 'Dashboard',
            icon: 'grid-outline',
            keywords: 'home status family members broadcast',
          }}
          groups={[
            {
              title: 'Geospatial & Analytics',
              items: [
                {
                  key: 'route',
                  title: 'Mapping',
                  icon: 'map-outline',
                  keywords: 'evacuation route map centers',
                },
                {
                  key: 'qr',
                  title: 'Household QR Pass',
                  icon: 'qr-code-outline',
                  keywords: 'qr code pass scan',
                  onPress: () => setShowQr(true),
                },
              ],
            },
            {
              title: 'Response Operations',
              items: [
                {
                  key: 'trusted',
                  title: 'Trusted Households',
                  icon: 'people-outline',
                  keywords: 'trusted family network pin',
                },
              ],
            },
            {
              title: 'Personnel Access Control',
              items: [
                {
                  key: 'profile',
                  title: 'Profile',
                  icon: 'person-outline',
                  keywords: 'household profile geotag contact',
                },
              ],
            },
          ]}
          userName={householdName}
          userRole={householdCode}
          notificationCount={notificationCount}
          onOpenNotifications={() => {
            setShowNotifications(true);
          }}
          onOpenProfile={() => setActiveTab('profile')}
          onSelectKey={(key) => setActiveTab(key as TabKey)}
          onLogout={() => void handleLogout()}
          refreshing={refreshing}
          onRefresh={() => void loadOverview(true)}
          banner={<OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} />}
          rightRail={
            isWideDesktop ? (
              <>
                <View style={styles.desktopSummaryCard}>
                  <Text style={styles.desktopSummaryTitle}>Household Summary</Text>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Mode</Text>
                    <HouseholdBadge
                      label={overview.active_event ? 'Disaster Mode' : 'Standby'}
                      tone={overview.active_event ? 'danger' : 'safe'}
                    />
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Your Status</Text>
                    <HouseholdBadge
                      label={overview.member_status?.status_label || 'Safe'}
                      tone={overview.member_status?.status_key || 'safe'}
                    />
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Family Members</Text>
                    <Text style={styles.desktopSummaryValue}>{(overview.members || []).length}</Text>
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Trusted Households</Text>
                    <Text style={styles.desktopSummaryValue}>{(overview.trusted?.households || []).length}</Text>
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Evacuation Centers</Text>
                    <Text style={styles.desktopSummaryValue}>{(overview.evacuation_centers || []).length}</Text>
                  </View>
                </View>

                <View style={styles.desktopSummaryCard}>
                  <Text style={styles.desktopSummaryTitle}>Quick Actions</Text>
                  <HouseholdButton
                    label="Open Evacuation Map"
                    icon="map-outline"
                    tone="light"
                    onPress={() => setActiveTab('route')}
                  />
                  <HouseholdButton
                    label="Show Household QR"
                    icon="qr-code-outline"
                    onPress={() => setShowQr(true)}
                  />
                </View>
              </>
            ) : undefined
          }
        >
          {overviewError && !offline ? <View style={{ padding: spacing.sm, gap: spacing.xs }}>
            <Text accessibilityLiveRegion="polite" style={{ color: palette.textSoft }}>
              Could not refresh. Showing previously loaded data.
            </Text>
            <HouseholdButton label="Retry refresh" tone="light" onPress={() => { void loadOverview(true); }} />
          </View> : null}
          <MobileTabPanels activeKey={activeTab} renderPanel={renderContent} />
        </WebAppShell>
      ) : (
        <>
          <HouseholdHeader
            isDisasterMode={Boolean(overview.active_event)}
            notificationCount={notificationCount}
            onOpenNotifications={() => {
              setShowNotifications(true);
            }}
          />

          <OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} />

          <ScrollView
            removeClippedSubviews={false}
            style={styles.scroll}
            contentContainerStyle={[
              styles.content,
              overview.setup?.is_setup_complete && { paddingBottom: 104 + insets.bottom },
            ]}
            refreshControl={<RefreshControl enabled={activeTab !== 'route' && activeTab !== 'profile'} refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
          >
            {['route', 'profile'].includes(activeTab) ? <HouseholdButton label={refreshing ? 'Syncing...' : 'Refresh page data'} icon="refresh-outline" tone="light" disabled={refreshing} onPress={() => void loadOverview(true)} /> : null}
            {overviewError && !offline ? <View style={{ padding: spacing.sm, gap: spacing.xs }}>
              <Text accessibilityLiveRegion="polite" style={{ color: palette.textSoft }}>
                Could not refresh. Showing previously loaded data.
              </Text>
              <HouseholdButton label="Retry refresh" tone="light" onPress={() => { void loadOverview(true); }} />
            </View> : null}
            <MobileTabPanels activeKey={activeTab} renderPanel={renderContent} />
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
        </>
      )}

      <HouseholdQrModal visible={showQr} householdId={overview.profile.household?.household_id} qr={overview.qr} onClose={() => setShowQr(false)} />
      <HouseholdNotificationsModal
        visible={showNotifications}
        requests={overview.trusted?.incoming_requests || []}
        notices={overview.trusted?.notifications || []}
        busyId={notificationBusyId}
        onClose={() => setShowNotifications(false)}
        onAccept={handleAcceptTrustedRequest}
        onReject={handleRejectTrustedRequest}
      />
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
      <MobileLogoutOverlay visible={loggingOut} />
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
  desktopBody: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: palette.page,
  },
  desktopSidebar: {
    width: 260,
    backgroundColor: palette.card,
    borderRightWidth: 1,
    borderColor: palette.border,
    padding: spacing.md,
    justifyContent: 'space-between',
  },
  desktopProfileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: radius.md,
    backgroundColor: palette.page,
    borderWidth: 1,
    borderColor: palette.border,
    marginBottom: spacing.md,
  },
  desktopAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: palette.navActive,
    alignItems: 'center',
    justifyContent: 'center',
  },
  desktopHouseholdName: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '800',
  },
  desktopHouseholdCode: {
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '600',
    marginTop: 2,
  },
  desktopNavGroup: {
    flex: 1,
    gap: 6,
  },
  desktopNavItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: radius.md,
  },
  desktopNavItemActive: {
    backgroundColor: '#e0f2fe',
  },
  desktopNavItemQr: {
    backgroundColor: palette.unsafe,
    marginVertical: 4,
  },
  desktopNavText: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '700',
  },
  desktopNavTextActive: {
    color: palette.navActive,
    fontWeight: '800',
  },
  desktopSidebarFooter: {
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderColor: palette.border,
  },
  desktopLogoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: radius.md,
  },
  desktopLogoutText: {
    color: palette.unsafe,
    fontSize: 14,
    fontWeight: '800',
  },
  desktopMainScroll: {
    flex: 1,
  },
  desktopMainContent: {
    padding: spacing.lg,
    maxWidth: 920,
    width: '100%',
    alignSelf: 'center',
    paddingBottom: spacing.xl * 2,
  },
  desktopRightRail: {
    width: 300,
    borderLeftWidth: 1,
    borderColor: palette.border,
    backgroundColor: palette.card,
    padding: spacing.md,
    gap: spacing.md,
  },
  desktopSummaryCard: {
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: palette.page,
    borderWidth: 1,
    borderColor: palette.border,
    gap: 12,
  },
  desktopSummaryTitle: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '800',
  },
  desktopSummaryRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  desktopSummaryLabel: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '600',
  },
  desktopSummaryValue: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '800',
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
