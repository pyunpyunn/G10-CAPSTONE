import Constants from 'expo-constants';
import { Alert, AppState, Platform } from 'react-native';
import { notifyDisasterBroadcast } from '@/utils/mobileReadCache';
import { api } from '@/api/client';
import { savePushRegistration } from '@/api/device';

export type PushRegistration = {
  playerId: string | null;
  oneSignalUserId: string | null;
  pushToken: string | null;
  pushProvider: 'onesignal';
  permissionStatus: 'granted' | 'denied' | 'undetermined' | 'unavailable' | 'error';
};

let oneSignalReady = false;
let pushSyncContext: { deviceUuid: string; userId: string } | null = null;
let syncingSubscription = false;
let unsupportedNoticeShown = false;
let settingsNoticeShown = false;

export async function configureNotificationHandler() {
  await initializeOneSignal();
}

export async function getPushRegistration(_deviceUuid?: string): Promise<PushRegistration> {
  const oneSignalAppId = oneSignalAppIdFromConfig();

  if (!supportsRemotePushNotifications()) {
    await explainNotificationBuildRequirement();

    return emptyOneSignalRegistration('unavailable');
  }

  if (!oneSignalAppId) {
    return emptyOneSignalRegistration('error');
  }

  try {
    const identity = await api.get('/auth/me');
    const user = identity.data.user ?? identity.data.data;
    if (!user?.user_id) return emptyOneSignalRegistration('error');
    if (_deviceUuid) pushSyncContext = { deviceUuid: _deviceUuid, userId: String(user.user_id) };
    await initializeOneSignal();
    return getOneSignalRegistration(oneSignalAppId, String(user.user_id));
  } catch {
    return emptyOneSignalRegistration('error');
  }
}

export async function logoutPushIdentity() {
  pushSyncContext = null;
  if (!oneSignalReady || !supportsRemotePushNotifications()) return;
  const { OneSignal } = await import('react-native-onesignal');
  OneSignal.User.pushSubscription.optOut();
  OneSignal.logout();
}

async function initializeOneSignal() {
  const appId = oneSignalAppIdFromConfig();

  if (!appId || oneSignalReady || !supportsRemotePushNotifications()) {
    return;
  }

  try {
    const { OneSignal } = await import('react-native-onesignal');
    OneSignal.initialize(appId);
    OneSignal.User.pushSubscription.addEventListener('change', () => { void syncCurrentSubscription(); });
    AppState.addEventListener('change', (state) => { if (state === 'active') void syncCurrentSubscription(); });
    OneSignal.Notifications.addEventListener('foregroundWillDisplay', (event: { notification: { additionalData?: { type?: string } } }) => {
      if (event.notification.additionalData?.type === 'disaster_broadcast') notifyDisasterBroadcast();
    });
    OneSignal.Notifications.addEventListener('click', (event: { notification: { additionalData?: { type?: string } } }) => {
      if (event.notification.additionalData?.type === 'disaster_broadcast') notifyDisasterBroadcast();
    });
    oneSignalReady = true;
  } catch {
    oneSignalReady = false;
  }
}

async function getOneSignalRegistration(
  appId: string,
  externalUserId?: string
): Promise<PushRegistration> {
  try {
    const { OneSignal } = await import('react-native-onesignal');

    if (!oneSignalReady) {
      OneSignal.initialize(appId);
      oneSignalReady = true;
    }

    if (externalUserId) {
      OneSignal.login(externalUserId);
      OneSignal.User.addTags({
        app_role: 'mobile',
      });
    }

    const hasPermission = await OneSignal.Notifications.getPermissionAsync();
    let accepted = hasPermission;

    if (!hasPermission) {
      const canAsk = await OneSignal.Notifications.canRequestPermission();

      if (!canAsk) {
        await explainNotificationSettingsRequired();

        return {
          pushToken: null,
          playerId: await OneSignal.User.pushSubscription.getIdAsync(),
          oneSignalUserId: await OneSignal.User.getOnesignalId(),
          pushProvider: 'onesignal',
          permissionStatus: 'denied',
        };
      }

      const consent = await confirmNotificationConsent();

      if (!consent) {
        return {
          pushToken: null,
          playerId: await OneSignal.User.pushSubscription.getIdAsync(),
          oneSignalUserId: await OneSignal.User.getOnesignalId(),
          pushProvider: 'onesignal',
          permissionStatus: 'undetermined',
        };
      }

      accepted = await OneSignal.Notifications.requestPermission(true);
    }

    if (!accepted) {
      return {
        pushToken: null,
        playerId: await OneSignal.User.pushSubscription.getIdAsync(),
        oneSignalUserId: await OneSignal.User.getOnesignalId(),
        pushProvider: 'onesignal',
        permissionStatus: 'denied',
      };
    }

    OneSignal.User.pushSubscription.optIn();

    const subscription = await readOneSignalSubscription();

    return {
      pushToken: subscription.token,
      playerId: subscription.playerId,
      oneSignalUserId: await OneSignal.User.getOnesignalId(),
      pushProvider: 'onesignal',
      permissionStatus: subscription.playerId && subscription.token ? 'granted' : 'undetermined',
    };
  } catch {
    return emptyOneSignalRegistration('error');
  }
}

