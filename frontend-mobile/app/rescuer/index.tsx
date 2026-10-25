import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { onDisasterBroadcast } from '@/utils/mobileReadCache';
import {
  Alert,
  AppState,
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
import { SafeAreaView } from 'react-native-safe-area-context';
import { logoutMobile } from '@/api/auth';
import { savePushRegistration } from '@/api/device';
import {
  cancelResourceRequest,
  createFieldReport,
  createResourceRequest,
  getRescuerOverview,
  sendAssignmentLocation,
  updateAssignmentStatus,
  updateRescuerProfile,
} from '@/api/rescuer';
import type { RescuerOverview } from '@/api/rescuer';
import { FieldReportScreen } from '@/components/rescuer/FieldReportScreen';
import { RescuerDashboardScreen } from '@/components/rescuer/RescuerDashboardScreen';
import { RescuerHeader } from '@/components/rescuer/RescuerHeader';
import { RadioCommunicationScreen } from '@/components/rescuer/RadioCommunicationScreen';
import { RescueMapScreen } from '@/components/rescuer/RescueMapScreen';
import { ResponderProfileScreen } from '@/components/rescuer/ResponderProfileScreen';
import { ResourceRequestScreen } from '@/components/rescuer/ResourceRequestScreen';
import { ActionButton, EmptyState, LoadingState } from '@/components/rescuer/RescuerUI';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { getPushRegistration } from '@/utils/pushNotifications';
import { configureRescuerTracking, stopRescuerTracking, subscribeRescuerPosition } from '@/utils/rescuerLiveLocation';
import { endNavigationTrip, mountNavigationSync } from '@/utils/navigationRuntime';

type TabKey = 'dashboard' | 'map' | 'report' | 'resource' | 'profile' | 'radio';
const deviceUuidKey = 'resq_rescuer_device_uuid';

const tabs: { key: TabKey; label: string; icon: keyof typeof Ionicons.glyphMap; special?: boolean }[] = [
  { key: 'dashboard', label: 'Home', icon: 'home-outline' },
  { key: 'map', label: 'Map', icon: 'map-outline' },
  { key: 'report', label: 'Report', icon: 'add', special: true },
  { key: 'resource', label: 'Resources', icon: 'cube-outline' },
  { key: 'profile', label: 'Profile', icon: 'person-outline' },
];

export default function RescuerHomeScreen() {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<TabKey>('dashboard');
  const [overview, setOverview] = useState<RescuerOverview | null>(null);
  const [selectedAssignment, setSelectedAssignment] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [overviewError, setOverviewError] = useState('');
  const overviewRequest = useRef(false);
  useEffect(() => Platform.OS === 'web' ? undefined : mountNavigationSync(), []);

  const loadOverview = useCallback(async (isRefresh = false, silent = false) => {
    if (overviewRequest.current) return;
    overviewRequest.current = true;

    if (!silent) {
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
    }

    try {
      const data = await getRescuerOverview(isRefresh);
      setOverview(data);
      setOverviewError('');
    } catch (error: any) {
      setOverviewError(errorMessage(error));
    } finally {
      overviewRequest.current = false;
      if (!silent) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, []);

  useEffect(() => {
    const removeBroadcast = onDisasterBroadcast(() => { void loadOverview(true); });
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void loadOverview(true, true);
    });
    return () => { removeBroadcast(); subscription.remove(); };
  }, [loadOverview]);

  useEffect(() => {
    loadOverview();
  }, [loadOverview]);

  useEffect(() => {
    async function registerNotifications() {
      let savedDeviceUuid = await getStoredItem(deviceUuidKey);

      if (!savedDeviceUuid) {
        savedDeviceUuid = `rescuer-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        await setStoredItem(deviceUuidKey, savedDeviceUuid);
      }

      const registration = await getPushRegistration(savedDeviceUuid);
      const batteryLevel = await currentBatteryLevel();

      try {
        await savePushRegistration({
          device_uuid: savedDeviceUuid,
          device_name: 'Rescuer mobile',
          platform: Platform.OS as 'android' | 'ios',
          player_id: registration.playerId,
          push_token: registration.pushToken,
          push_provider: registration.pushProvider,
          one_signal_user_id: registration.oneSignalUserId,
          battery_level: batteryLevel,
          notification_permission_status: registration.permissionStatus,
        });
      } catch {
        // Registration retries the next time the authenticated mobile screen opens.
      }
    }

    registerNotifications();
  }, []);

  const assignments = useMemo(() => overview?.assignments || [], [overview?.assignments]);
  const activeAssignment = useMemo(
    () =>
      assignments.find((assignment) =>
        ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key) && (assignment.household_id || !assignment.route_notes?.assigned_household_count)
      ),
    [assignments]
  );

  const [gpsStatus, setGpsStatus] = useState('');
  const trackingAssignmentId = activeAssignment?.assignment_id || null;
  useEffect(() => {
    if (Platform.OS === 'web') return;
    setGpsStatus(trackingAssignmentId ? 'GPS · Acquiring location' : '');
    void configureRescuerTracking(trackingAssignmentId).catch(() => setGpsStatus('GPS · Enable location access'));
    return () => { void stopRescuerTracking(); };
  }, [trackingAssignmentId]);
  useEffect(() => subscribeRescuerPosition(fix => {
    setGpsStatus(`GPS · ${new Date(fix.timestamp).toLocaleTimeString()} · ±${Math.round(fix.coords.accuracy || 0)} m`);
  }, problem => { if (problem) setGpsStatus(problem); }), []);
  useEffect(() => {
    if (!trackingAssignmentId) return;
    const timer = setInterval(() => {
      if (AppState.currentState === 'active') {
        void loadOverview(true, true);
      }
    }, 15000);
    return () => clearInterval(timer);
  }, [trackingAssignmentId, loadOverview]);

  async function handleLogout() {
    try {
      await logoutMobile();
      router.replace('/' as Href);
    } catch (error: any) { Alert.alert('Unable to log out', errorMessage(error)); }
  }

  async function handleStatusChange(assignmentId: number, status: string) {
    let completionStatus: 'safe' | 'evacuated' | null = null;
    if (status === 'completed') {
      const current = assignments.find((item) => Number(item.assignment_id) === Number(assignmentId));
      if (current?.status_key !== 'on_scene') {
        Alert.alert('Arrival required', 'Accept this household, start En route, and mark Arrived before confirming its final status.');
        return;
      }
      completionStatus = await new Promise<'safe' | 'evacuated' | null>((resolve) => {
        Alert.alert('Complete current household', 'Confirm the household’s official status. This updates HQ records and advances your queue. A detailed field report is optional.', [
          { text: 'Cancel', style: 'cancel', onPress: () => resolve(null) },
          { text: 'Safe', onPress: () => resolve('safe') },
          { text: 'Evacuated', onPress: () => resolve('evacuated') },
        ], { cancelable: true, onDismiss: () => resolve(null) });
      });
      if (!completionStatus) return;
    }

    try {
      const payload = ['accepted', 'en_route'].includes(status)
        ? await currentLocationPayload()
        : status === 'completed' ? { household_id: assignments.find((item) => Number(item.assignment_id) === Number(assignmentId))?.household_id, household_status_key: completionStatus } : {};

      if (payload === null) {
        return;
      }

      await updateAssignmentStatus(assignmentId, status, payload);
      if (status === 'completed' && Platform.OS !== 'web') {
        await endNavigationTrip().catch(() => Alert.alert('Household saved', 'The household was completed, but trip recording could not be stopped. Open navigation to stop the trip.'));
      }

      await loadOverview(true);
      if (status === 'completed') { setSelectedAssignment(null); setActiveTab('dashboard'); }

      if (['accepted', 'en_route'].includes(status)) {
        setSelectedAssignment(null);
        setActiveTab('map');
        if (Platform.OS !== 'web') {
          router.push({ pathname: '/rescuer/navigation' as any, params: { dispatchId: String(assignmentId) } });
        }
      }
    } catch (error: any) {
      Alert.alert('Unable to update assignment', errorMessage(error));
    }
  }

  async function handleSendLocation(assignmentId: number, payload: any) {
    const result = await sendAssignmentLocation(assignmentId, payload);
    if (payload.refresh_route && result.data) setOverview(current => current ? { ...current, assignments: current.assignments.map(item => Number(item.assignment_id) === Number(assignmentId) ? { ...item, route: result.data.route } : item) } : current);
    if (payload.refresh_route && result.data && !result.data.route) throw new Error('GPS saved. Current directions unavailable.');
  }

  async function currentLocationPayload() {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      Alert.alert('Location required', 'Enable location access so the app can create your road route and HQ can track your rescue movement.');
      return null;
    }

    const location = await Location.getCurrentPositionAsync({
      accuracy: Location.Accuracy.High,
    });

    return {
      latitude: location.coords.latitude,
      longitude: location.coords.longitude,
      accuracy_m: location.coords.accuracy,
    };
  }

  async function handleSubmitFieldReport(payload: any) {
    await createFieldReport(payload);
    await loadOverview(true);
  }

  function openMap(assignment?: any) {
    setSelectedAssignment(assignment || activeAssignment || null);
    setActiveTab('map');
  }

  function openReport(assignment?: any) {
    setSelectedAssignment(assignment || activeAssignment || null);
    setActiveTab('report');
  }

  async function handleSubmitResourceRequest(payload: any) {
    if (!payload.location || !payload.resource_type || !payload.quantity) {
      Alert.alert('Missing request details', 'Enter location, need type, and quantity.');
      return;
    }

    try {
      await createResourceRequest(payload);
      Alert.alert('Request submitted', 'Your request is waiting for HQ validation.');
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to submit request', errorMessage(error));
    }
  }

  async function handleCancelResourceRequest(requestId: string) {
    try {
      await cancelResourceRequest(requestId);
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to cancel request', errorMessage(error));
    }
  }

  async function handleUpdateProfile(payload: any) {
    try {
      await updateRescuerProfile(payload);
      Alert.alert('Profile updated', 'Your profile changes were saved.');
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to update profile', errorMessage(error));
      throw error;
    }
  }

  function renderContent() {
    if (loading && !overview) {
      return <LoadingState label="Loading rescuer console..." />;
    }

    if (!overview) {
      return (
        <View style={styles.loadFailure}>
          <EmptyState icon="cloud-offline-outline" title="Unable to load rescuer data" body={overviewError || 'Check your connection, then try again.'} />
          <ActionButton label="Try again" icon="refresh-outline" onPress={() => { void loadOverview(); }} />
        </View>
      );
    }

    if (activeTab === 'dashboard') {
      return (
        <RescuerDashboardScreen
          overview={overview}
          onOpenMap={openMap}
          onOpenReport={openReport}
          onStatusChange={handleStatusChange}
        />
      );
    }

    if (activeTab === 'map') {
      return (
        <>
          {Platform.OS !== 'web' ? (
            <Pressable
              style={({ pressed }) => [styles.navigationButton, pressed && styles.navigationButtonPressed]}
              onPress={() => router.push({
                pathname: '/rescuer/navigation' as any,
                params: { dispatchId: String(selectedAssignment?.assignment_id || activeAssignment?.assignment_id || '') },
              })}
            >
              <Ionicons name="navigate-outline" size={20} color="#fff" />
              <Text style={styles.navigationButtonText}>View directions / Offline navigation</Text>
            </Pressable>
          ) : null}
          <RescueMapScreen
            assignments={assignments}
            evacuationCenters={overview.evacuation_centers || []}
            activeAssignment={activeAssignment}
            selectedAssignment={selectedAssignment}
            onSendLocation={handleSendLocation}
          />
        </>
      );
    }

    if (activeTab === 'report') {
      return (
        <FieldReportScreen
          reports={overview.field_reports || []}
          assignmentId={Number(selectedAssignment?.assignment_id || activeAssignment?.assignment_id || 0)}
          householdId={String(selectedAssignment?.household_id || activeAssignment?.household_id || '')}
          onSubmitReport={handleSubmitFieldReport}
          onCompleteHousehold={() => handleStatusChange(Number(selectedAssignment?.assignment_id || activeAssignment?.assignment_id || 0), 'completed')}
        />
      );
    }

    if (activeTab === 'resource') {
      return (
        <ResourceRequestScreen
          requests={overview.resource_requests || []}
          categoryOptions={overview.category_options || []}
          onSubmitRequest={handleSubmitResourceRequest}
          onCancelRequest={handleCancelResourceRequest}
        />
      );
    }

    if (activeTab === 'radio') {
      return <RadioCommunicationScreen overview={overview} />;
    }

    return <ResponderProfileScreen profile={overview.profile} onSaveProfile={handleUpdateProfile} onLogout={handleLogout} />;
  }

  return (
    <SafeAreaView edges={['left', 'right', 'bottom']} style={styles.safe}>
      <RescuerHeader
        onOpenRadio={() => setActiveTab('radio')}
      />

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
      >
        {!!overviewError && overview ? (
          <View style={styles.refreshError}>
            <Text style={styles.refreshErrorText}>Unable to refresh data: {overviewError}</Text>
            <Pressable onPress={() => { void loadOverview(true); }} accessibilityRole="button">
              <Text style={styles.refreshErrorAction}>Retry</Text>
            </Pressable>
          </View>
        ) : null}
        {!!trackingAssignmentId && !!gpsStatus && <Text accessibilityRole="text" style={{ color: palette.textSoft, fontSize: 12 }}>{gpsStatus}</Text>}
        {renderContent()}
      </ScrollView>

      <View style={styles.tabBar}>
        {tabs.map((tab) => {
          const isActive = activeTab === tab.key;

          return (
            <Pressable
              key={tab.key}
              style={[styles.tabButton, tab.special && styles.specialTab, isActive && styles.activeTab]}
              onPress={() => setActiveTab(tab.key)}
            >
              <Ionicons
                name={tab.icon}
                size={tab.special ? 24 : 20}
                color={isActive || tab.special ? '#fff' : palette.navMuted}
              />
              {!tab.special ? (
                <Text style={[styles.tabLabel, isActive && styles.activeTabLabel]}>{tab.label}</Text>
              ) : null}
            </Pressable>
          );
        })}
      </View>
    </SafeAreaView>
  );
}

async function currentBatteryLevel() {
  try {
    const level = await Battery.getBatteryLevelAsync();

    return level >= 0 ? Math.round(level * 100) : null;
  } catch {
    return null;
  }
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
    paddingBottom: 100,
  },
  loadFailure: {
    gap: spacing.md,
    alignItems: 'stretch',
    paddingTop: spacing.xl,
  },
  refreshError: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    marginBottom: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: '#96202012',
  },
  refreshErrorText: {
    flex: 1,
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '700',
  },
  refreshErrorAction: {
    color: palette.navActive,
    fontWeight: '900',
  },
  navigationButton: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    marginBottom: spacing.md,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.nav,
  },
  navigationButtonPressed: {
    opacity: 0.85,
  },
  navigationButtonText: {
    color: palette.navText,
    fontWeight: '800',
  },
  tabBar: {
    position: 'absolute',
    right: spacing.md,
    bottom: spacing.md,
    left: spacing.md,
    minHeight: 70,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.sm,
    backgroundColor: palette.nav,
  },
  tabButton: {
    minWidth: 54,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    borderRadius: radius.md,
  },
  activeTab: {
    backgroundColor: palette.navActive,
  },
  specialTab: {
    width: 56,
    height: 56,
    marginTop: -26,
    borderWidth: 4,
    borderColor: palette.page,
    borderRadius: 28,
    backgroundColor: palette.unsafe,
  },
  tabLabel: {
    color: palette.navMuted,
    fontSize: 10,
    fontWeight: '900',
  },
  activeTabLabel: {
    color: '#fff',
  },
});
