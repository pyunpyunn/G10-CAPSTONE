import { createElement } from 'react';
import type { StyleProp, ViewStyle } from 'react-native';
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
  const html = mobileLeafletHtml({ markers, routes, center });
  const style: StyleProp<ViewStyle> = {
    width: '100%',
    height,
    border: 0,
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: '#e8eef0',
  } as StyleProp<ViewStyle>;

  return createElement('iframe' as any, {
    title: 'Interactive OpenStreetMap',
    src: `data:text/html;charset=utf-8,${encodeURIComponent(html)}`,
    style,
    loading: 'lazy',
  });
}