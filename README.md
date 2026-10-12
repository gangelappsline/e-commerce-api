# ShopLine E-commerce API

API-only multi-tenant commerce backend built on Laravel 12. The API serves multiple stores from one database and application instance; storefront, customer, and business data are scoped to the resolved `business_id`.

## Stack

- PHP 8.3+ / Laravel 12
- MySQL 8 (primary database)
- Laravel Passport (OAuth2, password grant and personal access tokens)
- Spatie Laravel Permission (`api` guard)
- Laravel API Resources, Mail, Notifications, database/Redis queues
- Laravel Scout with Meilisearch for product search (SQL `LIKE` fallback while Meilisearch is unavailable)
- L5-Swagger / OpenAPI 3
- Intervention Image (GD/WebP)

Cashier and Sanctum are not installed. Payment processing is behind `App\Services\Payments\PaymentGateway`; no gateway is configured by default. Checkout safely creates a pending payment and does not claim a card was charged.

## Install

1. Install PHP 8.3 with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, and `gd`; install Composer, MySQL 8, and preferably Redis. Start a Meilisearch instance for indexed search.
2. Install PHP dependencies and configure the environment:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

   Set `DB_*`, `APP_URL`, mail, queue, Meilisearch, and the platform domain in `.env`. Set `SHOPLINE_PLATFORM_DOMAIN` to the root domain used for tenant subdomains (for example `shopline.example.com`).
3. Create Passport clients and apply the schema:

   ```bash
   php artisan passport:install
   php artisan passport:client --password
   php artisan migrate --seed
   php artisan storage:link
   ```

   `passport:install` provisions the Passport signing keys, migrations, and personal-access client used by `POST /api/v1/auth/login`. Keep the confidential password-grant client ID and secret from `passport:client --password` in your trusted client’s secret manager; never embed that secret in a public app. Trusted first-party clients can use Passport’s OAuth password grant at `/oauth/token`.
4. To designate an existing registered user as the initial platform administrator, set `SHOPLINE_ADMIN_EMAIL` to that user's email and run `php artisan db:seed`. The seeder creates global roles and permissions but does not create a default admin account.
5. Run the application and workers:

   ```bash
   php artisan serve
   php artisan queue:work --tries=3
   php artisan schedule:work
   php artisan scout:import "App\\Models\\Product"
   php artisan scout:sync-index-settings
   ```

   Production deployments should use PHP-FPM, a durable queue worker (Redis recommended), scheduler/cron, and object storage (S3-compatible) for media. Use `QUEUE_CONNECTION=redis` and `FILESYSTEM_DISK=s3` where appropriate.

## Tenant resolution and access

Storefront and tenant routes resolve the store in this order:

1. `X-Store-Slug: northwind-goods`
2. A store subdomain such as `northwind-goods.shopline.example.com`
3. A custom domain configured for an active store
4. `?store=northwind-goods`

A store must be `active` for tenant routes. New public business registrations are created as `pending`; a platform administrator can activate them through `PUT /api/v1/admin/businesses/{id}/status`.

Business routes require a Passport token, a resolved store, and an active `business_user` membership. Ownership/staff roles are tenant-scoped by the pivot; global roles are managed by Spatie. Admin users can access a resolved tenant without a membership.

## Guest checkout

1. `POST /api/v1/auth/login/guest` returns a short-lived `guest_token` and `session_id`.
2. Send `X-Store-Slug` and `X-Cart-Session: <session_id>` to cart endpoints.
3. Send `X-Guest-Token: <guest_token>` to checkout and guest order-status/tracking endpoints. The token is signed with `APP_KEY`, expires, and is tied to that cart session. It is separate from Passport access tokens.
4. Authenticated carts use the user's global customer profile and the current store's customer-business relationship.

Never trust a client-supplied price, tax, inventory count, tenant ID, or payment result. Checkout reloads prices and stock server-side under a transaction and lock. The API will not accept PAN/CVV data; saved payment methods must be gateway-issued tokens.

## API response envelope

Successful and failed JSON responses use the common envelope. Pagination metadata is returned under `meta.pagination`; `meta` includes a UTC timestamp, request UUID, and store slug when applicable. Errors use stable codes such as `VALIDATION_ERROR`, `UNAUTHENTICATED`, `UNAUTHORIZED`, `NOT_FOUND`, `CONFLICT`, `STORE_NOT_FOUND`, `STORE_INACTIVE`, `OUT_OF_STOCK`, `PAYMENT_FAILED`, and `SERVER_ERROR`.

## API documentation

After installing Composer dependencies, generate the OpenAPI document and open Swagger UI:

```bash
php artisan l5-swagger:generate
```

- Swagger UI: `/api/documentation`
- OpenAPI JSON: `/api/documentation.json`

The OpenAPI source and schemas live in `app/OpenApi/OpenApiSpec.php`; controller annotations include endpoint examples for the core auth, catalog, store, cart, and checkout flows.

## Included modules

The migrations define the core/auth, business/staff, catalog, product variants/images/attributes, inventory, carts, orders/shipments/returns, payment records, coupons/discounts, shipping, customers, wishlists, content/newsletter/contact, notifications, audit/webhook logs, plans/subscriptions/invoices, and settings tables. Tenant-aware models expose `forBusiness()` query scopes; tenant controllers and middleware apply explicit scopes rather than a risky implicit global scope.

API routes are versioned under `/api/v1`. The project includes customer/business auth and profile flows, public storefront/catalog/cart/checkout/order/content/plan endpoints, business catalog/inventory/order/staff/store management, dashboard analytics, coupons/shipping CRUD, webhooks, subscription lifecycle, and platform business/user administration.

## Operational notes

- All email classes implement `ShouldQueue`; use a real queue backend in production. The scheduler archives expired carts and queues recovery emails for identified customers.
- Product images are validated, resized, and converted to WebP. Configure a durable public media disk before production use.
- Webhook endpoints require HTTPS and public DNS addresses. Deliveries are HMAC-signed (`X-ShopLine-Signature`) and logged; webhook receivers should deduplicate deliveries.
- The payment gateway interface is intentionally unbound. A gateway adapter must handle idempotency, signature validation, authorization/capture/refund callbacks, and reconciliation before payment states are marked paid/refunded.
- Use a production secret manager for `APP_KEY`, Passport keys, Meilisearch key, mail credentials, and webhook secrets; do not commit `.env` files or generated Passport credentials.

## Validation

Run the project test suite with `composer test`. The execution environment used to prepare this repository did not include PHP/Composer, so Laravel migrations and PHPUnit could not be executed here; PHP files were syntax-parsed, but a full dependency install, `php artisan migrate`, Passport client generation, and integration tests must be run in a PHP 8.3/MySQL environment before deployment.
