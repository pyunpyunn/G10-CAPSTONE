import { useEffect, useState } from 'react';
import { Alert } from 'react-native';

interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[];
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
  prompt(): Promise<void>;
}

export interface PwaInstallState {
  isStandalone: boolean;
  canPromptNative: boolean;
  isIos: boolean;
  promptInstall: () => Promise<void>;
}

let deferredPrompt: BeforeInstallPromptEvent | null = null;
let listenersAttached = false;
const subscribers = new Set<() => void>();

function notifySubscribers() {
  subscribers.forEach((cb) => cb());
}

function checkStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const mediaStandalone = window.matchMedia?.('(display-mode: standalone)')?.matches ?? false;
  const navigatorStandalone = Boolean((window.navigator as unknown as { standalone?: boolean }).standalone);
  return mediaStandalone || navigatorStandalone;
}

function checkIsIos(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent || '';
  return /iPad|iPhone|iPod/i.test(ua) || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
}

function ensureHeadPwaTags() {
  if (typeof document === 'undefined') return;
  if (!document.querySelector('link[rel="manifest"]')) {
    const manifest = document.createElement('link');
    manifest.rel = 'manifest';
    manifest.href = '/manifest.webmanifest';
    document.head.appendChild(manifest);
  }
  if (!document.querySelector('meta[name="theme-color"]')) {
    const theme = document.createElement('meta');
    theme.name = 'theme-color';
    theme.content = '#0f1d2d';
    document.head.appendChild(theme);
  }
  if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
    const capable = document.createElement('meta');
    capable.name = 'apple-mobile-web-app-capable';
    capable.content = 'yes';
    document.head.appendChild(capable);
  }
  if (!document.querySelector('link[rel="apple-touch-icon"]')) {
    const icon = document.createElement('link');
    icon.rel = 'apple-touch-icon';
    icon.href = '/pwa-192.png';
    document.head.appendChild(icon);
  }
}

function attachInstallListeners() {
  if (typeof window === 'undefined' || listenersAttached) return;
  listenersAttached = true;

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event as BeforeInstallPromptEvent;
    notifySubscribers();
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    notifySubscribers();
  });

  const mediaQuery = window.matchMedia?.('(display-mode: standalone)');
  mediaQuery?.addEventListener?.('change', () => {
    notifySubscribers();
  });
}

export function registerPwa() {
  if (typeof window === 'undefined') return;
  ensureHeadPwaTags();
  attachInstallListeners();

  if (process.env.NODE_ENV !== 'production' || !('serviceWorker' in navigator) || !window.isSecureContext) return;
  let refreshing = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (refreshing) return;
    refreshing = true;
    window.location.reload();
  });
  void navigator.serviceWorker
    .register('/sw.js', { scope: '/' })
    .then((registration) => {
      void registration.update();
    })
    .catch((error) => {
      console.error('Offline app installation failed.', error);
    });
}

export function usePwaInstall(): PwaInstallState {
  const [, setTick] = useState(0);

  useEffect(() => {
    attachInstallListeners();
    const update = () => setTick((n) => n + 1);
    subscribers.add(update);
    return () => {
      subscribers.delete(update);
    };
  }, []);

  const isStandalone = checkStandalone();
  const isIos = checkIsIos();
  const canPromptNative = Boolean(deferredPrompt);

  async function promptInstall() {
    if (deferredPrompt) {
      await deferredPrompt.prompt();
      try {
        await deferredPrompt.userChoice;
      } finally {
        deferredPrompt = null;
        notifySubscribers();
      }
      return;
    }

    if (isIos) {
      Alert.alert(
        'Install RESQPERATION on iOS',
        '1. Tap the Share button at the bottom of Safari.\n2. Scroll down and tap "Add to Home Screen".\n3. Tap "Add" to launch RESQPERATION as a standalone mobile app.'
      );
      return;
    }

    Alert.alert(
      'Install Mobile App (PWA)',
      'To install RESQPERATION as a standalone app:\n\n• Chrome / Edge (Desktop): Click the Install icon in the right side of the address bar.\n• Android Chrome: Tap the ⋮ browser menu and select "Install app" or "Add to Home screen".\n• For full offline Service Worker support, run the production PWA build on port 8099 (npm run preview:web).'
    );
  }

  return {
    isStandalone,
    canPromptNative,
    isIos,
    promptInstall,
  };
}
