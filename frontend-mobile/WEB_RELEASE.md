# Mobile PWA / SPA

The existing React Native / Expo pages remain in use. Web output is a single-page app with an install manifest and a versioned offline application shell.

```powershell
# Set this to the actual HTTPS backend before building, if hosted separately.
$env:EXPO_PUBLIC_API_BASE_URL = 'https://YOUR_BACKEND_HOST/api/v1'
npm run export:web
npm run preview:web
```

Upload `dist/` to an HTTPS host at its domain root. Rewrite application routes to `/index.html`, but never rewrite `/api/` or missing asset requests. Serve `sw.js`, `index.html` and the manifest with `Cache-Control: no-cache`. If no API URL is configured, the app uses `/api/v1` on the same origin; configure that reverse proxy and backend CORS as appropriate.

Open the site online once to install its shell. Browser installation is available through the browser's Install/Add to Home Screen option. New service-worker versions activate after existing app windows close. The worker caches only exported app assets; it does not cache authenticated API responses or bulk-download map tiles. Offline shell availability does not make fresh server data available offline.

The APK retains native push, background GPS, offline map packs and trip storage. Browsers do not provide equivalent background tracking guarantees; the existing web build does not configure web push or native trip recording. Web QR downloads use normal browser downloads, whose destination is controlled by browser settings. Android/iOS QR downloads save directly to the photo library, with the required OS permission.

Build a new APK for `expo-media-library`; a JavaScript-only update cannot add this native module. Check gallery saving and an active trip on a physical older Android phone before release. Device crash/thermal verification requires a connected phone; render error recovery cannot catch Android process termination from low memory or a native crash.

GPS writes use the existing operations queue for road-route refreshes. Keep its worker running (the backend's `composer run dev` already starts it):

```powershell
php artisan queue:work operations_outbox --queue=operations --sleep=1 --tries=3 --timeout=120
```

The Worklets dependency is patched from 0.5.1 to 0.5.2 to fix a reproduced web startup crash in lazy module initialization: https://github.com/software-mansion/react-native-reanimated/pull/8300

Expo reference: https://docs.expo.dev/guides/progressive-web-apps/
