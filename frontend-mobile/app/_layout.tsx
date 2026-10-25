import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack, usePathname } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import 'react-native-reanimated';
import '@/utils/navigationRuntime';
import '@/utils/rescuerLiveLocation';

import { useColorScheme } from '@/hooks/use-color-scheme';
import { configureNotificationHandler } from '@/utils/pushNotifications';
import { NotificationPermissionNotice } from '@/components/NotificationPermissionNotice';
import { Provider } from 'react-redux';
import { store } from '@/state/apiStore';

export const unstable_settings = {
  initialRouteName: 'index',
};

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const pathname = usePathname();
  const hasBrandedHeader = pathname === '/household' || pathname.startsWith('/household/') || pathname === '/rescuer' || pathname.startsWith('/rescuer/');

  useEffect(() => {
    configureNotificationHandler();
  }, []);

  return (
    <Provider store={store}><ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <NotificationPermissionNotice />
      <Stack>
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="forgot-password" options={{ headerShown: false }} />
        <Stack.Screen name="household/index" options={{ headerShown: false }} />
        <Stack.Screen name="rescuer/index" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="modal" options={{ presentation: 'modal', title: 'Modal' }} />
      </Stack>
      <StatusBar style={hasBrandedHeader || pathname === '/' ? "light" : "auto"} />
    </ThemeProvider></Provider>
  );
}
