# Mobile release 20 — 23 October 2026

Build 20 updates the existing signed Android app and includes all Build 19 routing changes.

## Navigation storage

Navigation refresh, GPS recording, sync acknowledgements and other storage operations previously ran concurrently. Exclusive SQLite transactions opened separate connections and could reject competing writes with `NativeDatabase.execAsync: database is locked`. A failed initialization also left a rejected cached database promise until restart.

All public navigation storage access now passes through one queue; internal nested reads stay within the current operation. Atomic transactions reuse that serialized connection, including its foreign-key configuration and five-second busy timeout. Failed initialization closes the handle and permits the next call to retry. Existing cached routes, trips and pending sync events are retained.

Three regression tests use real SQLite and asynchronous operations to check concurrent route refresh/GPS/sync, rollback preservation and initialization recovery. All 12 selected navigation tests and TypeScript passed. Targeted lint had no errors (the existing array-type warning remains). Android release assembly, signing certificate, version code and APK ZIP integrity passed. Embedded bundle checks confirm the storage configuration, new notification copy and Tailscale API address. No phone was attached for device keyboard/GPS testing.

## Login and notification prompt

The Android activity uses `adjustResize`; the login screen uses a bounded keyboard-avoiding scroll view and scrolls the focused credential field into view after keyboard opening. Server-address and password-change fields use the same handling.

The notification notice is a dismissible modal overlay with the login screen's navy, red and light-text palette. Enable opens the existing permission/settings flow; Not now or Close dismisses the reminder for the current app session. It no longer consumes login layout space or uses a yellow banner.

## Install

Open `http://100.121.155.55:8000/downloads/index.html` while connected to Tailscale, then select Download APK and install over the existing app. The page points to `RESQPERATION-build20.apk`. Laravel and Tailscale remain required for live API access. Each phone must install the update.

APK: `frontend-mobile/releases/RESQPERATION-build20.apk`, 22,350,865 bytes, versionCode 20, versionName 1.0.0, signed non-debuggable ARM64 release. SHA-256: `adb3911753173b67835126a923fd89356ece52187ad4731bd2d19f39c10e404e`.
