import { useMobileOverview } from '@/hooks/useMobileOverview';
import { OfflineBanner } from '@/components/OfflineBanner';
import { MobileTabPanels } from '@/components/MobileTabPanels';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  AppState,
  Alert,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import * as Battery from 'expo-battery';
import * as Location from 'expo-location';
import * as Network from 'expo-network';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { logoutMobile, resetHouseholdDeviceOwner } from '@/api/auth';
import { savePushRegistration } from '@/api/device';
import {
  completeHouseholdSetup,
  createTrustedHousehold,
  deleteTrustedHousehold,
  deleteHouseholdNotification,
  getHouseholdOverview,
  lookupTrustedHousehold,
  markHouseholdNotificationsRead,
  saveHouseholdGeotag,
  saveTrustedHouseholdMemberStatus,
  uploadHouseholdProfilePhoto,
  saveTrustedPin,
  respondToTrustedHousehold,
  updateHouseholdDeviceLocation,
  updateHouseholdMember,
  verifyTrustedHouseholdPin,
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
import { palette, spacing } from '@/constants/resqTheme';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { getPushRegistration } from '@/utils/pushNotifications';
import {
  discardFailedHouseholdStatusReports,
  enqueueHouseholdStatusReport,
  getHouseholdStatusOutboxCounts,
  syncPendingHouseholdStatuses,
} from '@/utils/householdStatusOutbox';
import { useWebViewMode } from '@/utils/viewMode';
import { WebAppShell } from '@/components/WebAppShell';
import { styles } from './styles';

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
  const [statusQueueCounts, setStatusQueueCounts] = useState({ pending: 0, failed: 0 });
  const [showHistory, setShowHistory] = useState(false);
  const [showQr, setShowQr] = useState(false);
  const [trustedPinConfigured, setTrustedPinConfigured] = useState(false);
  const [pinError, setPinError] = useState('');
  const [pinSaving, setPinSaving] = useState(false);
  const [showPin, setShowPin] = useState(false);
  const [pinAction, setPinAction] = useState<'open' | 'set' | 'change'>('open');
  const [selectedTrusted, setSelectedTrusted] = useState<any>(null);
  const [viewingTrusted, setViewingTrusted] = useState<any>(null);
  const [showAddTrusted, setShowAddTrusted] = useState(false);
  const [trustedLookup, setTrustedLookup] = useState<any>(null);
  const [trustedLoading, setTrustedLoading] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [notificationBusyId, setNotificationBusyId] = useState('');
  const [loggingOut, setLoggingOut] = useState(false);
  const logoutPending = useRef(false);
  const overviewTrustedPinConfigured = Boolean(overview?.trusted?.pin_configured);
  const hasTrustedPin = overviewTrustedPinConfigured || trustedPinConfigured;
  const statusOptionsSignature = JSON.stringify(overview?.status_options || []);

  const openNotifications = useCallback(() => {
    setShowNotifications(true);
    void markHouseholdNotificationsRead()
      .then(() => {
        setOverview((current) => current ? {
          ...current,
          trusted: {
            ...current.trusted,
            notifications: (current.trusted?.notifications || []).map((notice: any) => ({ ...notice, read: true })),
          },
        } : current);
      })
      .catch(() => {
        // The panel remains usable; the next overview refresh can retry marking them read.
      });
  }, [setOverview]);

  const refreshStatusQueueCounts = useCallback(async () => {
    setStatusQueueCounts(await getHouseholdStatusOutboxCounts());
  }, []);

  const syncQueuedStatusReports = useCallback(async () => {
    const result = await syncPendingHouseholdStatuses();
    await refreshStatusQueueCounts();
    if (result.syncedIds.length) void refreshOverview(true, true, true);
    const rejected = result.failures.find((failure) => !failure.retryable);
    if (rejected) {
      Alert.alert('Status not synced', `The status is stored on this device but was not saved to the server. ${rejected.message}`);
    }
    return result;
  }, [refreshOverview, refreshStatusQueueCounts]);

  const handleDiscardFailedStatusReports = useCallback(() => {
    Alert.alert(
      'Dismiss unsynced reports?',
      'These reports were rejected and were not saved to the server. Removing them from this device cannot be undone.',
      [
        { text: 'Keep reports', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: () => {
            void discardFailedHouseholdStatusReports()
              .then(refreshStatusQueueCounts)
              .catch((error) => Alert.alert('Unable to remove reports', errorMessage(error)));
          },
        },
      ],
    );
  }, [refreshStatusQueueCounts]);

  useEffect(() => {
    let active = true;
    const runSync = () => {
      void syncQueuedStatusReports().catch((error) => {
        if (active) Alert.alert('Unable to sync saved status reports', errorMessage(error));
      });
    };
    void refreshStatusQueueCounts().catch((error) => {
      if (active) Alert.alert('Unable to read saved status reports', errorMessage(error));
    });
    runSync();
    const networkSubscription = Network.addNetworkStateListener((state) => {
      if (state.isConnected && state.isInternetReachable !== false) runSync();
    });
    const appStateSubscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') runSync();
    });
    const retryTimer = setInterval(runSync, 30000);
    return () => {
      active = false;
      clearInterval(retryTimer);
      networkSubscription.remove();
      appStateSubscription.remove();
    };
  }, [refreshStatusQueueCounts, syncQueuedStatusReports]);

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

  useEffect(() => {
    if (activeTab !== 'trusted' || !overview?.setup?.is_setup_complete || hasTrustedPin) return;
    setPinAction('set');
    setPinError('');
    setShowPin(true);
  }, [activeTab, overview?.setup?.is_setup_complete, hasTrustedPin]);

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

  async function submitQueuedStatus(
    kind: 'household' | 'member',
    payload: Record<string, unknown>,
    memberId?: string,
  ): Promise<boolean> {
    const expectedEventId = String(overview?.active_event?.event_id || '');
    if (!expectedEventId) throw new Error('A status can only be reported during an active disaster event.');

    const reportId = await enqueueHouseholdStatusReport({ kind, memberId, expectedEventId, payload });
    await refreshStatusQueueCounts();
    const result = await syncQueuedStatusReports();
    if (result.syncedIds.includes(reportId)) return true;

    const failure = result.failures.find((item) => item.id === reportId);
    if (!failure || failure.retryable) {
      Alert.alert(
        'Status saved on this device',
        'This report has not yet been saved to the server. It will sync automatically when the internet connection returns.',
      );
    }
    return false;
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
      const saved = await submitQueuedStatus('household', {
        status_key: pendingStatus,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
        notes: pendingStatus === 'needs_help' ? 'Household requested assistance from mobile.' : null,
      });
      if (!saved) return;
      const selected = overview.status_options.find((option) => option.key === pendingStatus);
      setOverview((current) => current ? { ...current, member_status: {
        member_id: current.profile.user.member_id,
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
      const saved = await submitQueuedStatus('member', {
        status_key: statusKey,
        device_uuid: deviceUuid,
        battery_level: realBatteryLevel ?? undefined,
        ...locationPayload,
      }, memberId);
      if (!saved) return;
      void loadOverview(true, true, true);
    } catch (error: any) {
      Alert.alert('Unable to save member status', errorMessage(error));
    }
  }

  async function handleSaveTrustedMemberStatus(connectionId: string, memberId: string, statusKey: string) {
    if (!requireTrustedPin()) return;

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

  function requireTrustedPin() {
    if (hasTrustedPin) return true;

    setPinAction('set');
    setPinError('');
    setShowPin(true);
    return false;
  }

  function openTrusted(household: any) {
    if (!requireTrustedPin()) return;

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
    if (!requireTrustedPin()) return;

    setTrustedLookup(null);
    setShowAddTrusted(true);
  }

  function openChangeTrustedPin() {
    if (!requireTrustedPin()) return;

    setPinAction('change');
    setPinError('');
    setShowPin(true);
  }

  async function handlePinConfirm({
    pin,
    currentPin,
    currentPassword,
  }: {
    pin: string;
    currentPin?: string;
    currentPassword?: string;
  }) {
    setPinSaving(true);
    setPinError('');

    try {
      if (pinAction === 'set' || pinAction === 'change') {
        await saveTrustedPin({
          pin,
          pin_confirmation: pin,
          current_pin: pinAction === 'change' ? currentPin : undefined,
          current_password: pinAction === 'change' ? currentPassword : undefined,
        });
        setTrustedPinConfigured(true);
      }

      if (pinAction === 'open' && selectedTrusted) {
        await verifyTrustedHouseholdPin(String(selectedTrusted.connection_id), pin);
        setShowPin(false);
        setViewingTrusted(selectedTrusted);
      } else if (pinAction === 'set') {
        setShowPin(false);
        Alert.alert('Household PIN', 'Your household PIN was set.');
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
    if (!requireTrustedPin()) return;

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

  function handleDeleteNotification(notification: any) {
    const notificationId = String(notification.notification_id);
    Alert.alert(
      'Delete notification?',
      'This notification will be permanently removed from your notification list.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: () => {
            setNotificationBusyId(notificationId);
            void deleteHouseholdNotification(notificationId)
              .then(() => {
                setOverview((current) => current ? {
                  ...current,
                  trusted: {
                    ...current.trusted,
                    notifications: (current.trusted?.notifications || [])
                      .filter((notice: any) => String(notice.notification_id) !== notificationId),
                  },
                } : current);
              })
              .catch((error) => Alert.alert('Unable to delete notification', errorMessage(error)))
              .finally(() => setNotificationBusyId(''));
          },
        },
      ],
    );
  }

  function handleRejectTrustedRequest(request: any) {
    if (!requireTrustedPin()) return;

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
    if (!requireTrustedPin()) return;

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
    if (!requireTrustedPin()) return;

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
    if (!requireTrustedPin()) return;

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
          canChangeTrustedPin={hasTrustedPin}
          pinSetupRequired={!hasTrustedPin}
          onRequireTrustedPin={requireTrustedPin}
          onAddTrusted={openAddTrusted}
          onChangeTrustedPin={openChangeTrustedPin}
          onOpenTrusted={openTrusted}
          onDeleteTrusted={handleDeleteTrusted}
          onBackFamily={() => setViewingTrusted(null)}
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
      <OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={false} pendingStatuses={statusQueueCounts.pending} failedStatuses={statusQueueCounts.failed} onDiscardFailedStatuses={handleDiscardFailedStatusReports} />
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

  const unreadNotificationCount = (overview.trusted?.notifications || []).filter((notice: any) => !notice.read).length;
  const notificationCount = (overview.trusted?.incoming_requests?.length || 0) + unreadNotificationCount;
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
            openNotifications();
          }}
          onOpenProfile={() => setActiveTab('profile')}
          onSelectKey={(key) => setActiveTab(key as TabKey)}
          onLogout={() => void handleLogout()}
          refreshing={refreshing}
          onRefresh={() => void loadOverview(true)}
          banner={<OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} pendingStatuses={statusQueueCounts.pending} failedStatuses={statusQueueCounts.failed} onDiscardFailedStatuses={handleDiscardFailedStatusReports} />}
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
              openNotifications();
            }}
          />

          <OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} pendingStatuses={statusQueueCounts.pending} failedStatuses={statusQueueCounts.failed} onDiscardFailedStatuses={handleDiscardFailedStatusReports} />

          <ScrollView
            removeClippedSubviews={false}
            style={styles.scroll}
            contentContainerStyle={[
              styles.content,
              overview.setup?.is_setup_complete && { paddingBottom: 104 + insets.bottom },
            ]}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
          >
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
                    style={({ pressed }) => [
                      styles.tabButton,
                      tab.center && styles.centerTab,
                      isActive && !tab.center && styles.activeTab,
                      pressed && !tab.center && styles.tabPressed,
                    ]}
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
        onDeleteNotice={handleDeleteNotification}
      />
      <TrustedPinModal
        visible={showPin}
        mode={pinAction === 'open' ? 'verify' : pinAction}
        householdName={selectedTrusted?.family_name}
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
