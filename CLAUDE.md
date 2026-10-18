# CLAUDE.md

## Working preferences

- Follow existing Laravel architecture and naming conventions.
- Reuse existing authentication and authorization patterns.
- Validate request inputs and enforce record ownership server-side.
- Keep changes focused on the requested feature.
- Add tests for changed behavior, including access boundaries.
- Run relevant tests using `php artisan test`.
- Report executed checks and anything left unverified.
- Propose dependency or schema changes before implementing them.

## Development environment

- The app runs in Docker (`docker compose up -d`) at http://localhost:8000, backed by the `mysql` container.
- Run artisan inside the container: `docker compose exec app php artisan ...`. The host `.env` uses SQLite, so a host `php artisan migrate` does not touch the app's database.
- `vendor/` is a Docker volume. After changing dependencies on the host, run `docker compose exec app composer install`, then `docker compose restart queue scheduler`.
- MySQL from the host (e.g. Workbench): `127.0.0.1:3307`, database `payment_gateway`, user `laravel` (defaults in `docker-compose.yml`).
- `php artisan test` on the host is safe: phpunit forces in-memory SQLite, and `tests/TestCase.php` refuses to refresh any other database. Keep both guards.
- Verify risky migrations on a scratch SQLite copy, never directly against shared dev data.
- `php artisan db:seed` is safe to rerun. It creates `test@example.com` (merchant), `admin@example.com` (admin) and two simulated providers. Grant admin to an existing user with `php artisan user:grant-admin {email}`.

## Payment providers

- Merchants call one unified API. Each upstream bank/PSP has its own API and gets a driver class implementing `App\Services\Processor\PaymentProcessor`, registered in `config('gateway.provider_drivers')`.
- Each merchant has one provider per payment method (`CARD`, `QRPH`, `GCASH`, `MAYA`, `ECPAY`, `INSTAPAY`, `PESONET`). Providers can offer the same method; they differ in pricing.
- `payment_method` is required on `POST /api/v1/payments`. Non-card methods are asynchronous: `requires_action` + `next_action`, completed by a signed callback to `/api/provider-callbacks/{provider}`. Unpaid ones are canceled (`failure_code` `expired`) by the scheduled `payments:expire-pending`. Callbacks that contradict a final status are not applied: they return 409 and are logged for reconciliation.
- A payment keeps the provider and fee it was created with; capture, cancel, refund and callbacks use `$payment->provider`, never the merchant's current assignment.
- A provider's `code` (callback URL) and `driver` cannot change after creation. The `simulated` driver is only registered while `GATEWAY_SIMULATOR_ENABLED` is on (local/testing by default); providers with unregistered drivers cannot be assigned or charged.
- Roles and permissions come from spatie/laravel-permission: `admin` (`providers.manage`, `merchant-providers.assign`) and `merchant` (dashboard).
- API secrets, webhook secrets and provider credentials are encrypted with `APP_KEY`. Changing or losing it invalidates them all.
