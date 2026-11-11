import { lazy, Suspense } from 'react';
import Constants from 'expo-constants';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

const isExpoGo = Constants.executionEnvironment === 'storeClient' || Constants.appOwnership === 'expo';
const NativeNavigationScreen = isExpoGo
  ? null
  : lazy(() => import('./OfflineNavigationScreen.maplibre'));

export default function OfflineNavigationScreen() {
  if (isExpoGo || !NativeNavigationScreen) {
    return (
      <View style={styles.message}>
        <Text style={styles.title}>Offline navigation needs the RESQPERATION app build</Text>
        <Text style={styles.body}>You can still view available evacuation centers and dispatch routes from the Map tab in Expo Go.</Text>
      </View>
    );
  }

  const NavigationScreen = NativeNavigationScreen;
  return (
    <Suspense fallback={<View style={styles.message}><ActivityIndicator /><Text>Loading navigation...</Text></View>}>
      <NavigationScreen />
    </Suspense>
  );
}

const styles = StyleSheet.create({
  message: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, gap: 12 },
  title: { fontSize: 18, fontWeight: '700', textAlign: 'center' },
  body: { fontSize: 15, textAlign: 'center' },
});
