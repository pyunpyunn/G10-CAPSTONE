import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Slot, usePathname } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { createElement, useEffect, useRef, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';

import { useColorScheme } from '@/hooks/use-color-scheme';
import { configureNotificationHandler } from '@/utils/pushNotifications';
import { registerPwa, usePwaInstall } from '@/utils/registerPwa.web';
import { setWebViewMode, useWebViewMode } from '@/utils/viewMode';
import { Provider } from 'react-redux';
import { store } from '@/state/apiStore';
import { palette } from '@/constants/resqTheme';

export { ErrorBoundary } from 'expo-router';

export const unstable_settings = {
  initialRouteName: 'index',
};

// Polyfill Alert.alert for web browsers so prompts, errors, and confirmation dialogues work reliably
if (typeof window !== 'undefined') {
  Alert.alert = (
    title: string,
    message?: string,
    buttons?: Array<{ text?: string; onPress?: () => void; style?: string }>
  ) => {
    const text = [title, message].filter(Boolean).join('\n\n');
    if (!buttons || buttons.length <= 1) {
      window.alert(text);
      buttons?.[0]?.onPress?.();
      return;
    }
    const cancelButton = buttons.find((b) => b.style === 'cancel' || b.text?.toLowerCase() === 'cancel');
    const confirmButton = buttons.find((b) => b !== cancelButton) || buttons[buttons.length - 1];
    if (window.confirm(text)) {
      confirmButton?.onPress?.();
    } else {
      cancelButton?.onPress?.();
    }
  };
}

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const pathname = usePathname();
  const { isStandalone, promptInstall } = usePwaInstall();
  const { isDesktopSite, isLaptopScreen, isInsidePhoneFrame } = useWebViewMode();

  // On laptop screen, show the floating iPhone Mobile Web View over the Desktop Web View (matching the reference screenshot)
  const [showPhoneOverlay, setShowPhoneOverlay] = useState(true);
  // Whether "Desktop site" is checked inside the iPhone browser menu ("•••")
  const [phoneDesktopSite, setPhoneDesktopSite] = useState(false);
  // Whether the "•••" browser menu inside the iPhone is open
  const [phoneMenuOpen, setPhoneMenuOpen] = useState(false);
  // Whether the white "RESQPERATION is better on the app" bottom sheet is shown
  const [showBetterOnAppSheet, setShowBetterOnAppSheet] = useState(true);

  const iframeRef = useRef<HTMLIFrameElement | null>(null);

  useEffect(() => {
    configureNotificationHandler();
    registerPwa();

    if (typeof document !== 'undefined') {
      const styleId = 'resqperation-web-enhancements';
      if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.innerHTML = `
          html, body, #root {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #070e17;
            overscroll-behavior-y: none;
          }
          [role="button"], button, a, [data-focusable="true"] {
            cursor: pointer !important;
          }
          * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
          }
          ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
          }
          ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.04);
          }
          ::-webkit-scrollbar-thumb {
            background: rgba(31, 62, 90, 0.35);
            border-radius: 3px;
          }
        `;
        document.head.appendChild(style);
      }
    }
  }, []);

  // Sync "Desktop site" toggle inside the iPhone iframe
  function togglePhoneDesktopSite() {
    const next = !phoneDesktopSite;
    setPhoneDesktopSite(next);
    setPhoneMenuOpen(false);
    try {
      iframeRef.current?.contentWindow?.postMessage(
        { type: 'RESQ_SET_VIEW_MODE', mode: next ? 'desktop' : 'mobile' },
        '*'
      );
    } catch {
      // Ignore cross-frame timing errors
    }
  }

  const iframeSrc =
    typeof window !== 'undefined'
      ? `${window.location.origin}${pathname || '/'}?insidePhone=1`
      : '/?insidePhone=1';

  // Bottom sheet + blue browser bar used both inside the iPhone overlay AND on real mobile browsers
  function renderMobileBrowserBottomOverlay() {
    return (
      <View style={styles.phoneBottomOverlay} pointerEvents="box-none">
        {phoneMenuOpen && (
          <View style={styles.browserPopupMenu}>
            <Text style={styles.browserMenuHeader}>Chrome Mobile Menu</Text>
            <Pressable
              style={styles.browserMenuItem}
              onPress={() => {
                if (isInsidePhoneFrame || !isLaptopScreen) {
                  setWebViewMode(isDesktopSite ? 'mobile' : 'desktop');
                  setPhoneMenuOpen(false);
                } else {
                  togglePhoneDesktopSite();
                }
              }}
            >
              <Ionicons
                name="desktop-outline"
                size={17}
                color="#0f172a"
              />
              <Text style={styles.browserMenuItemText}>Desktop site</Text>
              <Ionicons
                name={
                  (isLaptopScreen ? phoneDesktopSite : isDesktopSite)
                    ? 'checkbox'
                    : 'square-outline'
                }
                size={19}
                color={
                  (isLaptopScreen ? phoneDesktopSite : isDesktopSite)
                    ? '#1358a4'
                    : '#64748b'
                }
              />
            </Pressable>

            <Pressable
              style={styles.browserMenuItem}
              onPress={() => {
                setPhoneMenuOpen(false);
                void promptInstall();
              }}
            >
              <Ionicons name="download-outline" size={17} color="#0f172a" />
              <Text style={styles.browserMenuItemText}>Install app (PWA)</Text>
              <Ionicons name="chevron-forward" size={15} color="#64748b" />
            </Pressable>

            {!showBetterOnAppSheet && (
              <Pressable
                style={styles.browserMenuItem}
                onPress={() => {
                  setShowBetterOnAppSheet(true);
                  setPhoneMenuOpen(false);
                }}
              >
                <Ionicons name="phone-portrait-outline" size={17} color="#0f172a" />
                <Text style={styles.browserMenuItemText}>Show app prompt</Text>
              </Pressable>
            )}
          </View>
        )}

        {showBetterOnAppSheet ? (
          <View style={styles.whiteAppSheet}>
            <Pressable
              style={styles.appLogoCircle}
              onPress={() => void promptInstall()}
            >
              <Ionicons name="shield-checkmark" size={26} color="#ffffff" />
            </Pressable>

            <Text style={styles.whiteSheetTitle}>
              RESQPERATION is better on the app
            </Text>
            <Text style={styles.whiteSheetSubtitle}>
              Get the latest updates, newest features and the fastest experience.
            </Text>

            <View style={styles.blueBrowserBarRow}>
              <Pressable
                style={styles.blueCircleBtn}
                onPress={() => {
                  try {
                    if (isLaptopScreen && iframeRef.current?.contentWindow) {
                      iframeRef.current.contentWindow.history.back();
                    } else if (typeof window !== 'undefined') {
                      window.history.back();
                    }
                  } catch {
                    // ignore
                  }
                }}
              >
                <Ionicons name="chevron-back" size={16} color="#ffffff" />
              </Pressable>

              <Pressable
                style={styles.blueUrlPill}
                onPress={() => void promptInstall()}
              >
                <Text style={styles.blueUrlText} numberOfLines={1}>
                  web.resqperation.com
                </Text>
              </Pressable>

              <Pressable
                style={styles.blueCircleBtn}
                onPress={() => setPhoneMenuOpen((open) => !open)}
              >
                <Ionicons name="ellipsis-horizontal" size={16} color="#ffffff" />
              </Pressable>
            </View>

            <Pressable
              style={styles.notNowBtn}
              onPress={() => {
                setShowBetterOnAppSheet(false);
                setPhoneMenuOpen(false);
              }}
            >
              <Text style={styles.notNowText}>Not now</Text>
            </Pressable>
          </View>
        ) : (
          <View style={styles.compactBrowserBarWrap}>
            <View style={styles.blueBrowserBarRow}>
              <Pressable
                style={styles.blueCircleBtn}
                onPress={() => setShowBetterOnAppSheet(true)}
              >
                <Ionicons name="chevron-up" size={15} color="#ffffff" />
              </Pressable>

              <Pressable
                style={styles.blueUrlPill}
                onPress={() => void promptInstall()}
              >
                <Text style={styles.blueUrlText} numberOfLines={1}>
                  web.resqperation.com {phoneDesktopSite ? '(Desktop site)' : ''}
                </Text>
              </Pressable>

              <Pressable
                style={styles.blueCircleBtn}
                onPress={() => setPhoneMenuOpen((open) => !open)}
              >
                <Ionicons name="ellipsis-horizontal" size={16} color="#ffffff" />
              </Pressable>
            </View>
          </View>
        )}
      </View>
    );
  }

  return (
    <Provider store={store}>
      <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
        <View style={styles.outerShell}>
          {/* Top-right switcher on laptop screens so you can show/hide the floating Mobile Web View phone or switch views */}
          {isLaptopScreen && !isStandalone && (
            <View style={styles.floatingModeSwitcher}>
              <Pressable
                style={[styles.modeSwitchBtn, showPhoneOverlay && styles.modeSwitchBtnActive]}
                onPress={() => setShowPhoneOverlay(true)}
              >
                <Ionicons
                  name="phone-portrait-outline"
                  size={15}
                  color={showPhoneOverlay ? '#ffffff' : '#94a3b8'}
                />
                <Text style={[styles.modeSwitchText, showPhoneOverlay && styles.modeSwitchTextActive]}>
                  Mobile Web View
                </Text>
              </Pressable>

              <Pressable
                style={[styles.modeSwitchBtn, !showPhoneOverlay && styles.modeSwitchBtnActive]}
                onPress={() => setShowPhoneOverlay(false)}
              >
                <Ionicons
                  name="desktop-outline"
                  size={15}
                  color={!showPhoneOverlay ? '#ffffff' : '#94a3b8'}
                />
                <Text style={[styles.modeSwitchText, !showPhoneOverlay && styles.modeSwitchTextActive]}>
                  Web Only
                </Text>
              </Pressable>
            </View>
          )}

          {/* Main App Layer (Full Desktop Web View on laptop; Mobile View inside iframe or on mobile devices) */}
          <View style={styles.appContainer}>
            <Slot />

            <StatusBar style="auto" />
          </View>

          {/* Floating iPhone 15 Pro Mobile Web View Overlay on top of the Desktop Web View (matching reference image) */}
          {isLaptopScreen && !isStandalone && showPhoneOverlay && (
            <View style={styles.floatingPhoneStage} pointerEvents="box-none">
              <View style={styles.iphoneOuterRing}>
                <View style={styles.iphoneInnerBezel}>
                  {/* iPhone Top Status Bar: 23:42 + Dynamic Island + Signal/WiFi/Battery */}
                  <View style={styles.iphoneStatusBar}>
                    <Text style={styles.iphoneClock}>23:42</Text>
                    <View style={styles.dynamicIsland} />
                    <View style={styles.iphoneStatusIcons}>
                      <Ionicons name="cellular" size={12} color="#0f172a" />
                      <Ionicons name="wifi" size={13} color="#0f172a" />
                      <Ionicons name="battery-full" size={15} color="#0f172a" />
                    </View>
                  </View>

                  {/* Carrier Data Mode Strip ("Data mode" | "Buy data") from reference screenshot */}
                  <View style={styles.dataModeStrip}>
                    <View style={styles.dataModeLeft}>
                      <Text style={styles.dataModeText}>Data mode</Text>
                    </View>
                    <View style={styles.buyDataPill}>
                      <Ionicons name="sunny-outline" size={11} color="#1e293b" />
                      <Text style={styles.buyDataText}>Buy data</Text>
                    </View>
                  </View>

                  {/* Live Mobile Web View inside the iPhone screen */}
                  <View style={styles.iphoneScreenViewport}>
                    {createElement('iframe', {
                      ref: (node: HTMLIFrameElement | null) => {
                        iframeRef.current = node;
                      },
                      src: iframeSrc,
                      title: 'RESQPERATION Mobile Web View',
                      onLoad: () => {
                        try {
                          iframeRef.current?.contentWindow?.postMessage(
                            {
                              type: 'RESQ_SET_VIEW_MODE',
                              mode: phoneDesktopSite ? 'desktop' : 'mobile',
                            },
                            '*'
                          );
                        } catch {
                          // ignore
                        }
                      },
                      style: phoneDesktopSite
                        ? {
                            width: '1120px',
                            height: '2050px',
                            border: 'none',
                            transform: 'scale(0.305)',
                            transformOrigin: 'top left',
                            backgroundColor: '#0f1d2d',
                          }
                        : {
                            width: '100%',
                            height: '100%',
                            border: 'none',
                            backgroundColor: '#0f1d2d',
                          },
                    })}

                    {/* White "RESQPERATION is better on the app" card + Blue URL pill bar */}
                    {renderMobileBrowserBottomOverlay()}
                  </View>

                  {/* iPhone Home Indicator */}
                  <View style={styles.iphoneHomeIndicatorRow}>
                    <View style={styles.iphoneHomeIndicator} />
                  </View>
                </View>
              </View>
            </View>
          )}
        </View>
      </ThemeProvider>
    </Provider>
  );
}

