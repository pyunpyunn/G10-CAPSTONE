import { useMobileOverview } from '@/hooks/useMobileOverview';
import { OfflineBanner } from '@/components/OfflineBanner';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { MobileLogoutOverlay } from '@/components/MobileLogoutOverlay';
import { ResourceRequestScreen } from '@/components/rescuer/ResourceRequestScreen';
import { ActionButton, EmptyState, LoadingState, StatusBadge } from '@/components/rescuer/RescuerUI';
import { palette, radius, spacing } from '@/constants/resqTheme';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';
import { getPushRegistration } from '@/utils/pushNotifications';
import { configureRescuerTracking, stopRescuerTracking, trackingAssignmentFor } from '@/utils/rescuerLiveLocation';
import { endNavigationTrip, mountNavigationSync } from '@/utils/navigationRuntime';
import { createAdaptivePoller } from '@/utils/networkScheduling';
import { rescueActionGps } from '@/utils/rescueActionGps';
import { useWebViewMode } from '@/utils/viewMode';
import { WebAppShell } from '@/components/WebAppShell';

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
  const { isDesktopSite: isDesktop, isWideDesktop } = useWebViewMode();
  const [activeTab, setActiveTab] = useState<TabKey>('dashboard');
  const { overview, loading, refreshing, error: overviewError, offline, lastSyncedAt, loadOverview }
    = useMobileOverview<RescuerOverview>('rescuer', getRescuerOverview);
  const [selectedAssignment, setSelectedAssignment] = useState<any>(null);
  const [loggingOut, setLoggingOut] = useState(false);
  const logoutPending = useRef(false);
  const statusPending = useRef(false);
  const [pendingAssignment, setPendingAssignment] = useState<number | null>(null);
  useEffect(() => Platform.OS === 'web' ? undefined : mountNavigationSync(), []);


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
          platform: Platform.OS === 'ios' ? 'ios' : 'android',
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

  const trackingAssignmentId = trackingAssignmentFor(activeAssignment);
  const queueAssignmentId = activeAssignment?.assignment_id || null;
  useEffect(() => {
    if (Platform.OS === 'web') return;
    void configureRescuerTracking(trackingAssignmentId).catch((error) => {
      console.error('Rescuer GPS tracking could not start.', error);
    });
    return () => { void stopRescuerTracking(); };
  }, [trackingAssignmentId]);
  useEffect(() => {
    return createAdaptivePoller(
      () => AppState.currentState === 'active' ? loadOverview(true, true) : null,
      { intervalMs: 30000, maxIntervalMs: 60000 },
    ).start();
  }, [queueAssignmentId, loadOverview]);

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
      Alert.alert('Unable to log out', errorMessage(error));
    }
  }

  async function handleStatusChange(assignmentId: number, status: string) {
    if (statusPending.current) return;
    statusPending.current = true;
    setPendingAssignment(assignmentId);
    try { await performStatusChange(assignmentId, status); }
    finally { statusPending.current = false; setPendingAssignment(null); }
  }

  async function performStatusChange(assignmentId: number, status: string) {
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

      void loadOverview(true, true, true);
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

  async function currentLocationPayload() {
    const permission = await Location.requestForegroundPermissionsAsync();

    if (permission.status !== 'granted') {
      Alert.alert('Location required', 'Enable location access so the app can create your road route and HQ can track your rescue movement.');
      return null;
    }

    const location = await rescueActionGps();
    return {
      latitude: location.latitude,
      longitude: location.longitude,
      accuracy_m: location.accuracy_m,
    };
  }

  async function handleSubmitFieldReport(payload: any) {
    await createFieldReport(payload);
    void loadOverview(true, true, true);
  }

  function openMap(assignment?: any) {
    setSelectedAssignment(assignment || activeAssignment || null);
    setActiveTab('map');
  }

  function openReport(assignment?: any) {
    setSelectedAssignment(assignment || activeAssignment || null);
    setActiveTab('report');
  }

  async function handleSubmitResourceRequest(payload: any): Promise<boolean> {
    if (!payload.complete_address || !payload.location || !payload.resource_type || !payload.quantity) {
      Alert.alert('Missing request details', 'Enter the location and quantity, and complete the required request fields.');
      return false;
    }

    try {
      await createResourceRequest(payload);
      Alert.alert('Request submitted', 'Your request is waiting for HQ validation.');
      void loadOverview(true, true, true);
      return true;
    } catch (error: any) {
      Alert.alert('Unable to submit request', errorMessage(error));
      return false;
    }
  }

  async function handleCancelResourceRequest(requestId: string) {
    try {
      await cancelResourceRequest(requestId);
      void loadOverview(true, true, true);
    } catch (error: any) {
      Alert.alert('Unable to cancel request', errorMessage(error));
    }
  }

  async function handleUpdateProfile(payload: any): Promise<boolean> {
    try {
      await updateRescuerProfile(payload);
      Alert.alert('Profile updated', 'Your profile changes were saved.');
      void loadOverview(true, true, true);
      return true;
    } catch (error: any) {
      Alert.alert('Unable to update profile', errorMessage(error));
      return false;
    }
  }

  function renderContent() {
    if (loading && !overview) {
      return <LoadingState label="Loading rescuer console..." />;
    }

    if (!overview) {
      return (
        <View style={styles.loadFailure}>
          <EmptyState icon="cloud-offline-outline" title="No saved page available" />
          <ActionButton label="Try again" icon="refresh-outline" onPress={() => { void loadOverview(); }} />
        </View>
      );
    }

    if (activeTab === 'dashboard') {
      return (
        <RescuerDashboardScreen
          pendingAssignmentId={pendingAssignment}
          overview={overview}
          onOpenMap={openMap}
          onOpenReport={openReport}
          onStatusChange={handleStatusChange}
        />
      );
    }

    if (activeTab === 'map') {
      return (
        <RescueMapScreen
          assignments={assignments}
          evacuationCenters={overview.evacuation_centers || []}
          activeAssignment={activeAssignment}
          selectedAssignment={selectedAssignment}
          showNavigationButton={Platform.OS !== 'web'}
          onOpenNavigation={() => router.push({
            pathname: '/rescuer/navigation' as any,
            params: { dispatchId: String(selectedAssignment?.assignment_id || activeAssignment?.assignment_id || '') },
          })}
        />
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

  const responder: any = overview?.profile?.responder || {};
  const responderName = responder.full_name || overview?.profile?.user?.full_name || 'Field Rescuer';
  const responderTeam = responder.team_name || 'Responder Unit';
  const summary: any = overview?.summary || {};

  const rescuerPageTitles: Record<TabKey, string> = {
    dashboard: 'Dispatch Dashboard',
    map: 'Mapping',
    report: 'Field Reports',
    resource: 'Request Monitoring',
    radio: 'Field Communication',
    profile: 'Profile',
  };

  if (isDesktop) {
    return (
      <SafeAreaView edges={['left', 'right', 'bottom']} style={styles.safe}>
        <WebAppShell
          activeKey={activeTab}
          pageTitle={rescuerPageTitles[activeTab] || 'Dashboard'}
          dashboardItem={{
            key: 'dashboard',
            title: 'Dashboard',
            icon: 'grid-outline',
            keywords: 'home overview assignments queue dispatch',
          }}
          groups={[
            {
              title: 'Geospatial & Analytics',
              items: [
                {
                  key: 'map',
                  title: 'Mapping',
                  icon: 'map-outline',
                  keywords: 'rescue map route evacuation centers',
                },
              ],
            },
            {
              title: 'Response Operations',
              items: [
                {
                  key: 'report',
                  title: 'Field Reports',
                  icon: 'document-text-outline',
                  keywords: 'submit field report status',
                },
                {
                  key: 'radio',
                  title: 'Field Communication',
                  icon: 'radio-outline',
                  keywords: 'radio call communication',
                },
              ],
            },
            {
              title: 'Resources & Requests',
              items: [
                {
                  key: 'resource',
                  title: 'Request Monitoring',
                  icon: 'cube-outline',
                  keywords: 'resources supplies requests',
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
                  keywords: 'account responder profile settings',
                },
              ],
            },
          ]}
          userName={responderName}
          userRole={responderTeam}
          onOpenProfile={() => setActiveTab('profile')}
          onSelectKey={(key) => setActiveTab(key as TabKey)}
          onLogout={() => void handleLogout()}
          refreshing={refreshing}
          onRefresh={() => void loadOverview(true)}
          banner={<OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} />}
          rightRail={
            isWideDesktop && overview ? (
              <>
                <View style={styles.desktopSummaryCard}>
                  <Text style={styles.desktopSummaryTitle}>Dispatch Summary</Text>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Duty Status</Text>
                    <StatusBadge label={responder.duty_status || 'Standby'} tone={responder.duty_status} />
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Total Assigned</Text>
                    <Text style={styles.desktopSummaryValue}>{summary.total_assignments || 0}</Text>
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Active Missions</Text>
                    <Text style={styles.desktopSummaryValue}>{summary.active_assignments || 0}</Text>
                  </View>
                  <View style={styles.desktopSummaryRow}>
                    <Text style={styles.desktopSummaryLabel}>Completed</Text>
                    <Text style={styles.desktopSummaryValue}>{summary.completed_assignments || 0}</Text>
                  </View>
                </View>

                <View style={styles.desktopSummaryCard}>
                  <Text style={styles.desktopSummaryTitle}>Quick Actions</Text>
                  <ActionButton label="Open Rescue Map" icon="map-outline" onPress={() => openMap()} />
                  <ActionButton label="New Field Report" icon="document-text-outline" onPress={() => openReport()} />
                </View>
              </>
            ) : undefined
          }
        >
          {!!overviewError && !offline && overview ? (
            <View style={styles.refreshError}>
              <Text style={styles.refreshErrorText}>Could not refresh. Showing previously loaded data. {overviewError}</Text>
              <Pressable onPress={() => { void loadOverview(true); }} accessibilityRole="button">
                <Text style={styles.refreshErrorAction}>Retry</Text>
              </Pressable>
            </View>
          ) : null}
          {renderContent()}
        </WebAppShell>
        <MobileLogoutOverlay visible={loggingOut} />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView edges={['left', 'right', 'bottom']} style={styles.safe}>
      <RescuerHeader
        onOpenRadio={() => setActiveTab('radio')}
      />

      <OfflineBanner offline={offline} lastSyncedAt={lastSyncedAt} loading={loading} />

      <ScrollView
        removeClippedSubviews={false}
        style={styles.scroll}
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
      >
        {!!overviewError && !offline && overview ? (
          <View style={styles.refreshError}>
            <Text style={styles.refreshErrorText}>Could not refresh. Showing previously loaded data. {overviewError}</Text>
            <Pressable onPress={() => { void loadOverview(true); }} accessibilityRole="button">
              <Text style={styles.refreshErrorAction}>Retry</Text>
            </Pressable>
          </View>
        ) : null}
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
              android_ripple={{ color: '#ffffff33' }}
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
      <MobileLogoutOverlay visible={loggingOut} />
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
  desktopResponderName: {
    color: palette.text,
    fontSize: 14,
    fontWeight: '800',
  },
  desktopResponderTeam: {
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
  desktopNavItemSpecial: {
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
