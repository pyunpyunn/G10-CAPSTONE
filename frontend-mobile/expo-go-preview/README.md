# Expo Go UI Preview

This folder has a separate Expo SDK 58 dependency set for testing the existing mobile app in the current iPhone Expo Go. It points Expo Router and Metro at `../app` and does not change the production app's SDK 54 manifest or lockfile.

From this folder, run `npm install --legacy-peer-deps` once, then `npm start`. Scan the QR code with the iPhone camera or Expo Go. The preview runs on port 8082 so it can coexist with the production project's Metro server.

OneSignal push notifications and MapLibre offline navigation require the installed RESQPERATION app; they are not available in Expo Go. The preview uses the existing React Native Maps screen for map viewing.
