const base = require('./app.json');

const googleMapsAndroidApiKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY;
const androidConfig = { ...(base.expo.android?.config || {}) };

if (googleMapsAndroidApiKey) {
  androidConfig.googleMaps = {
    apiKey: googleMapsAndroidApiKey,
  };
}

module.exports = {
  ...base.expo,
  plugins: base.expo.plugins.map((plugin) => {
    if (Array.isArray(plugin) && plugin[0] === 'onesignal-expo-plugin') {
      return [plugin[0], {
        ...plugin[1],
        mode: process.env.EAS_BUILD_PROFILE === 'production' ? 'production' : 'development',
      }];
    }
    return plugin;
  }),
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
