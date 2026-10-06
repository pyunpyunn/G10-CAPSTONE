import { StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { mobileLeafletHtml, type LeafletPoint, type LeafletRoute } from '@/utils/mobileLeafletHtml';

export function MobileLeafletMap({
  markers,
  routes,
  center,
  height = 340,
}: {
  markers: LeafletPoint[];
  routes: LeafletRoute[];
  center?: { latitude: number; longitude: number };
  height?: number;
}) {
  return (
    <View style={[styles.frame, { height }]}>
      <WebView
        originWhitelist={['*']}
        source={{ html: mobileLeafletHtml({ markers, routes, center }) }}
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