# Casa Paraiso Android Installation

## Build the signed APK

The one-time command below creates the 4096-bit release key under `%USERPROFILE%\.casa-paraiso`, restricts that directory to the current Windows account, synchronizes Capacitor, builds version `1.0.0` (code `1`), and verifies the APK signature:

```powershell
.\scripts\build-mobile-release.ps1 -InitializeSigning
```

Back up `%USERPROFILE%\.casa-paraiso` securely. Losing this directory prevents future APK updates from being installed over the existing app. Never commit or share its keystore or property file.

Subsequent builds reuse the same key:

```powershell
.\scripts\build-mobile-release.ps1
```

The universal APK and checksum are written to:

```text
mobile\android\app\build\outputs\apk\release\app-release.apk
mobile\android\app\build\outputs\apk\release\app-release.apk.sha256
```

## Install on a phone

1. Enable Android Developer options and USB debugging.
2. Connect the phone by USB and accept its authorization prompt.
3. Build and install with exactly one authorized phone connected:

```powershell
.\scripts\build-mobile-release.ps1 -Install
```

Alternatively, copy `app-release.apk` to the phone, open it, and allow installation from the selected file manager when Android asks. Check the published SHA-256 value before installing a copied APK.

## Start a demonstration

From the repository root:

```powershell
.\scripts\mobile-demo.ps1 -Action Start
```

If one authorized phone is connected, the helper opens the pairing link automatically. Otherwise enter the displayed Quick Tunnel URL and eight-digit code in the app. Google Cloud must authorize the exact displayed mobile callback ending in `/auth/google/mobile/callback`; the callback changes whenever the Quick Tunnel rotates.

Sign in, exercise the appropriate role workspace, and stop the public tunnel afterward:

```powershell
.\scripts\mobile-demo.ps1 -Action Stop
```

The app is online-only. A stopped or rotated tunnel requires a new pairing URL and code.
