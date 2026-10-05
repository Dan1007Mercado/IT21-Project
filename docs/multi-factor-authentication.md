# INTSEC multi-factor authentication

INTSEC requires every interactive user and administrator to complete password and reCAPTCHA authentication followed by a second factor. TOTP from an authenticator app is primary, verified-email OTP is fallback, and a single-use recovery code or recovery file is emergency-only.

## Architecture

- Password/reCAPTCHA creates a restricted Laravel-authenticated session and clears any previous MFA state.
- Operational routes require `auth`, `mfa.configured`, and `mfa.verified`; administrator routes additionally require `admin`.
- MFA session state is server-side, regenerated after successful MFA, and bound to the authenticated user ID.
- Existing users have nullable MFA columns and are redirected to mandatory enrollment instead of being rejected.
- TOTP secrets and pending replacement secrets use Laravel's encrypted cast backed by `APP_KEY`.
- Email OTPs and recovery codes are independently hashed. Email OTP purposes are isolated and recovery consumption is transactional.
- Recovery files are streamed from an encrypted, short-lived session bundle and are never saved to application storage.
- QR codes are generated locally as SVG data URIs. The provisioning URI is never sent to an external QR service.
- MFA security events contain only safe identifiers and outcome metadata. Authentication does not depend on Reverb or successful security-notification delivery.

MFA cannot be disabled because the application policy requires it for every account.

## Local installation

Keep the existing `APP_KEY`; never regenerate it after encrypted TOTP data exists.

```shell
composer install
php artisan migrate
php artisan optimize:clear
npm install
npm run build
php artisan test
```

Optional configuration defaults are documented in `.env.example`:

```env
MFA_ISSUER=INTSEC
MFA_EMAIL_OTP_TTL=300
MFA_EMAIL_OTP_RESEND_COOLDOWN=60
MFA_MAX_ATTEMPTS=5
MFA_RECOVERY_CODE_COUNT=10
```

For Brevo, set Laravel's normal `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` values only in the environment. OTP mail is sent synchronously so login does not depend on a queue worker.

## Render deployment

1. Keep one persistent production `APP_KEY`; rotating it makes encrypted TOTP secrets unreadable.
2. Configure the database and database-backed sessions as usual, plus production Brevo SMTP and reCAPTCHA variables.
3. Optionally override the `MFA_*` variables above. Defaults are production-safe.
4. Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize:clear`, and `php artisan config:cache` during deployment.
5. Build frontend assets with `npm ci && npm run build` in the build stage.
6. No persistent disk, Redis, Google API key, Google OAuth client, or MFA queue worker is required.

## Manual verification

### Authenticator enrollment

1. Sign in with email, password, and reCAPTCHA.
2. Verify the account email if prompted.
3. On **Set up authenticator app**, open Google Authenticator and choose **Add account → Scan a QR code**.
4. Scan the INTSEC QR. If scanning is unavailable, enter the displayed setup key, account, and issuer manually.
5. Enter the current six-digit code in INTSEC.
6. Save or download the one-time recovery codes, acknowledge that they were saved, and finish setup.
7. Sign out, sign in again, and enter a new rotating authenticator code.

### Brevo email fallback

1. Complete password/reCAPTCHA and open **Send email code** from the MFA challenge.
2. Send the code, confirm it arrives at the masked verified address, and enter it within the configured TTL.
3. Confirm a wrong, expired, previously used, or superseded code fails.
4. Temporarily use an invalid SMTP setting in a non-production environment and confirm mail failure leaves authenticator and emergency recovery available without granting access.

### Emergency recovery

1. Download the recovery file during enrollment and keep it offline.
2. After a new password/reCAPTCHA login, choose **Use emergency recovery**.
3. Enter one code or upload the `.txt` recovery file. Confirm access succeeds and exactly one code is consumed.
4. Sign in again and confirm the consumed code fails.
5. Regenerate codes from **Profile & Security** using the current password and authenticator code; confirm every code in the old file then fails.
