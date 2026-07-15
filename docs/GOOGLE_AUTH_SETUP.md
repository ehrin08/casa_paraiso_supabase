# Google Authentication Setup

Casa Paraiso supports verified Google OAuth alongside verified email/password registration, recovery, and login. The browser fallback and bundled Android app share the same identity-linking rules.

## Google Cloud configuration

1. Create or select a Google Cloud project and configure the OAuth consent screen.
2. Create an OAuth 2.0 Web application client.
3. Add `http://localhost:18001/auth/google/callback` for local browser sign-in.
4. Add `http://localhost:18001/profile/delete/google/callback` for local account-deletion confirmation.
5. For a phone demonstration, run `scripts/mobile-demo.ps1 -Action Start` and add the exact displayed HTTPS callback ending in `/auth/google/mobile/callback`. A rotated Quick Tunnel requires replacing that authorized callback.
6. Add the equivalent browser, mobile, and deletion HTTPS callback URLs for the final backend domain.
7. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback` in the backend environment. Never commit the secret and never place it in the APK.

## Android exchange

The Android app opens Google in the system browser. The backend binds a random state and SHA-256 PKCE challenge to the paired server instance and device for five minutes. Google returns to `/auth/google/mobile/callback`; the backend then sends only a single-use exchange code through `casaparaiso://oauth/callback`. The app proves possession of the verifier before Laravel issues its 30-day Sanctum device token. Bearer tokens and Google authorization codes never appear in the app callback URL.

## Access rules

- `SUPER_ADMIN_EMAIL` must remain `ehrinjohn08@gmail.com` unless ownership is intentionally transferred through a reviewed deployment change.
- Unknown verified Google emails become customers.
- Staff and admin emails are pre-authorized from **Admin → User access** by the super admin.
- The protected super admin cannot be demoted, deactivated, renamed to another email, or deleted through the application.

## Recovery and security

Access to `ehrinjohn08@gmail.com` is operationally critical. Enable Google two-step verification, maintain current recovery email and phone information, and securely store Google recovery codes. Google Cloud client credentials should be included in the private deployment handover, not in the Git repository.

After deploying configuration changes, clear and rebuild Laravel's production configuration cache. Test both callback routes and the Android exchange on the final HTTPS host.
