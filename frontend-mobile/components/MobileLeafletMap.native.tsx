import { StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { mobileLeafletHtml, type LeafletPoint, type LeafletRoute } from '@/utils/mobileLeafletHtml';

export function MobileLeafletMap({
  markers,
  routes,
  center,
  height = 340,
  onMapPress,
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
  height?: number;
  onMapPress?: (point: { latitude: number; longitude: number }) => void;
}) {
  return (
    <View style={[styles.frame, { height }]}>
      <WebView
        originWhitelist={['*']}
        source={{ html: mobileLeafletHtml({ markers, routes, center, interactive: !!onMapPress }) }}
        onMessage={(event) => {
          try {
            const point = JSON.parse(event.nativeEvent.data);
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
});
