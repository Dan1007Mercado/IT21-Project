# reCAPTCHA deployment on Render

INTSEC uses the same reCAPTCHA v2 checkbox implementation in every environment. Keys and Google domain authorization are the only environment-specific parts.

## Deployment checklist

1. Create a separate production reCAPTCHA v2 checkbox key pair. In Render, set `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY`; do not copy either value into source control. Also configure the normal Laravel production variables, including `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` with the public HTTPS URL, and a persistent `APP_KEY`.
2. In the Google reCAPTCHA console, register the exact public production hostname assigned to the service (the hostname only, without `https://`, a path, or a port). Add any later custom hostname before directing users to it.
3. After changing environment values, rebuild Laravel's cached configuration during deployment with `php artisan optimize:clear` followed by `php artisan config:cache`. If routes and views are cached by the deployment, run `php artisan route:cache` and `php artisan view:cache` as well.
4. Keep the existing Laravel trusted-proxy setup. Set `INTSEC_TRUSTED_PROXIES` only to verified Render proxy addresses or CIDR ranges when Render publishes stable values for the service; never use `*` merely to make HTTPS detection work. Confirm that Laravel generates HTTPS URLs and resolves the expected client address in staging. reCAPTCHA itself does not require a proxy bypass.
5. For HTTPS production, set `SESSION_SECURE_COOKIE=true` and retain `SESSION_SAME_SITE=lax` unless a documented cross-site authentication flow requires a different value. Leave `SESSION_DOMAIN` unset for the Render hostname unless cookies must deliberately span your own subdomains. Do not disable CSRF protection.
6. Open the deployed login page in a private browser window, confirm the widget loads, complete it, and test both a valid login and a deliberately expired or omitted challenge. The valid challenge should allow normal authentication; the missing/invalid challenge should return to login without authenticating. Check browser developer tools and Render logs for connectivity errors without logging tokens or secrets.
7. Before rollout, keep an authenticated administrator session and a Render shell/dashboard path available. If login becomes inaccessible, roll back the deployment or restore the previous production key pair and Google hostname authorization, then clear/rebuild configuration caches. Never add a local/production CAPTCHA bypass as a rollback mechanism.

Cloudflare Tunnel and local hostnames are authorized in Google's key configuration, not in INTSEC code. Local `.env` values can therefore be replaced by Render environment values without changing PHP, Blade, JavaScript, routes, or controllers.
