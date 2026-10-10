import { Alert, Platform } from 'react-native';

export function confirmHouseholdGps(): Promise<boolean> {
  if (Platform.OS === 'web') {
    return Promise.resolve(window.confirm('Make sure you are in your house to pin your location'));
  }
  return new Promise((resolve) => {
    Alert.alert('Pin household using GPS', 'Make sure you are in your house to pin your location', [
      { text: 'Cancel', style: 'cancel', onPress: () => resolve(false) },
      { text: 'Confirm', onPress: () => resolve(true) },
    ], { cancelable: true, onDismiss: () => resolve(false) });
  });
}
