import * as Battery from 'expo-battery';
import { Platform } from 'react-native';
import { savePushRegistration } from '@/api/device';
import { getPushRegistration } from '@/utils/pushNotifications';
import { getStoredItem, setStoredItem } from '@/utils/secureStorage';

type MobileRole = 'household_resident' | 'rescuer';

const deviceUuidKeys: Record<MobileRole, string> = {
  household_resident: 'resq_household_device_uuid',
  rescuer: 'resq_rescuer_device_uuid',
};

const devicePrefixes: Record<MobileRole, string> = {
  household_resident: 'hh',
  rescuer: 'rescuer',
};

export async function getMobileDeviceUuid(role: MobileRole) {
  const storageKey = deviceUuidKeys[role];
  const existingDeviceUuid = await getStoredItem(storageKey);

  if (existingDeviceUuid) {
    return existingDeviceUuid;
  }

  const nextDeviceUuid = `${devicePrefixes[role]}-${Date.now()}-${Math.random()
    .toString(16)
    .slice(2)}`;

  await setStoredItem(storageKey, nextDeviceUuid);

  return nextDeviceUuid;
}

export async function registerOneSignalDevice(role: MobileRole, deviceName: string) {
  const deviceUuid = await getMobileDeviceUuid(role);
  const registration = await getPushRegistration(deviceUuid);
  const batteryLevel = await currentBatteryLevel();

  await savePushRegistration({
    device_uuid: deviceUuid,
    device_name: deviceName,
    platform: Platform.OS === 'ios' ? 'ios' : 'android',
    player_id: registration.playerId,
    push_token: registration.pushToken,
    push_provider: registration.pushProvider,
    one_signal_user_id: registration.oneSignalUserId,
    battery_level: batteryLevel,
    notification_permission_status: registration.permissionStatus,
  });

  return {
    deviceUuid,
    registration,
  };
}

async function currentBatteryLevel() {
  try {
    const battery = await Battery.getBatteryLevelAsync();

    if (battery >= 0) {
      return Math.round(battery * 100);
    }
  } catch {
    return null;
  }

  return null;
}
