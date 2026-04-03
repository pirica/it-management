# Code Review Report (2026-04-03)

## Scope
- Reviewed authentication/configuration entry points and debugging utilities:
  - `config/config.php`
  - `login.php`
  - `debug.php`

## Findings

### 1) Hardcoded credentials and secrets in source (High)
**Evidence**
- Database credentials are committed in `config/config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
- API key constant placeholder is also configured in code (`MAILERLITE_API_KEY`).

**Risk**
- Increases chance of credential leakage via source access, backups, logs, or accidental publication.
- Makes secret rotation and environment separation harder.

**Recommendation**
- Move secrets to environment variables or external secret manager.
- Fail fast if required secrets are missing.
- Rotate DB credentials if this repo has been shared outside trusted boundaries.

---

### 2) `BASE_URL` trust on `HTTP_HOST` enables host-header poisoning (High)
**Evidence**
- `BASE_URL` is built directly from `$_SERVER['HTTP_HOST']` in `config/config.php`.

**Risk**
- Attackers can manipulate `Host` header and influence generated absolute links and redirects.
- May facilitate phishing links, cache poisoning, or open-redirect-like behavior in deployments behind proxies.

**Recommendation**
- Replace dynamic host construction with a canonical app URL from environment/config.
- If dynamic behavior is required, validate host against an allowlist before use.

---

### 3) Public debug endpoint leaks internal system details (High)
**Evidence**
- `debug.php` executes without explicit authentication gate.
- It outputs DB connectivity status, full table list, PHP version, extension status, and writable directory checks.

**Risk**
- Discloses sensitive reconnaissance data to unauthenticated users.
- Helps attackers fingerprint environment and target known weaknesses.

**Recommendation**
- Remove `debug.php` from production builds, or enforce strict authentication/authorization.
- Guard behind environment checks (`APP_ENV === 'development'`) and server-level access controls.

---

### 4) Login allows raw password equality bypass (Medium)
**Evidence**
- In `login.php`, password verification accepts either `password_verify(...)` **or** direct `hash_equals($storedPassword, $password)`.

**Risk**
- If stored passwords are ever plain text or weakly transformed, direct comparison allows authentication without modern hashing safeguards.
- Can weaken migration posture and policy enforcement.

**Recommendation**
- Remove plaintext fallback and require `password_hash` + `password_verify` only.
- If legacy migration is needed, perform one-time migration-on-login: verify legacy hash format, then rehash to modern format and store.

---

## Suggested Priority
1. Disable/protect `debug.php` immediately.
2. Externalize secrets and rotate credentials.
3. Fix host handling for canonical `BASE_URL`.
4. Remove plaintext login fallback and migrate legacy passwords safely.
