# Pricing API

Laravel 13 app in this folder. Local SQLite is the connector-MVP database; production persistence is decided later.

## Setup

```bash
composer setup
composer dev
```

`composer setup` copies `.env`, generates `APP_KEY`, migrates, and builds front-end assets. `composer dev` serves the app at `http://localhost:8000`.

Health: `GET http://localhost:8000/up`.

## Local dashboard + rivals smoke

1. Migrate (fixes missing `shops.user_id`):

```bash
php artisan migrate
```

2. Seed demo seller (idempotent):

```bash
php artisan db:seed
# or: php artisan db:seed --class=DashboardSeeder
```

3. Login at `http://localhost:8000/login`:
   - email: `seller@pricing.test`
   - password: `password`

4. Open **محصولات** (`/dashboard/products`) — rival cheapest/median/count come from the **fake** Torob driver (no network).

5. Queue (if `QUEUE_CONNECTION=database`): after a WP sync, run `php artisan queue:work` so `DiscoverShopRivalsJob` runs. With `QUEUE_CONNECTION=sync`, discover runs inline on sync.

Keep `.env`: `RIVALS_DRIVER=fake` for local. Live Torob only when `RIVALS_TOROB_ENABLED=true` and ops says go.

---

## Seller panel wow (10 minutes)

Vite panel at `D:\dev\pricing\panel` talks to this API over Sanctum Bearer tokens.

**API**

```bash
# from api/
composer dev
# .env: RIVALS_DRIVER=fake and QUEUE_CONNECTION=sync (rivals appear right after WP sync)
php artisan db:seed   # plans + optional demo shop
```

**Panel**

```bash
# from panel/
cp .env.example .env   # VITE_API_URL=http://localhost:8000/api  VITE_USE_MSW=false
npm run dev
```

**Path**

1. Open the panel → **ثبت‌نام** (creates user + Starter shop).
2. **کلید API** → create key → copy the one-time secret.
3. WordPress plugin Pricing → Connection: service URL `http://localhost:8000/v1` + paste key → connect → sync products.
4. Refresh panel **خانه** / **محصولات** — you should see rival source, cheapest, count, vs store, or status **در حال پیدا کردن** (not the old MSW perfume mock).

Seller JSON routes (prefix `/api`, separate from connector `/v1`):

- `POST /api/auth/register` · `POST /api/auth/login` · `POST /api/auth/logout`
- `GET /api/me`
- `GET /api/pricing/overview` · `GET /api/pricing/products`
- `GET|POST /api/pricing/api-keys` · `POST /api/pricing/api-keys/{id}/revoke`

Set `VITE_USE_MSW=true` only for offline UI demos without the API.

---

## Connector (Phase 1)

WP talks to **`http://localhost:8000/v1`** with `Authorization: Bearer <api_key>`. Seed a non-production shop + key:

```bash
php artisan db:seed
```

Default key (override with `CONNECTOR_DEV_API_KEY` in `.env`):

`dev_pk_local_connector_key_do_not_use_in_prod`

```bash
export KEY=dev_pk_local_connector_key_do_not_use_in_prod

curl -X POST http://localhost:8000/v1/connector/validate \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" -d "{}"

curl -X POST http://localhost:8000/v1/connector/products/sync \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"currency":"IRR","products":[{"external_id":"42","sku":"SKU-001","name":"Sample Product","price":"1500000"}]}'

curl http://localhost:8000/v1/connector/products/42/recommendation \
  -H "Authorization: Bearer $KEY"

curl -X POST http://localhost:8000/v1/connector/products/42/applied \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"applied_price":"1500000","currency":"IRR","source":"manual","applied_at":"2026-09-07T12:05:00Z"}'
```

Recommendations after sync use the **B6 floor-aware engine** when the shop has a `shop_cost_profiles` row (seeded for local). Strategy is `floor_plus_margin` (recommended = effective floor) until rivals (B7).

Without a cost profile:
- `local` / `testing`: B4 stub (`recommended_price` = synced price) unless `CONNECTOR_STUB_RECOMMENDATIONS=false`
- `production`: stub off by default — no recommendation until costs exist

Cost entry UI is backend phase **B8** (dashboard). Rival auto-match is **B7**; alerts **B9**; reports `/dashboard/reports`. Production + Zhaket/RTL license webhook: see [`docs/deploy/production.md`](../docs/deploy/production.md).

Optional `.env`:

```
CONNECTOR_STUB_RECOMMENDATIONS=false
MARKETPLACE_WEBHOOK_SECRET=
```
