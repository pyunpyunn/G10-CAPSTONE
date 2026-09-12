import * as Location from 'expo-location';
import { Alert } from 'react-native';
import { registerOneSignalDevice } from '@/utils/mobileDeviceRegistration';

type MobileRole = 'household_resident' | 'rescuer';

export async function askStandardMobilePermissions(role: MobileRole) {
  try {
    await registerOneSignalDevice(
      role,
      role === 'rescuer' ? 'Rescuer mobile' : 'Household mobile'
    );
  } catch {
    // Notification registration retries when the authenticated role screen opens.
  }

  await askLocationPermission(role);
}

async function askLocationPermission(role: MobileRole) {
  const current = await Location.getForegroundPermissionsAsync();

  if (current.status === 'granted') {
    return current.status;
  }

  const accepted = await confirmLocationConsent(role);

  if (!accepted) {
    return current.status;
  }

  const requested = await Location.requestForegroundPermissionsAsync();

  return requested.status;
}

function confirmLocationConsent(role: MobileRole): Promise<boolean> {
  const message =
    role === 'rescuer'
      ? 'RESQPERATION uses your location to show rescue routes and let HQ monitor active dispatch movement.'
      : 'RESQPERATION uses your location for household geotagging, route guidance, and emergency response mapping.';

  return new Promise((resolve) => {
    Alert.alert(
      'Allow location access?',
      `${message} You can change this permission in your device settings.`,
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
