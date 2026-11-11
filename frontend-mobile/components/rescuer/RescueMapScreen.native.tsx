import { lazy, Suspense } from 'react';
import Constants from 'expo-constants';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { RescueMapScreen as ExpoGoMap } from './RescueMapScreen.expoGo';

type RescueMapProps = {
  active?: boolean;
  assignments: any[];
  evacuationCenters: any[];
  activeAssignment: any;
  selectedAssignment?: any;
  showNavigationButton?: boolean;
  onOpenNavigation?: () => void;
};

const isExpoGo = Constants.executionEnvironment === 'storeClient' || Constants.appOwnership === 'expo';
const NativeMap = isExpoGo
  ? null
  : lazy(() => import('./RescueMapScreen.maplibre').then(({ RescueMapScreen }) => ({ default: RescueMapScreen })));

export function RescueMapScreen(props: RescueMapProps) {
  if (isExpoGo || !NativeMap) return <ExpoGoMap {...props} />;

  const MapScreen = NativeMap;
  return (
    <Suspense fallback={<View style={styles.loading}><ActivityIndicator /><Text>Loading rescue map...</Text></View>}>
      <MapScreen {...props} />
    </Suspense>
  );
}

const styles = StyleSheet.create({
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 8 },
});
