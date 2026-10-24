import { useCallback, useEffect, useMemo, useRef } from 'react';
import { Linking, Pressable, StyleSheet, Text, TurboModuleRegistry, View } from 'react-native';
import type { TurboModule } from 'react-native';
import { mobileLeafletHtml, type LeafletPoint, type LeafletRoute } from '@/utils/mobileLeafletHtml';

// Importing WebView before this check throws on development APKs built before
// WebView was added. Metro cannot add native modules to an installed APK.
const WebView = TurboModuleRegistry.get<TurboModule>('RNCWebViewModule')
  // eslint-disable-next-line @typescript-eslint/no-require-imports -- Must not import a missing native module.
  ? (require('react-native-webview') as typeof import('react-native-webview')).WebView
  : null;

export function MobileLeafletMap({
  markers,
  routes,
  center,
  height = 340,
  onMapPress,
  geotagPicker = false,
  geotagMode = 'drag',
  selectedPoint,
  onPinMoving,
  followPosition,
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
  height?: number;
  onMapPress?: (point: { latitude: number; longitude: number }) => void;
  geotagPicker?: boolean;
  geotagMode?: 'drag' | 'center';
  selectedPoint?: { latitude: number; longitude: number } | null;
  onPinMoving?: () => void;
  followPosition?: { latitude: number; longitude: number } | null;
}) {
  const webView = useRef<import('react-native-webview').WebView>(null);
  const initialMap = useRef<string | null>(null);
  if (initialMap.current === null) initialMap.current = mobileLeafletHtml({ markers, routes, center, interactive: !!onMapPress });
  const html = geotagPicker ? mobileLeafletHtml({ markers: [], routes: [], geotagPicker: true, geotagMode }) : initialMap.current;
  const source = useMemo(() => ({ html }), [html]);
  const layerPayload = JSON.stringify({ markers, routes }).replace(/</g, '\\u003c');
  const updateLayers = useCallback(() => {
    if (!geotagPicker) webView.current?.injectJavaScript(`window.updateRescueData?.(${layerPayload}); true;`);
  }, [geotagPicker, layerPayload]);
  useEffect(updateLayers, [updateLayers]);
  const latitude = selectedPoint?.latitude;
  const longitude = selectedPoint?.longitude;
  const updatePin = useCallback(() => {
    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
      webView.current?.injectJavaScript(`window.setHouseholdPin?.(${latitude}, ${longitude}); true;`);
    }
  }, [latitude, longitude]);
  useEffect(updatePin, [updatePin]);
  const gpsLatitude = followPosition?.latitude;
  const gpsLongitude = followPosition?.longitude;
  const updateGps = useCallback(() => {
    const point = Number.isFinite(gpsLatitude) && Number.isFinite(gpsLongitude)
      ? `[${gpsLatitude},${gpsLongitude}]` : 'null';
    webView.current?.injectJavaScript(`window.setGpsPosition?.(${point}); true;`);
  }, [gpsLatitude, gpsLongitude]);
  useEffect(updateGps, [updateGps]);
  if (!WebView) {
    return (
      <View style={[styles.frame, styles.updatePrompt, { height }]}>
        <Text style={styles.updateTitle}>Update RESQPERATION to view the map</Text>
        <Text style={styles.updateMessage}>Install the latest RESQPERATION app to see routes and locations directly here.</Text>
        <Pressable
          style={styles.updateButton}
          onPress={() => {
            void Linking.openURL('https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds/6cd63da7-75ca-492e-bde7-3ba897be902b')
              .catch(() => {});
          }}
        >
          <Text style={styles.updateButtonText}>Download app update</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={[styles.frame, { height }]}>
      <WebView
        ref={webView}
        originWhitelist={['*']}
        source={source}
        onLoadEnd={() => { updateLayers(); updatePin(); updateGps(); }}
        onMessage={(event) => {
          try {
            const point = JSON.parse(event.nativeEvent.data);
            if (point.type === 'pinMoving') onPinMoving?.();
            if (point.type === 'mapPress' && Number.isFinite(point.latitude) && Number.isFinite(point.longitude)) {
              onMapPress?.({ latitude: point.latitude, longitude: point.longitude });
            }
          } catch {
            // Ignore messages that are not map coordinates.
          }
        }}
        javaScriptEnabled
        domStorageEnabled
        nestedScrollEnabled
        style={styles.webView}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  frame: { overflow: 'hidden', borderRadius: 8, backgroundColor: '#e8eef0' },
  webView: { flex: 1, backgroundColor: 'transparent' },
  updatePrompt: { alignItems: 'center', justifyContent: 'center', padding: 24, gap: 12 },
  updateTitle: { color: '#102638', fontSize: 16, fontWeight: '700', textAlign: 'center' },
  updateMessage: { color: '#496171', textAlign: 'center', lineHeight: 20 },
  updateButton: { backgroundColor: '#102638', paddingHorizontal: 18, paddingVertical: 12, borderRadius: 8 },
  updateButtonText: { color: '#fff', fontWeight: '700' },
});
