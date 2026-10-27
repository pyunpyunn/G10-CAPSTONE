import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Slot } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { useColorScheme } from '@/hooks/use-color-scheme';
import { configureNotificationHandler } from '@/utils/pushNotifications';
import { registerPwa } from '@/utils/registerPwa.web';
import { Provider } from 'react-redux';
import { store } from '@/state/apiStore';
export { ErrorBoundary } from 'expo-router';

export const unstable_settings = {
  initialRouteName: 'index',
};

export default function RootLayout() {
  const colorScheme = useColorScheme();

  useEffect(() => {
    configureNotificationHandler();
    registerPwa();
  }, []);

  return (
    <Provider store={store}><ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Slot />
      <StatusBar style="auto" />
    </ThemeProvider></Provider>
  );
}
