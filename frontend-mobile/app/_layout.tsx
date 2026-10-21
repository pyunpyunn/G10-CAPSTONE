import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack, usePathname } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import 'react-native-reanimated';
import '@/utils/navigationRuntime';

import { palette } from '@/constants/resqTheme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { configureNotificationHandler } from '@/utils/pushNotifications';
import { NotificationPermissionNotice } from '@/components/NotificationPermissionNotice';

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
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <NotificationPermissionNotice statusBarBackgroundColor={hasBrandedHeader ? palette.nav : undefined} />
      <Stack>
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="household/index" options={{ headerShown: false }} />
        <Stack.Screen name="rescuer/index" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="modal" options={{ presentation: 'modal', title: 'Modal' }} />
      </Stack>
      <StatusBar style={hasBrandedHeader ? "light" : "auto"} />
    </ThemeProvider>
  );
}