const styles = StyleSheet.create({
  outerShell: {
    flex: 1,
    width: '100%',
    height: '100%',
    backgroundColor: palette.page,
    position: 'relative',
  },
  appContainer: {
    flex: 1,
    width: '100%',
    height: '100%',
    backgroundColor: palette.page,
    position: 'relative',
    overflow: 'hidden',
  },
  floatingModeSwitcher: {
    position: 'absolute',
    bottom: 16,
    right: 18,
    zIndex: 3000,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#0b1624',
    padding: 4,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#294866',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.4,
    shadowRadius: 14,
    elevation: 16,
  },
  modeSwitchBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
  },
  modeSwitchBtnActive: {
    backgroundColor: '#1358a4',
  },
  modeSwitchBtnDesktopActive: {
    backgroundColor: '#0d9488',
  },
  modeSwitchText: {
    color: '#94a3b8',
    fontSize: 12,
    fontWeight: '700',
  },
  modeSwitchTextActive: {
    color: '#ffffff',
    fontWeight: '800',
  },
  floatingPhoneStage: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 2000,
  },
  iphoneOuterRing: {
    padding: 3,
    borderRadius: 52,
    backgroundColor: '#cbd5e1',
    borderWidth: 2,
    borderColor: '#64748b',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 26 },
    shadowOpacity: 0.65,
    shadowRadius: 48,
    elevation: 30,
  },
  iphoneInnerBezel: {
    width: 360,
    height: 740,
    maxHeight: '90vh' as any,
    borderRadius: 48,
    borderWidth: 10,
    borderColor: '#090d16',
    backgroundColor: '#f8fafc',
    overflow: 'hidden',
  },
  iphoneStatusBar: {
    height: 34,
    paddingHorizontal: 20,
    backgroundColor: '#f1f5f9',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  iphoneClock: {
    color: '#0f172a',
    fontSize: 12,
    fontWeight: '800',
    width: 46,
  },
  dynamicIsland: {
    width: 88,
    height: 22,
    borderRadius: 12,
    backgroundColor: '#020617',
  },
  iphoneStatusIcons: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 4,
    width: 46,
  },
  dataModeStrip: {
    height: 28,
    paddingHorizontal: 12,
    backgroundColor: '#e2e8f0',
    borderBottomWidth: 1,
    borderColor: '#cbd5e1',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  dataModeLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  dataModeText: {
    color: '#334155',
    fontSize: 10.5,
    fontWeight: '700',
  },
  buyDataPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f8fafc',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#cbd5e1',
  },
  buyDataText: {
    color: '#1e293b',
    fontSize: 10,
    fontWeight: '700',
  },
  iphoneScreenViewport: {
    flex: 1,
    width: '100%',
    backgroundColor: '#0f1d2d',
    position: 'relative',
    overflow: 'hidden',
  },
  iphoneHomeIndicatorRow: {
    height: 16,
    backgroundColor: '#ffffff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iphoneHomeIndicator: {
    width: 110,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#94a3b8',
  },
  phoneBottomOverlay: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 999,
    alignItems: 'center',
  },
  browserPopupMenu: {
    width: '90%',
    maxWidth: 310,
    backgroundColor: '#ffffff',
    borderRadius: 16,
    paddingVertical: 8,
    paddingHorizontal: 10,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#cbd5e1',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 16,
    gap: 2,
  },
  browserMenuHeader: {
    color: '#64748b',
    fontSize: 10,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  browserMenuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 8,
    paddingVertical: 9,
    borderRadius: 10,
  },
  browserMenuItemText: {
    flex: 1,
    color: '#0f172a',
    fontSize: 13,
    fontWeight: '700',
  },
  whiteAppSheet: {
    width: '100%',
    backgroundColor: '#ffffff',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 8,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -6 },
    shadowOpacity: 0.28,
    shadowRadius: 18,
    elevation: 16,
  },
  appLogoCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#1358a4',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
    shadowColor: '#1358a4',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
  },
  whiteSheetTitle: {
    color: '#0f172a',
    fontSize: 15,
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 3,
  },
  whiteSheetSubtitle: {
    color: '#64748b',
    fontSize: 11,
    lineHeight: 15,
    textAlign: 'center',
    marginBottom: 12,
    paddingHorizontal: 8,
  },
  blueBrowserBarRow: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    paddingHorizontal: 6,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: '#dbeafe',
    borderWidth: 1,
    borderColor: '#93c5fd',
  },
  blueCircleBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#2563eb',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#1d4ed8',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.35,
    shadowRadius: 4,
  },
  blueUrlPill: {
    flex: 1,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#3b82f6',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 12,
  },
  blueUrlText: {
    color: '#ffffff',
    fontSize: 11.5,
    fontWeight: '800',
  },
  notNowBtn: {
    paddingTop: 8,
    paddingBottom: 2,
    paddingHorizontal: 16,
  },
  notNowText: {
    color: '#0f172a',
    fontSize: 12,
    fontWeight: '800',
  },
  compactBrowserBarWrap: {
    width: '100%',
    paddingHorizontal: 10,
    paddingBottom: 6,
    paddingTop: 4,
    backgroundColor: 'rgba(15, 29, 45, 0.85)',
  },
});
