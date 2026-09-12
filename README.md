# Pricing API

Laravel 13 app in this folder. Local SQLite is the connector-MVP database; production persistence is decided later.

## Setup

```bash
composer setup
composer dev
```

`composer setup` copies `.env`, generates `APP_KEY`, migrates, and builds front-end assets. `composer dev` serves the app at `http://localhost:8000`.

Health: `GET http://localhost:8000/up`.

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

Recommendations after sync are a **stub** (`recommended_price` = synced price). The real floor-aware engine is backend phase B6.
