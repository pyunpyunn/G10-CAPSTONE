import { useEffect, useState } from 'react';
import { Platform, useWindowDimensions } from 'react-native';

export type WebViewMode = 'mobile' | 'desktop';

function isInsideIframe(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return window.self !== window.top || new URLSearchParams(window.location.search).get('insidePhone') === '1';
  } catch {
    return true;
  }
}

// Inside the phone simulator (or on a real mobile screen < 768px), default automatically ("matik") to 'mobile'.
// On the outer desktop screen (>= 768px), default to 'desktop' so the Desktop Web View fills the background
// while the iPhone Mobile Web View floats on top of it (matching the reference image).
let currentMode: WebViewMode = isInsideIframe() ? 'mobile' : 'desktop';
let manualOverride = false;
const listeners = new Set<() => void>();

function notify() {
  listeners.forEach((listener) => listener());
}

if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
  window.addEventListener('message', (event) => {
    if (event.data?.type === 'RESQ_SET_VIEW_MODE') {
      const nextMode = event.data.mode as WebViewMode;
      if (nextMode === 'mobile' || nextMode === 'desktop') {
        manualOverride = true;
        currentMode = nextMode;
        notify();
      }
    }
  });
}

export function setWebViewMode(mode: WebViewMode) {
  manualOverride = true;
  if (currentMode === mode) return;
  currentMode = mode;
  notify();
}

export function toggleWebViewMode() {
  setWebViewMode(currentMode === 'mobile' ? 'desktop' : 'mobile');
}

export function useWebViewMode() {
  const { width } = useWindowDimensions();
  const [, setTick] = useState(0);
  const inIframe = isInsideIframe();

  useEffect(() => {
    const onUpdate = () => setTick((n) => n + 1);
    listeners.add(onUpdate);
    return () => {
      listeners.delete(onUpdate);
    };
  }, []);

  const isDesktopSite =
    Platform.OS === 'web' &&
    (inIframe
      ? currentMode === 'desktop'
      : manualOverride
        ? currentMode === 'desktop'
        : width >= 768);

  const isWideDesktop = isDesktopSite && (width >= 1120 || inIframe);

  return {
    viewMode: isDesktopSite ? 'desktop' : 'mobile',
    isDesktopSite,
    isWideDesktop,
    isInsidePhoneFrame: inIframe,
    isLaptopScreen: Platform.OS === 'web' && width >= 768 && !inIframe,
    setWebViewMode,
    toggleWebViewMode,
  };
}
