# AGENT_NOTES.md - Public booking portal

## 1. Purpose

Guest-facing hotel listing and booking under `/it-management/booking/`. Uses ITM `config/config.php` (MySQLi) and `includes/itm_hotel_booking.php`.

**Canonical doc:** `docs/BOOKING.md` (architecture, flows, admin map, review, troubleshooting).

`bootstrap.php` defines `ITM_HOTEL_BOOKING_PUBLIC_PORTAL` before `config.php` so global employee login is skipped for this tree.

## 3. Auth

- **Browse and book:** no portal login. Guest details (name, email, phone) are collected on `rooms/room-single.php` and stored via `customers` + `hotel_bookings`.
- **Manage booking:** `users/bookings.php` — last name + **reservation ID** (`hotel_bookings.id`) + **auth2** (random 4-digit PIN on `hotel_bookings.auth2`); verified by `itm_hotel_booking_fetch_for_guest_manage()`. The confirmation page shows the auth code and links "Manage my booking" to the company-configured `urlmybooking` URL with `target="_blank"`.
- **Read reviews:** `hotel_booking_settings.reviews_url` (company default) and optional per-hotel `hotel_booking_hotels.reviews_url`; resolved via `itm_hotel_booking_resolve_reviews_url()`. Under the green rating bubbles: **Guest rating** — based on recent stays, then **Read reviews ↗** (example seed: Conrad Algarve TripAdvisor `#REVIEWS` URL).
- Optional legacy: `hotel_booking_portal_users` and `auth/login.php` / `register.php` (not required for public flow).
- **CSRF:** Public auth POST handlers use `itm_require_post_csrf()` via `bootstrap.php`.

## 4. Entry points

- `index.php` — hotel list (all active `hotel_booking_hotels` across tenants); each card image has gallery **‹ ›** arrows and **`1 / N`** counter when multiple photos exist; **Details** opens the same gallery in the modal
- `rooms.php` — **Step 1 of 4** Select a Room; room-type cards and hotel sidebar use gallery **‹ ›** arrows + **`1 / N`** counter; **View room details** modal uses the same gallery
- `rooms/select-rate.php` — **Step 2 of 4** Select a Rate (active `hotel_booking_portal_rate_plans` per hotel; posts `portal_rate_plan_id`; special requests, comments).
- `rooms/customize.php` — **Step 3 of 4**: main column upgrade card; **View room details** opens the same room-detail modal as Step 1 (no Quick compare link); right column stacks stepper then **Reservation summary** (tourist tax €2/guest/night from settings).
- `rooms/room-single.php` — **Step 4 of 4** guest form: locked check-in/out from draft; saves `hotel_bookings.portal_rate_plan_id` from checkout draft; email (`filter_var`) and phone (E.164 `+` country code) validated server-side.
- `rooms/payment.php` — confirmation after step 4: **Number of nights** `(N night(s))`, **Guests** `👤 …` occupancy line, **Reservation notes**, jsPDF download (**Save booking confirmation** — same card as screen, not print preview). Occupancy stored in `hotel_bookings.notes` as `Occupancy: rooms=…` meta line. Confirmation PDF is a rasterized card (html2canvas) plus a **clickable PDF `/URI` annotation** over the inline **Manage my booking** hint (`data-hb-pdf-manage-link`, `hotel-booking-confirmation-pdf.js` → `hbPdfAddManageBookingLink`); action buttons stay `.hb-pdf-exclude`.
- `rooms/confirmation-pdf.php` — session-scoped auto-PDF page (`data-hb-auto-pdf`) for the last booking in the same browser session.
- `calendar.php` — JSON nightly rates for Select Dates modal (check-in + optional check-out range; single check-in = 1 night). Modal month **◀ / ▶** controls and auto-advance to the next month when check-in is within the last week of a month (so check-out can be picked across month boundaries).
- `users/bookings.php` — manage reservation (last name + reservation ID + **auth2** PIN); lookup form uses **Back** (`hb-checkout-skip`); found booking renders the same confirmation panel as `rooms/payment.php` (room type title without room number, nights, guests, reservation notes, PDF + action buttons). Stay bar shows **Logout** (not **Edit stay**) linking to `auth/logout.php`. Cancelled stays show a **red** confirmation state (`Reservation cancelled`, status badge). Aside includes **Cancellation policy**, **Change booking** (modal with hotel name, directions, website, phone from `hotel_booking_hotels`), and **Cancel Booking** (future and present segments — check-out still in the future; sets the active segment status to `CANCELLED`). `rooms/payment.php` confirmation aside also renders **Cancel Booking** for the session booking (last name + auth2 derived from the booking/customer record).
- `cancellation_policy/` — default HTML policy pages (`1_cancellation_policy.html` … `4_cancellation_policy.html`); URLs configurable per hotel in **Portal Rate Plans** admin module. Each page ends with a **Back** button (`history.go(-1)`), which is located outside the `<main>` element to prevent it from being loaded into the HTML editor when rate plans are updated.
- `auth/login.php`, `register.php`, `logout.php` (`logout.php` clears portal user session and returns to `index.php`)

