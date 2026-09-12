import { Linking, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { palette, radius, spacing } from '@/constants/resqTheme';

type Point = {
  latitude?: number | string | null;
  longitude?: number | string | null;
  label?: string;
  color?: string;
};

export const canUseNativeMap =
  Platform.OS !== 'android' || process.env.EXPO_PUBLIC_ENABLE_NATIVE_ANDROID_MAPS === 'true';

export function MobileMapFallback({
  title = 'Native map setup required',
  message = 'Real Android maps need a Google Maps Android API key included in the installed APK.',
  point,
  points,
}: {
  title?: string;
  message?: string;
  point?: Point | null;
  points?: Point[];
}) {
  const firstPoint = (points || []).find((item) => isValidPoint(item)) || (isValidPoint(point) ? point : null);
  const latitude = Number(firstPoint?.latitude);
  const longitude = Number(firstPoint?.longitude);
  const hasPoint = Number.isFinite(latitude) && Number.isFinite(longitude);

  function openGoogleMaps() {
    if (!hasPoint) {
      Linking.openURL('https://www.google.com/maps/search/?api=1&query=Mambaling%20Cebu');
      return;
    }

    Linking.openURL(`https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`);
  }

  return (
    <View style={styles.box}>
      <View style={styles.iconBox}>
        <Ionicons name="map-outline" size={28} color={palette.navActive} />
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      <Pressable style={styles.button} onPress={openGoogleMaps}>
        <Ionicons name="open-outline" size={16} color="#fff" />
        <Text style={styles.buttonText}>Open in Google Maps</Text>
      </Pressable>
    </View>
  );
}

function isValidPoint(point?: Point | null) {
  return Number.isFinite(Number(point?.latitude)) && Number.isFinite(Number(point?.longitude));
}

const styles = StyleSheet.create({
  box: {
    minHeight: 260,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    padding: spacing.lg,
    backgroundColor: palette.secondary,
  },
  iconBox: {
    width: 56,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.lg,
    backgroundColor: palette.card,
  },
  title: {
    color: palette.text,
    fontSize: 15,
    fontWeight: '900',
    textAlign: 'center',
  },
  message: {
    maxWidth: 320,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
    lineHeight: 18,
    textAlign: 'center',
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    backgroundColor: palette.navActive,
  },
  buttonText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '900',
  },
});
