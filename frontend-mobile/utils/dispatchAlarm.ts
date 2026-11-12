import { NativeModules, Platform } from 'react-native';

export async function stopAcceptedDispatchAlarm(assignmentId: number, status: string): Promise<void> {
  if (Platform.OS !== 'android' || !['accepted', 'en_route', 'on_scene', 'completed', 'cancelled'].includes(status)) return;
  // Expo Go does not include the custom Android service.
  if (!NativeModules.DispatchAlarm) return;
  try {
    await NativeModules.DispatchAlarm.acknowledge(String(assignmentId));
  } catch (error) {
    console.warn('Could not stop the dispatch alarm.', error);
  }
}
