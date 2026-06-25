import Constants from 'expo-constants';
import { Alert, Platform } from 'react-native';

export type PushRegistration = {
  token: string | null;
  permissionStatus: 'granted' | 'denied' | 'undetermined' | 'unavailable' | 'error';
};

export async function configureNotificationHandler() {
  if (!supportsRemotePushNotifications()) {
    return;
  }

  const Notifications = await import('expo-notifications');

  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldPlaySound: true,
      shouldSetBadge: true,
      shouldShowBanner: true,
      shouldShowList: true,
    }),
  });
}

export async function getPushRegistration(): Promise<PushRegistration> {
  if (!supportsRemotePushNotifications()) {
    return { token: null, permissionStatus: 'unavailable' };
  }

  try {
    const Notifications = await import('expo-notifications');

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('disaster-alerts', {
        name: 'Disaster alerts',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 250, 250, 250],
        sound: 'default',
      });
    }

    let permission = await Notifications.getPermissionsAsync();

    if (permission.status !== 'granted') {
      const accepted = await confirmNotificationConsent();

      if (!accepted) {
        return {
          token: null,
          permissionStatus: permission.status === 'denied' ? 'denied' : 'undetermined',
        };
      }

      permission = await Notifications.requestPermissionsAsync();
    }

    if (permission.status !== 'granted') {
      return {
        token: null,
        permissionStatus: permission.status === 'denied' ? 'denied' : 'undetermined',
      };
    }

    const projectId =
      process.env.EXPO_PUBLIC_EAS_PROJECT_ID ||
      Constants.easConfig?.projectId ||
      (Constants.expoConfig?.extra?.eas?.projectId as string | undefined);

    if (!projectId) {
      return {
        token: null,
        permissionStatus: 'error',
      };
    }

    const response = await Notifications.getExpoPushTokenAsync(
      { projectId }
    );

    return {
      token: response.data,
      permissionStatus: 'granted',
    };
  } catch {
    return {
      token: null,
      permissionStatus: 'error',
    };
  }
}

function supportsRemotePushNotifications() {
  const isMobile = Platform.OS === 'android' || Platform.OS === 'ios';
  const isExpoGo =
    Constants.executionEnvironment === 'storeClient' ||
    Constants.appOwnership === 'expo';

  return isMobile && !isExpoGo;
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
