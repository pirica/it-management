# Stripe Checkout (hotel booking portal)

Guest-facing Stripe Checkout for the public `booking/` portal — procedural PHP with **curl** to `api.stripe.com` (no Composer).

## Tables

| Table | Purpose |
|-------|---------|
| `hotel_booking_settings` | `stripe_enabled`, `stripe_mode` (`test`/`live`), `stripe_publishable_key`, encrypted secret + webhook signing secret, `deposit_percent` |
| `hotel_bookings` | `payment_status` (`unpaid`/`pending`/`paid`/`refunded`), `stripe_checkout_session_id`, `stripe_payment_intent_id`, `amount_paid` |
| `hotel_booking_payment_events` | Stripe webhook / session audit payloads (`event_type`, `payload_json`) |

Canonical DDL: `db/01_schema.sql`. Existing databases: `db/migrations/hotel_booking_stripe.sql` (`php scripts/migrate.php --apply`).

## Helper

`includes/itm_stripe_checkout.php` — loaded from booking portal files, admin settings save, and verify script only (not global `config.php`).

- `itm_stripe_checkout_encrypt_secret()` / `itm_stripe_checkout_decrypt_secret()` — same pattern as `itm_webhook_queue_encrypt_secret()`
- `itm_stripe_checkout_is_enabled($conn, $companyId)`
- `itm_stripe_create_checkout_session()` — POST `checkout/sessions` via curl
- `itm_stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)`
- `itm_stripe_handle_webhook_event($conn, $event)` — `checkout.session.completed` → mark group paid, confirmation emails, `itm_webhook_queue_emit_hotel_booking_confirmed()`

## Guest flow

1. Step 4 (`booking/rooms/room-single.php`) — when Stripe enabled, guest chooses **Pay now with card** (`pay_method=stripe`).
2. Booking rows created with `payment_status=pending`; confirmation emails deferred until webhook.
3. Redirect to [`booking/payment-stripe.php`](http://localhost/it-management/booking/payment-stripe.php) → Stripe hosted Checkout.
4. Success/cancel return to [`booking/rooms/payment.php`](http://localhost/it-management/booking/rooms/payment.php) (`?stripe=success|cancel|error`).

## Webhook

- URL: `http://localhost/it-management/booking/stripe-webhook.php?company_id={company_id}` (Admin → Hotel Booking Settings shows tenant URL).
- Defines `ITM_STRIPE_WEBHOOK` before `config.php` (no employee session).
- Verifies `Stripe-Signature` with tenant webhook signing secret from settings.
- Multi-room stays: one session; webhook updates all bookings in the same `auth2` + date group and splits `amount_paid`.

## Admin

[`modules/hotel_booking_settings/index.php`](http://localhost/it-management/modules/hotel_booking_settings/index.php) — Stripe section (enable, mode, publishable key, secret key, webhook signing secret, deposit %). Secret fields are encrypted at rest; leave password inputs blank to keep existing values.

## Regression

```bash
php scripts/verify_stripe_checkout.php
```

Browser (Administrator): [verify_stripe_checkout.php?run=1](http://localhost/it-management/scripts/verify_stripe_checkout.php?run=1)

No live Stripe API key required — encrypt round-trip, signature probes, and mock session payload validation.

## Related docs

- Guest portal overview: `docs/BOOKING.md`
- Outbound integration webhooks (post-confirmation): `includes/itm_webhook_queue.php`