## 5. Tenant

`hb_public_company_id()` reads `hotel_booking_settings.public_portal_enabled` per company (fallback company 1) for portal chrome and welcome copy on `index.php`. The home hotel grid lists every row with `hotel_booking_hotels.active = 1` (all companies). `hb_load_active_hotel_row()` resolves a hotel by `id` for `rooms.php` and `calendar.php`.

## 6. Admin

Hotel administration lives in ITM modules: `modules/hotel_bookings/`, `modules/hotel_booking_hotels/`, `modules/hotel_booking_rooms/`, etc. (Admin sidebar — Hospitality). Channel partner API: `modules/hotel_booking_api/` — see `docs/HOTEL_BOOKING_DISTRIBUTION.md`.

## 7. Active assets (post-legacy cleanup)

| Area | Files |
|------|--------|
| Bootstrap | `bootstrap.php` |
| Includes | `includes/portal_chrome.php`, `portal_checkout.php`, `portal_room_detail.php` |
| CSS | `css/hotel-booking-modern.css` only |
| JS | `js/hotel-booking-{public,dates,amenity-icons,gallery,select-room,customize,change-booking,confirmation-pdf}.js` |
| Images | `images/amenities/*.svg` (+ `ATTRIBUTION.md`); portal fallbacks `image_2.jpg`, `room-5.jpg`, etc.; uploaded hotel photos in `booking/images/{hotel_id}/hotel_photos/`; room-type photos in `booking/images/{hotel_id}/room_types_photos/` (served as `APPURL/images/{hotel_id}/…`) |

## 8. Photo storage and galleries

### Disk layout (per hotel)

| Folder | Admin module | Portal usage |
|--------|----------------|--------------|
| `booking/images/{hotel_id}/hotel_photos/` | `modules/hotel_booking_hotels/` | Hotel cards on `index.php`, hotel sidebar on `rooms.php` |
| `booking/images/{hotel_id}/room_types_photos/` | `modules/booking_rooms_types/` | Room-type cards and modals on `rooms.php` (shared by every physical room of that type) |
| `booking/images/{hotel_id}/room_photos/` | `modules/hotel_booking_room_photos/` | Optional per-room overrides (not used on `rooms.php` type cards) |

Room-type uploads from **Room Types** are mirrored into every active hotel folder for the tenant (`itm_hotel_booking_photo_storage_abs_dirs_for_scope()`). Portal URLs always use the **current** `hotel_id` from the page query string.

### Gallery UI

