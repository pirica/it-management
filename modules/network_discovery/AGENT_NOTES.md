# AGENT_NOTES.md - Network Discovery

## 1. Module Purpose
Scheduled TCP/HTTP network discovery with a staging queue before equipment and CMDB promotion.

## 2. Key Tables
- **network_discovery_profiles** — subnet scope (`subnet_ids_json`), cron schedule, SNMP flag, auto-create policy, `last_run_at`.
- **network_discovery_staging** — discovered hosts (`probe_json` holds TCP, HTTP fingerprint, SNMP sysName, subnet match, equipment hints).
- **background_jobs** — chunked scan queue (`job_type = network_discovery_scan`; payload holds `profile_id`, `ips`, SNMP/policy flags).

## 3. Required Relationships
- Profiles and staging → **companies** (`company_id`).
- Staging → **network_discovery_profiles** (`profile_id`).
- Staging → **equipment** (`promoted_equipment_id`, optional).
- Profile subnets reference **ip_subnets** ids in JSON (not FK).
- Scan jobs → **background_jobs** keyed by `payload_json.profile_id` (one active `pending`/`running` job per profile).

## 4. Business Rules (Critical for Agents)
- **Chunked scans:** `php scripts/run_background_jobs.php` processes ~10 IPs per job batch; `php scripts/run_network_discovery.php` enqueues due profiles (schedule every 5–15 min).
- **Auto-create policy:** `review` / `none` land hosts in staging; `equipment` auto-promotes after each discovered host.
- **Promote:** creates **equipment**, calls `itm_cmdb_sync_equipment()`, optional IPAM row via `itm_ipam_network_discovery_import_hosts_batch()`.
- **Link:** sets equipment `ip_address` when empty, CMDB sync, optional IPAM row; marks staging `promoted`.
- **Dismiss:** staging `status = dismissed` (not re-opened on rescans).
- **SNMP:** optional `snmpget` when `snmp_enabled` and PHP snmp extension present; community from `ITM_NETWORK_DISCOVERY_SNMP_COMMUNITY` (default `public`).
- **Profiles:** admin-only create/delete/run-now in UI; technicians use staging promote/link/dismiss via `api.php`.

## 5. UI Behavior Requirements
- **Tabs:** Staging (default) and Profiles on `index.php` (shared partials under `modules/ip_subnets/includes/partials/`).
- **IP Subnets:** `index.php?tab=profiles|staging` embeds the same partials; module `index.php` remains a standalone entry.
- **API:** `api.php` POST `promote`, `link`, `dismiss` with CSRF + rate limit.
- **NO MIXED** action buttons on staging grid (emoji-only + `title`).

## 6. API Actions
- `modules/network_discovery/api.php` — `promote`, `link`, `dismiss` (session auth).

## 7. File Structure
- `index.php` — thin shell; requires shared partials from `modules/ip_subnets/includes/`.
- `api.php` — JSON mutations.

## 8. Multi-Tenant Rules
- Strict `company_id` on all queries.

## 9. Audit Logging Requirements
- `trg_network_discovery_profiles_audit_*`, `trg_network_discovery_staging_audit_*`, `trg_background_jobs_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- **PHP timeout:** do not scan full /24 in one request — use background job worker cron.
- **Duplicate IP:** unique `(company_id, profile_id, ip_address)` on staging; equipment match uses `itm_ipam_find_equipment_by_ip_text()`.

## 11. Examples of Safe Code Patterns
```php
$batch = itm_network_discovery_profile_run_batch($conn, $profileId, $employeeId);
itm_network_discovery_enqueue_profile_scan($conn, $profileId, $employeeId);
```

## 12. Module Owner Notes (Optional)
- Enqueue cron: `php scripts/run_network_discovery.php`; worker: `php scripts/run_background_jobs.php`.
- Regression: `php scripts/verify_network_discovery.php`, `php scripts/verify_background_jobs.php`.
- Core orchestration: `includes/itm_network_discovery.php`, `includes/itm_background_jobs.php`; probes extend `includes/ipam_helpers.php`.
