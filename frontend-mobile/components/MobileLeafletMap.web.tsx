import { createElement, useCallback, useEffect, useRef } from 'react';
import { mobileLeafletHtml, type LeafletPoint, type LeafletRoute } from '@/utils/mobileLeafletHtml';

export function MobileLeafletMap({ markers, routes, center, height = 340, geotagPicker = false, selectedPoint, onMapPress, onPinMoving }: {
  markers: LeafletPoint[]; routes: LeafletRoute[];
  center?: { latitude: number; longitude: number }; height?: number;
  geotagPicker?: boolean; selectedPoint?: { latitude: number; longitude: number } | null;
  onMapPress?: (point: { latitude: number; longitude: number }) => void;
  onPinMoving?: () => void;
}) {
  const frame = useRef<HTMLIFrameElement>(null);
  const html = geotagPicker ? mobileLeafletHtml({ markers: [], routes: [], geotagPicker: true }) : mobileLeafletHtml({ markers, routes, center });
  const latitude = selectedPoint?.latitude, longitude = selectedPoint?.longitude;
  const updatePin = useCallback(() => {
    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
      frame.current?.contentWindow?.postMessage({ type: 'setHouseholdPin', latitude, longitude }, '*');
    }
  }, [latitude, longitude]);
  useEffect(updatePin, [updatePin]);
  useEffect(() => {
    const receive = (event: MessageEvent) => {
      if (event.source !== frame.current?.contentWindow) return;
      const point = event.data;
      if (point?.type === 'pinMoving') onPinMoving?.();
      if (point?.type === 'mapPress' && Number.isFinite(point.latitude) && Number.isFinite(point.longitude)) {
        onMapPress?.({ latitude: point.latitude, longitude: point.longitude });
      }
    };
    window.addEventListener('message', receive);
    return () => window.removeEventListener('message', receive);
  }, [onMapPress, onPinMoving]);
  return createElement('iframe', {
    ref: frame, title: 'Household location map', srcDoc: html, onLoad: updatePin,
    style: { width: '100%', height, border: 0, borderRadius: 8, backgroundColor: '#e8eef0' },
  });
}
