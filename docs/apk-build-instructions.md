# APK Build Instructions

The Android app is a Capacitor shell for the existing PosLAB web backend. Do not duplicate POS business logic in Android; the APK must call the same Laravel API and web routes as the browser app.

## Environment

Set these values per environment:

- `CAPACITOR_ANDROID_APP_ID`: Android package name, for example `com.yourcompany.poslab`
- `CAPACITOR_SERVER_URL`: hosted PosLAB backend URL, for example `https://pos.example.com`
- `VITE_API_BASE_URL`: same backend API base URL used by the web bundle

## Build Flow

1. Install dependencies with `npm install`.
2. Build web assets with `npm run build`.
3. Set `CAPACITOR_SERVER_URL` to the staging or production backend.
4. Sync native assets with `npm run cap:sync`.
5. Open Android Studio with `npm run cap:open:android`.
6. Build a debug APK from Android Studio for device testing.
7. Build a signed release APK or AAB using Android Studio release signing configuration.

The `capacitor-www` folder is a minimal native shell. Runtime screens, authentication, orders, billing, printing, and reports continue to come from the Laravel/Vue web app.

## Printer Bridge

Direct USB/Bluetooth printing should be added later through a local bridge or native adapter. This preparation does not add printer-specific APK business logic.
