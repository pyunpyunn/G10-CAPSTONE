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
  android: {
    ...base.expo.android,
    ...(Object.keys(androidConfig).length ? { config: androidConfig } : {}),
  },
  extra: {
    ...base.expo.extra,
    googleMapsAndroidApiKeyConfigured: Boolean(googleMapsAndroidApiKey),
  },
};
