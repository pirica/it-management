# Vault Key Operations

Employee-scoped encryption for Passwords, Notes, Bookmarks, Private Contacts, Todo, Events, and the Explorer **Private** folder. Entry point: **Profile** (`user-config.php`) → **Vault Security** (`#vault-security`).

| Layer | Detail |
|-------|--------|
| **Authentication hash** | `employees.vault_key_hash` — bcrypt of the plaintext master key (`password_hash` / `password_verify`). |
| **Session derivation key** | `$_SESSION['vault_key']` — `hash('sha256', $plaintext_master_key)` used by `itm_encrypt()` / `itm_decrypt()`. |
| **Re-encryption** | `includes/itm_vault_master_key.php` helpers during master-key change inside a DB transaction. |

---

## 1. Workflows

### A. Create Vault Key

First-time setup when `employees.vault_key_hash` is empty.

1. **Entry:** Profile → **Vault Security** (`user-config.php#vault-security`).
2. **Inputs:**
   - **System Password** — verified with `password_verify()` against `employees.password`.
   - **New Master Key** and **Confirm Master Key** — must match.
3. **Optional — Generate Key:** click **🔑** (**Generate Secure High-Entropy Key**). Client-side JavaScript uses `window.crypto.getRandomValues()` to build a 24-character key, fills the New/Confirm fields, and opens the **one-time display** overlay (see [§1.D](#d-secure-key-generation-one-time-display)).
4. **Processing:**
   - No re-encryption pipeline (no existing ciphertext).
   - `password_hash($new_vk, PASSWORD_DEFAULT)` → `employees.vault_key_hash`.
   - `$_SESSION['vault_key'] = hash('sha256', $new_vk)` unlocks the vault for the current session.
5. **Notification email (no secrets):** after a successful commit, if the employee has a valid `work_email` (fallback `personal_email`), `itm_send_email()` sends a **Vault Key Created** message via the tenant default SMTP profile. The body contains no plaintext key; delivery is logged in **Email Management → Send Logs** (`emails` table). See [§3 Email integration](#3-email-integration).

### B. Unlock Vault

When `$_SESSION['vault_key']` is empty, secured modules show a **Vault Locked** gate:

- Passwords, Bookmarks, Notes, Private Contacts, Todo, Events, Explorer **Private/{username}_{employee_id}/**.

**Flow:**

1. User submits the master key on the lock screen.
2. `password_verify($master_key, $user_data['vault_key_hash'])`.
3. On success: `$_SESSION['vault_key'] = hash('sha256', (string)$master_key)`.
4. Encrypted rows are fetched and decrypted with `itm_decrypt($ciphertext, $_SESSION['vault_key'])`.

### C. Change Master Key

Updates an existing vault key and re-encrypts all private data atomically.

1. **Entry:** Profile → **Vault Security**.
2. **Inputs:**
   - **System Password**
   - **Current Master Key** (when `vault_key_hash` already exists)
   - **New Master Key** and **Confirm Master Key**
3. **Optional — Generate Key:** same client-side generator and one-time display as create (§1.D).
4. **Transaction (`mysqli_begin_transaction`):**
   - Re-encryption helpers (old SHA-256 session key → new SHA-256 session key):
     - `itm_vault_reencrypt_password_entries()` — `password_entries`
     - `itm_vault_reencrypt_bookmark_urls()` — `bookmarks`, `bookmark_folders`
     - `itm_vault_reencrypt_notes()` — `notes`, `note_labels`
     - `itm_vault_reencrypt_events()` — `events`
     - `itm_vault_reencrypt_private_contacts()` — `private_contacts`
     - `itm_vault_reencrypt_todo()` — `todo`
   - Update `employees.vault_key_hash` with bcrypt hash of the new key.
   - **Commit** on success; **rollback** on any exception (data stays on the old key).
5. **Session sync:** `$_SESSION['vault_key'] = hash('sha256', $new_vk)`.
6. **Notification email (no secrets):** after commit, **Vault Key Updated** via `itm_send_email()` (same transport/logging as create). No plaintext key in the message.

### D. Secure key generation (one-time display)

UI helpers in `user-config.php` (client-side only; the server never receives the key until form submit):

| Control | Behaviour |
|---------|-----------|
| **🔑** (header) | Generates a 24-character key with unbiased `crypto.getRandomValues()` rejection sampling, copies it into **New** / **Confirm**, shows the overlay. Visible control is emoji-only (`title` carries the phrase). |
| **One-time overlay** | Read-only field + **🗐** copy; user must save the key externally. |
| **➡️** (overlay dismiss) | Clears the overlay field and masks form inputs as `type="password"` again. Values remain in the form until save or navigation — users must submit **💾** to persist. |
| **👁️** (per field) | Toggles visibility on Current / New / Confirm master-key inputs. |

> **Note:** Dismissing the overlay does not erase key material from the DOM form fields; it only hides the dedicated display field. Treat the overlay as a convenience copy step, not a cryptographic wipe.

---

## 2. Forgotten Vault Key

Zero-knowledge design: the plaintext master key is **never** stored server-side.

### A. Complete cryptographic lockout

- Database holds ciphertext plus `vault_key_hash` (bcrypt for verification only).
- Without the correct plaintext key, `itm_decrypt()` fails or returns unusable output.
- Administrators **cannot** recover vault contents.

### B. Impacted data (per `employee_id`)

| Module | Tables / paths |
|--------|----------------|
| Passwords | `password_entries` |
| Notes | `notes`, `note_labels` |
| Bookmarks | `bookmarks`, `bookmark_folders` |
| Private Contacts | `private_contacts` |
| Todo | `todo` (private tasks) |
| Events | `events` (private / unshared) |
| Explorer | `files/{company_id}/Private/{username}_{employee_id}/` |

### C. Administrator limits

- Login password can be reset; vault data cannot.
- Forcing a new `vault_key_hash` in the database without the old key leaves historical ciphertext encrypted with the **forgotten** key — permanently unreadable.

### D. Remediation path

1. Remove or purge existing encrypted rows for the employee (and clear the Explorer private folder).
2. Create a **new** master key via Vault Security.
3. Rebuild passwords, notes, bookmarks, contacts, and private files from scratch.

---

## 3. Email integration

Vault notifications use the shared transactional mail stack — **not** a direct HTTP call to `modules/emails/`.

```php
require_once ROOT_PATH . 'includes/itm_email.php';
itm_send_email($emailTarget, $emailSubject, $emailHtml, $home_company_id, [
    'email_template' => [
        'subtitle' => $emailSubtitle,
        'button_text' => 'Go to Dashboard',
        'button_url' => BASE_URL . 'dashboard.php',
    ],
]);
```

| Aspect | Detail |
|--------|--------|
| **Helper** | `includes/itm_email.php` → `itm_send_email()` |
| **SMTP source** | Tenant default profile (`email_smtp_configurations.is_default = 1`) for `$home_company_id` |
| **Admin UI** | **Email Management** (`modules/emails/`) — Send Logs, SMTP profiles, alert rules |
| **Logging** | Each attempt written to `emails` (private-data exempt from `audit_logs` triggers) |
| **Recipient** | `work_email`, else `personal_email`; skipped when missing or invalid |
| **Security** | Notification only — **no** master key, reset link, or other secret in subject/body |
| **Failure handling** | `itm_send_email()` returns `bool`; vault commit is **not** rolled back if mail fails |

Same pattern as forgot-password, registration welcome mail, and employee onboarding approvals.

---

## 4. Related files

| Path | Role |
|------|------|
| `user-config.php` | Vault Security form, POST `vault_key_change`, key generator JS, one-time overlay |
| `includes/itm_vault_master_key.php` | Re-encryption helpers during key change |
| `includes/itm_email.php` | `itm_send_email()` transport + transactional template |
| `modules/emails/` | Operator UI for send logs and SMTP configuration |
| `modules/passwords/`, `modules/notes/`, … | Vault-gated modules consuming `$_SESSION['vault_key']` |

Regression when changing mail behaviour: `php scripts/verify_emails_module.php`.
