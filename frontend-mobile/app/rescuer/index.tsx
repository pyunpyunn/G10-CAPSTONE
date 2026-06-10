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
import * as Location from 'expo-location';
import { type Href, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { logoutMobile } from '@/api/auth';
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
import { RescueMapScreen } from '@/components/rescuer/RescueMapScreen';
import { ResponderProfileScreen } from '@/components/rescuer/ResponderProfileScreen';
import { ResourceRequestScreen } from '@/components/rescuer/ResourceRequestScreen';
import { LoadingState } from '@/components/rescuer/RescuerUI';
import { palette, radius, spacing } from '@/constants/resqTheme';

type TabKey = 'dashboard' | 'map' | 'report' | 'resource' | 'profile';

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
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadOverview = useCallback(async (isRefresh = false) => {
    if (isRefresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    try {
      const data = await getRescuerOverview();
      setOverview(data);
    } catch (error: any) {
      Alert.alert('Unable to load rescuer data', errorMessage(error));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadOverview();
  }, [loadOverview]);

  const assignments = useMemo(() => overview?.assignments || [], [overview?.assignments]);
  const activeAssignment = useMemo(
    () =>
      assignments.find((assignment) =>
        ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key)
      ),
    [assignments]
  );

  async function handleLogout() {
    await logoutMobile();
    router.replace('/' as Href);
  }

  async function handleStatusChange(assignmentId: number, status: string) {
    try {
      const payload = ['accepted', 'en_route'].includes(status)
        ? await currentLocationPayload()
        : {};

      if (payload === null) {
        return;
      }

      await updateAssignmentStatus(assignmentId, status, payload);
      await loadOverview(true);

      if (['accepted', 'en_route'].includes(status)) {
        setActiveTab('map');
      }
    } catch (error: any) {
      Alert.alert('Unable to update assignment', errorMessage(error));
    }
  }

  async function handleSendLocation(assignmentId: number, payload: any) {
    await sendAssignmentLocation(assignmentId, payload);
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
    if (!payload.household_id) {
      Alert.alert('Missing household ID', 'Enter the household ID before submitting.');
      return;
    }

    try {
      await createFieldReport(payload);
      Alert.alert('Report submitted', 'Your household field report was sent to HQ.');
      await loadOverview(true);
    } catch (error: any) {
      Alert.alert('Unable to submit report', errorMessage(error));
    }
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
    if (loading || !overview) {
      return <LoadingState label="Loading rescuer console..." />;
    }

    if (activeTab === 'dashboard') {
      return (
        <RescuerDashboardScreen
          overview={overview}
          onOpenMap={() => setActiveTab('map')}
          onOpenReport={() => setActiveTab('report')}
          onStatusChange={handleStatusChange}
        />
      );
    }

    if (activeTab === 'map') {
      return (
        <RescueMapScreen
          assignments={assignments}
          activeAssignment={activeAssignment}
          onSendLocation={handleSendLocation}
        />
      );
    }

    if (activeTab === 'report') {
      return (
        <FieldReportScreen
          reports={overview.field_reports || []}
          statusOptions={overview.status_options || []}
          onSubmitReport={handleSubmitFieldReport}
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

    return <ResponderProfileScreen profile={overview.profile} onSaveProfile={handleUpdateProfile} onLogout={handleLogout} />;
  }

  return (
    <SafeAreaView style={styles.safe}>
      <RescuerHeader
        onRefresh={() => loadOverview(true)}
        onLogout={handleLogout}
      />

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
      >
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
