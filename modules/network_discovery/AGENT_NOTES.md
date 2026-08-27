# AGENT_NOTES.md - Network Discovery

## 1. Module Purpose
Scheduled TCP/HTTP network discovery with a staging queue before equipment and CMDB promotion.

## 2. Key Tables
- **network_discovery_profiles** — subnet scope (`subnet_ids_json`), cron schedule, SNMP flag, auto-create policy, chunked scan state (`scan_in_progress`, `scan_offset`, `scan_ips_json`).
- **network_discovery_staging** — discovered hosts (`probe_json` holds TCP, HTTP fingerprint, SNMP sysName, subnet match, equipment hints).

## 3. Required Relationships
- Profiles and staging → **companies** (`company_id`).
- Staging → **network_discovery_profiles** (`profile_id`).
- Staging → **equipment** (`promoted_equipment_id`, optional).
- Profile subnets reference **ip_subnets** ids in JSON (not FK).

## 4. Business Rules (Critical for Agents)
- **Chunked scans:** cron runs one batch (`itm_network_discovery_batch_size()` = 10) per profile per invocation; large subnets use `scan_ips_json` queue on the profile row (not a global job_queue table yet).
- **Auto-create policy:** `review` / `none` land hosts in staging; `equipment` auto-promotes after each discovered host.
- **Promote:** creates **equipment**, calls `itm_cmdb_sync_equipment()`, optional IPAM row via `itm_ipam_network_discovery_import_hosts_batch()`.
- **Link:** sets equipment `ip_address` when empty, CMDB sync, optional IPAM row; marks staging `promoted`.
- **Dismiss:** staging `status = dismissed` (not re-opened on rescans).
- **SNMP:** optional `snmpget` when `snmp_enabled` and PHP snmp extension present; community from `ITM_NETWORK_DISCOVERY_SNMP_COMMUNITY` (default `public`).
- **Profiles:** admin-only create/delete/run-now in UI; technicians use staging promote/link/dismiss via `api.php`.

## 5. UI Behavior Requirements
- **Tabs:** Staging (default) and Profiles on `index.php`; IP Subnets list links to both tabs.
- **API:** `api.php` POST `promote`, `link`, `dismiss` with CSRF + rate limit.
- **NO MIXED** action buttons on staging grid (emoji-only + `title`).

## 6. API Actions
- `modules/network_discovery/api.php` — `promote`, `link`, `dismiss` (session auth).

## 7. File Structure
- `index.php` — profiles + staging UI.
- `api.php` — JSON mutations.

## 8. Multi-Tenant Rules
- Strict `company_id` on all queries.

## 9. Audit Logging Requirements
- `trg_network_discovery_profiles_audit_*` and `trg_network_discovery_staging_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- **PHP timeout:** do not scan full /24 in one request — use cron batch runner.
- **Duplicate IP:** unique `(company_id, profile_id, ip_address)` on staging; equipment match uses `itm_ipam_find_equipment_by_ip_text()`.

## 11. Examples of Safe Code Patterns
```php
$batch = itm_network_discovery_profile_run_batch($conn, $profileId, $employeeId);
```

## 12. Module Owner Notes (Optional)
- Cron: `php scripts/run_network_discovery.php` (schedule every 5–15 minutes).
- Regression: `php scripts/verify_network_discovery.php`.
- Core orchestration: `includes/itm_network_discovery.php`; probes extend `includes/ipam_helpers.php`.