// A subscription can arrive after login, rotate, or change when Firebase is configured.
// Keep the database current without displaying another permission prompt.
async function syncCurrentSubscription() {
  const context = pushSyncContext;
  if (!context || !oneSignalReady || syncingSubscription) return;
  syncingSubscription = true;
  try {
    const { OneSignal } = await import('react-native-onesignal');
    const identity = await api.get('/auth/me');
    const user = identity.data.user ?? identity.data.data;
    if (pushSyncContext !== context || String(user?.user_id) !== context.userId ||
        await OneSignal.User.getExternalId() !== context.userId) return;
    const playerId = await OneSignal.User.pushSubscription.getIdAsync();
    const token = await OneSignal.User.pushSubscription.getTokenAsync();
    const permission = await OneSignal.Notifications.getPermissionAsync();
    const optedIn = await OneSignal.User.pushSubscription.getOptedInAsync();
    if (pushSyncContext !== context) return;
    await savePushRegistration({
      device_uuid: context.deviceUuid,
      device_name: user.role_key === 'rescuer' ? 'Rescuer mobile' : 'Household mobile',
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      player_id: playerId,
      push_token: token,
      one_signal_user_id: await OneSignal.User.getOnesignalId(),
      push_provider: 'onesignal',
      notification_permission_status: !permission ? 'denied' : playerId && token && optedIn ? 'granted' : 'undetermined',
    });
  } catch {
    // Retry while the authenticated app is active or when its subscription changes.
  } finally {
    syncingSubscription = false;
  }
}

async function readOneSignalSubscription() {
  const { OneSignal } = await import('react-native-onesignal');
  let playerId: string | null = null;
  let token: string | null = null;

  for (let attempt = 0; attempt < 6; attempt += 1) {
    playerId = await OneSignal.User.pushSubscription.getIdAsync();
    token = await OneSignal.User.pushSubscription.getTokenAsync();

    if (playerId && token) {
      break;
    }

    await wait(1000);
  }

  return { playerId, token };
}

function oneSignalAppIdFromConfig() {
  return (
    process.env.EXPO_PUBLIC_ONESIGNAL_APP_ID ||
    (Constants.expoConfig?.extra?.oneSignalAppId as string | undefined) ||
    ''
  ).trim();
}

function supportsRemotePushNotifications() {
  const isMobile = Platform.OS === 'android' || Platform.OS === 'ios';
  const isExpoGo =
    Constants.executionEnvironment === 'storeClient' ||
    Constants.appOwnership === 'expo';

  return isMobile && !isExpoGo;
}

function wait(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function emptyOneSignalRegistration(
  permissionStatus: PushRegistration['permissionStatus']
): PushRegistration {
  return {
    playerId: null,
    oneSignalUserId: null,
    pushToken: null,
    pushProvider: 'onesignal',
    permissionStatus,
  };
}

function confirmNotificationConsent(): Promise<boolean> {
  return new Promise((resolve) => {
    Alert.alert(
      'Allow disaster notifications?',
      'RESQPERATION uses notifications for disaster broadcasts, rescue assignments, and urgent safety updates. You can change this permission in your device settings.',
      [
        {
          text: 'Not now',
          style: 'cancel',
          onPress: () => resolve(false),
        },
        {
          text: 'Continue',
          onPress: () => resolve(true),
        },
      ],
      {
        cancelable: false,
      }
    );
  });
}

function explainNotificationBuildRequirement(): Promise<void> {
  if (unsupportedNoticeShown) {
    return Promise.resolve();
  }

  unsupportedNoticeShown = true;

  return new Promise((resolve) => {
    Alert.alert(
      'Notifications need the installed app',
      'Expo Go can open RESQPERATION for screen testing, but OneSignal notifications require the RESQPERATION development or production app. Install that build, then log in and allow notifications to create a OneSignal subscription record.',
      [
        {
          text: 'OK',
          onPress: () => resolve(),
        },
      ],
      {
        cancelable: false,
      }
    );
  });
}

function explainNotificationSettingsRequired(): Promise<void> {
  if (settingsNoticeShown) {
    return Promise.resolve();
  }

  settingsNoticeShown = true;

  return new Promise((resolve) => {
    Alert.alert(
      'Notifications are blocked',
      'Notification permission cannot be requested again from inside the app. Enable notifications for RESQPERATION in your device settings, then open the app again.',
      [
        {
          text: 'OK',
          onPress: () => resolve(),
        },
      ],
      {
        cancelable: false,
      }
    );
  });
}
