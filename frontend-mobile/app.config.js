const base = require('./app.json');

const googleMapsAndroidApiKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY;
const secureReleaseBuild = process.env.EAS_BUILD === 'true' || process.env.RESQ_ANDROID_RELEASE === '1';
const allowDevelopmentCleartext = process.env.NODE_ENV === 'development' && !secureReleaseBuild;
const apiUrls = [
  process.env.EXPO_PUBLIC_API_BASE_URL,
  ...(process.env.EXPO_PUBLIC_API_FALLBACK_URLS || '').split(','),
].filter(Boolean).map((url) => url.trim());

if (secureReleaseBuild) {
  if (!process.env.EXPO_PUBLIC_API_BASE_URL) {
    throw new Error('Secure Android releases require an HTTPS API base URL.');
  }

  for (const apiUrl of apiUrls) {
    const parsedUrl = new URL(apiUrl);
    if (parsedUrl.protocol !== 'https:' || parsedUrl.username || parsedUrl.password || parsedUrl.search || parsedUrl.hash
      || !parsedUrl.pathname.replace(/\/+$/, '').endsWith('/api/v1')) {
      throw new Error('Secure Android release API URLs must use HTTPS and end with /api/v1.');
    }
  }
}
const androidConfig = { ...(base.expo.android?.config || {}) };

if (googleMapsAndroidApiKey) {
  androidConfig.googleMaps = {
    apiKey: googleMapsAndroidApiKey,
  };
}

module.exports = {
  ...base.expo,
  plugins: base.expo.plugins.map((plugin) => {
    if (Array.isArray(plugin) && plugin[0] === 'expo-build-properties') {
      return [plugin[0], {
        ...plugin[1],
        android: {
          ...plugin[1].android,
          usesCleartextTraffic: allowDevelopmentCleartext,
          extraProguardRules: `${plugin[1].android?.extraProguardRules || ''}\n-keep class expo.modules.adapters.react.apploader.RNHeadlessAppLoader { *; }`,
        },
      }];
    }
    if (Array.isArray(plugin) && plugin[0] === 'onesignal-expo-plugin') {
      return [plugin[0], {
        ...plugin[1],
        mode: 'production',
      }];
    }
    return plugin;
  }).concat([
    './plugins/withDispatchAlarm',
    '@maplibre/maplibre-react-native',
    'expo-sqlite',
    'expo-task-manager',
    ['expo-media-library', {
      photosPermission: false,
      savePhotosPermission: 'Allow RESQPERATION to save your household QR image to your photo gallery.',
      granularPermissions: [],
      isAccessMediaLocationEnabled: false,
    }],
    ['expo-location', {
      locationWhenInUsePermission: 'Allow location access while following an active rescue dispatch.',
      locationAlwaysAndWhenInUsePermission: 'Allow location access while recording an active rescue dispatch.',
      isAndroidBackgroundLocationEnabled: true,
      isAndroidForegroundServiceEnabled: true,
      isIosBackgroundLocationEnabled: true,
      androidForegroundServiceIcon: './assets/images/android-icon-foreground.png',
    }],
  ]),
  android: {
    ...base.expo.android,
    ...(Object.keys(androidConfig).length ? { config: androidConfig } : {}),
  },
  extra: {
    ...base.expo.extra,
    oneSignalAppId: process.env.EXPO_PUBLIC_ONESIGNAL_APP_ID || base.expo.extra.oneSignalAppId,
    googleMapsAndroidApiKeyConfigured: Boolean(googleMapsAndroidApiKey),
  },
};
