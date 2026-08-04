# Appointment Scheduling Subsystem

Comprehensive documentation for the employee self-service IT appointments scheduling system, weekly slot grid generation, multi-concurrency lock mechanisms, API actions, and administrative configuration.

---

## 1. Intent & Purpose

The **Appointment** subsystem (`modules/appointments/`) enables employees to schedule visits with the IT support desk. It streamlines support workflow by offering:
- Self-service booking for in-person or remote troubleshooting.
- Configurable appointment visit reasons.
- Real-time weekly slot selection based on tenant business hours and duration rules.
- Multi-concurrency booking locks to prevent double-booking.

---

## 2. System Architecture & Flow

The appointment system functions as a modular, tenant-scoped booking interface with real-time slot generation:

```mermaid
sequenceDiagram
    participant User as Client Browser (UI)
    participant API as appointments/api.php (Backend)
    participant Helper as includes/itm_appointment.php
    participant DB as MySQL Database

    User->>API: GET week_slots (date, reason, timezone)
    API->>Helper: Build week slots for company settings & hours
    Helper->>DB: Query appointments, settings, business hours
    DB-->>Helper: Active settings and scheduled slots
    Helper-->>API: List of bookable slots
    API-->>User: JSON week grid payload
    Note over User: User selects a slot and clicks "Schedule"
    User->>API: POST schedule + CSRF (date, slot times, type)
    Note over API: Lock slot with "booking_lock" unique index
    API->>DB: Insert new appointment row (status = 'scheduled')
    DB-->>API: Success (ID)
    API-->>User: Redirect to view.php?id=ID
```

---

## 3. Database Schema & Tables

The subsystem coordinates several tables to manage business hours, timezone configurations, booking options, and appointments:

| Table | Primary Role | Multi-Tenancy Scoping |
|---|---|---|
| **`appointments`** | Holds booked slots, visit details, assignment status, and the `booking_lock` unique identifier. | `company_id` |
| **`appointment_type`** | Tenant lookup defining core support modalities (such as `in_person` or `remote`). | `company_id` |
| **`appointment_visit_reasons`** | Dropdown choices displayed to the user when booking (must be active and not soft-deleted). | `company_id` |
| **`appointment_settings`** | Global tenant settings specifying timezone, slot duration, bookable window, and check-in buffers. | `company_id` |
| **`appointment_business_hours`** | Configures daily hours, weekend closures, and modality restrictions (e.g. Wednesday remote-only). | `company_id` |

### Key Column Constraints & Schema Highlights

- **`appointments.booking_lock`:** A unique index across `(company_id, booking_lock)` that guarantees no two concurrent bookings can occupy the exact same slot on the same day for a company. On cancel/soft-delete, this string is cleared to release the slot.
- **`appointment_settings.default_appointment_modality`:** Specifies which `appointment_type` to pre-select when rendering multiple booking options.
- **`appointment_business_hours.allowed_types_json`:** JSON field defining specific active types permitted for each day of the week.

---

## 4. Business Rules & Booking Constraints

### A. Real-Time Slot Generation
The slot builder (`itm_appointment_build_week_slots()`) dynamically divides each day's open hours into increments based on `slot_duration_minutes`.
- **Weekend Closures:** Days marked `is_closed = 1` are excluded from slot generation.
- **Modality Restricting:** Weekdays can be restricted (e.g. Wednesday remote-only). The booking engine checks `allows_in_person` and `allows_remote` flags along with `allowed_types_json`.

### B. Validation Constraints
When a user attempts to schedule an appointment, the backend API enforces strict gates:
- **Mandatory Fields:** A visit reason must be chosen, and a slot must be active/selected. If submitted incomplete, client-side alerts prompt the user to make a valid selection.
- **Active Lookup Enforcement:** The visit reason ID must correspond to an active, non-deleted row in `appointment_visit_reasons`.
- **Concurrency Check:** If two sessions submit the same slot simultaneously, the transaction with the first insert commits the `booking_lock`, causing the second transaction to fail gracefully with a unique constraint violation instead of double-booking.

### C. Status & Deletion (Soft-Delete)
- New bookings are inserted with `status = 'scheduled'`.
- Deletion is modeled as a **soft-delete**. The delete POST handler clears the `booking_lock` column, sets `status = 'cancelled'`, and stamps the `deleted_at` and `deleted_by` metadata, releasing the slot back to the bookable pool.

---

## 5. UI Layout & User Experience

The module opts out of standard flattened CRUD tables to deliver a custom self-service interface:

1. **Booking Screen (`index.php`):**
   - **Reason selector:** A clear dropdown prefilled with active visit reasons.
   - **Weekly Slot Modal:** Interactive weekly calendar grid. Users navigate weeks to view open slot times. Selecting a slot renders a read-only slot summary with the selected ISO date and time.
   - **Type Selection Cards:** Custom radio cards corresponding to allowed modalities. Hidden until a slot is confirmed; labels dynamically fetch from `appointment_type.label`.
   - **Settings Shortcut:** A **⚙️ Appointment Settings** icon is visible in the toolbar *only* to administrators (`itm_is_admin()`).
2. **Review Lists (`list_all.php`):**
   - Non-admins and IT staff can view all scheduled company appointments (up to a default limit of 200 rows).
   - If granted edit permissions, inline dropdowns allow operators to change the **Assigned to** employee and toggle the **Confirmed** checkbox in real-time.

---

## 6. API Actions

The booking UI coordinates with the backend via `modules/appointments/api.php`. Rate limiting and an active user session are required.

### `action=week_slots` (GET)
- **Parameters:** `date` (YYYY-MM-DD), `reason_id` (int)
- **Response:** JSON payload of weekday slots, open times, and daily allowed modalities.

### `action=schedule` (POST)
- **Payload:** `csrf_token`, `appointment_date`, `start_time`, `end_time`, `appointment_type_id`, `reason_id`, `notes`
- **Response:** JSON with a success flag and the redirection `view_url` (routing to `view.php?id=ID`).

---

## 7. Related Files & Components

| Path | Primary Role |
|---|---|
| `modules/appointments/index.php` | Main entry point for booking UI, list review, and delete forms. |
| `modules/appointments/api.php` | API endpoint for week-grid slots and schedule submission. |
| `modules/appointments/list_all.php` | Administrative wrapper routing to the all-bookings list view. |
| `modules/appointments/view.php` | Detail view showing assignment and confirmation flags. |
| `includes/itm_appointment.php` | Core backend functions, slot duration builders, and weekday modality maps. |
| `js/appointment.js` | UI logic managing slot selection modals, weekly navigation, and AJAX API posts. |
| `css/appointment.css` | Styles for the week-grid modal and modality radio cards. |

---

## 8. Troubleshooting & Verification

### Common Pitfalls
- **Empty Reasons Dropdown:** If all visit reasons are deleted or disabled, the booking dropdown will appear empty. Ensure active reasons are seeded under `appointment_visit_reasons`.
- **Modality Exclusions:** Wed-only remote rules or custom day exclusions are driven by `appointment_business_hours` configurations. Double-check weekday modality flags if slots fail to render.
- **Unique Booking Failures:** If a slot is cancelled but `booking_lock` is not cleared (NULL), that slot remains blocked permanently. Ensure soft-delete handlers correctly NULL the lock string.

### Automated Verification
To run the automated test suite and verify appointment routing, database triggers, and concurrency gates, execute from the repository root:

```bash
php scripts/verify_appointment.php
```