- Shared gallery: `js/hotel-booking-gallery.js` — `HB_galleryMarkup()`, `HB_bindGallery()`, `HB_initGalleries()`.
- **Hotel detail modal** (`index.php` → `hotel-booking-public.js`): prev/next arrows + `1 / N` counter overlay on all hotel photos.
- **Room cards and detail modal** (`rooms.php`, `rooms/customize.php` → `portal_room_detail.php`): `hb_portal_room_type_photo_urls()` + `hb_portal_render_image_gallery()` (detail modal uses `hb_portal_gallery_html()` wrapper); arrows hidden when only one image (`hb-gallery-wrap--single`).
- Counter format uses spaces: `1 / 12`. Arrow controls use dark translucent squares (see `css/hotel-booking-modern.css` `.hb-gallery-*`).

### Sample data backfill

`php scripts/seed_hotel_booking_sample_photos.php --apply` — copies seed JPEGs into `booking/images/1/hotel_photos/` and `room_types_photos/` and upserts `hotel_booking_hotel_photos` / `booking_rooms_type_photos` rows.

## 9. Portal step pricing (no hardcoded checkout amounts)

Per-hotel portal math is **not** hardcoded in `booking/*.php` or `booking/js/*.js`. Values load from `hotel_booking_hotels` via `itm_hotel_booking_portal_hotel_pricing()`:

| Column | Used for |
|--------|----------|
| `portal_breakfast_adult_price_per_night` | Breakfast rate plan add-on (adults) |
| `portal_breakfast_child_price_per_night` | Breakfast rate plan add-on (children); Step 2 info banner |
| `portal_child_nightly_supplement` | Extra per child on nightly room quote |
| `portal_extra_adult_supplement_percent` | % of base rate per adult above 2 per room |
| `portal_pet_daily_fee` | Pet checkbox add-on per night (Step 2) |

**Admin:** [Portal Rate Plans](http://localhost/it-management/modules/hotel_booking_portal_rate_plans/index.php) — **Portal step pricing** form (per selected hotel). Schema defaults match `itm_hotel_booking_portal_pricing_defaults()` when columns are unset.

**Room type calendar:** [Room Type Calendar](http://localhost/it-management/modules/hotel_booking_room_type_calendar/index.php) — date-range BAR overrides and stop-sell blocks per room type. Portal uses `itm_hotel_booking_resolve_room_type_nightly_bar()` for card/check-in display and `itm_hotel_booking_compute_stay_payment_dated_rates()` for multi-night checkout when `room_type_id` is on the checkout draft. Availability: `itm_hotel_booking_room_unavailable_for_stay()` (bookings + OOO/OOS + HSK maintenance + type blocks). Migration: `db/migrations/hotel_booking_room_type_calendar.sql`.

**Tourist tax** remains company-level on `hotel_booking_settings.tourist_tax_per_person_per_night` ([Hotel Booking Settings](http://localhost/it-management/modules/hotel_booking_settings/index.php)).

**JS:** `rooms.php` passes `portalPricing` + `pricingDefaults` on `window.HB_SELECT_ROOM` for live occupancy/rate recalculation (`hotel-booking-select-room.js`).

**Migration (existing DBs):** `db/migrations/hotel_booking_portal_hotel_pricing.sql` (destructive `hotel_booking_hotels` replace — back up before apply).

## 10. Stay date format (31/Aug/2026)

Hospitality stay dates in the portal and admin booking flows use **`itm_format_hotel_date_display()`** / **`itm_parse_date_input()`** (`d/M/Y`, e.g. `31/Aug/2026`, `01/Oct/2026`). Editable fields use **`itm_render_hotel_date_input()`** + **`js/hotel-date-input.js`** (empty placeholder; value shows the formatted date). Loaded on `index.php` (Select Dates modal), `rooms/room-single.php` (step 4), and admin `modules/hotel_bookings/` forms. MySQL storage stays `Y-m-d`.

Removed legacy Colorlib template tree: `about.php`, `contact.php`, `services.php`, `404.php`, `config/config.php` (PDO), `includes/header.php` / `footer.php`, vendored `scss/`, `css/style.css`, jQuery/Bootstrap JS stack, `fonts/`, and the entire `admin-panel/` folder.
