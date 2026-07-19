# Casa Paraiso Android Installation

## Build the signed APK

The one-time command below creates the 4096-bit release key under `%USERPROFILE%\.casa-paraiso`, restricts that directory to the current Windows account, synchronizes Capacitor, builds version `1.0.1` (code `2`), and verifies the APK signature. Supply the live HTTPS Render origin; it is compiled into the release APK so users never have to pair to a laptop tunnel:

```powershell
.\scripts\build-mobile-release.ps1 -InitializeSigning -BackendUrl https://casa-paraiso-supabase-api-poc.onrender.com
```

Back up `%USERPROFILE%\.casa-paraiso` securely. Losing this directory prevents future APK updates from being installed over the existing app. Never commit or share its keystore or property file.

Subsequent builds reuse the same key:

```powershell
.\scripts\build-mobile-release.ps1 -BackendUrl https://casa-paraiso-supabase-api-poc.onrender.com
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
.\scripts\build-mobile-release.ps1 -Install -BackendUrl https://casa-paraiso-supabase-api-poc.onrender.com
```

Alternatively, copy `app-release.apk` to the phone, open it, and allow installation from the selected file manager when Android asks. Check the published SHA-256 value before installing a copied APK.

## Start a demonstration

From the repository root:

```powershell
.\scripts\mobile-demo.ps1 -Action Start
```

The helper is browser/API-only. It starts a temporary Quick Tunnel, validates the API, and prints the Google callback URLs; Android debug and release builds use the stable Render endpoint and never accept a Quick Tunnel connection link. Google Cloud must authorize the exact displayed mobile callback ending in `/auth/google/mobile/callback`; the callback changes whenever the Quick Tunnel rotates.

Sign in, exercise the appropriate role workspace, and stop the public tunnel afterward:

```powershell
.\scripts\mobile-demo.ps1 -Action Stop
```

The app is online-only. A stopped or rotated Quick Tunnel affects browser/API development only; Android builds continue using Render.
