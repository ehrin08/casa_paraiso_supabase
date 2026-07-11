# Google Authentication Setup

Casa Paraiso uses Google OAuth for every customer, staff, admin, and super-admin login. Password login and manual registration are disabled.

## Google Cloud configuration

1. Create or select a Google Cloud project and configure the OAuth consent screen.
2. Create an OAuth 2.0 Web application client.
3. Add `http://localhost:8001/auth/google/callback` for local sign-in.
4. Add `http://localhost:8001/profile/delete/google/callback` for local account-deletion confirmation.
5. Add the equivalent HTTPS callback URLs for the final Hostinger domain.
6. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in the environment. Never commit the secret.

## Access rules

- `SUPER_ADMIN_EMAIL` must remain `ehrinjohn08@gmail.com` unless ownership is intentionally transferred through a reviewed deployment change.
- Unknown verified Google emails become customers.
- Staff and admin emails are pre-authorized from **Admin → User access** by the super admin.
- The protected super admin cannot be demoted, deactivated, renamed to another email, or deleted through the application.

## Recovery and security

Access to `ehrinjohn08@gmail.com` is operationally critical. Enable Google two-step verification, maintain current recovery email and phone information, and securely store Google recovery codes. Google Cloud client credentials should be included in the private deployment handover, not in the Git repository.

After deploying configuration changes on Hostinger, clear and rebuild Laravel's production configuration cache.
