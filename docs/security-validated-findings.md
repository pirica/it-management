# Validated security findings

Scan commit: `e42d65df1cd75f41085f26447ef15b230eb4b23f`  
Detected (Pacific): `2026-08-16T16:49:00-07:00`

Automated memory: [`it-management---flagged-vulnerabilities.json`](../it-management---flagged-vulnerabilities.json)

---

## Medium — cross-tenant profile photo read via Explorer `file.php` (remediated)

| Field | Detail |
|-------|--------|
| **Location** | `modules/explorer/file.php` |
| **Severity** | Medium |
| **Status** | Fixed — `emp_profile_photo_request_allowed_for_employee()` gates profile paths with `itm_employee_has_company_access()`. |
| **Attacker** | Any authenticated employee (including non-admin users scoped to a different `company_id`). |
| **Controlled input** | `GET path` such as `Private/Admin_1/profile/Admin_1.png`. |
| **Impact** | Cross-tenant read of employee profile images under `files/{company_id}/Private/…/profile/`. |
| **Regression** | [verify_user_config_profile.php?run=1](http://localhost/it-management/scripts/verify_user_config_profile.php?run=1) (cross-tenant deny) and [verify_explorer_profile_photo_acl.php?run=1](http://localhost/it-management/scripts/verify_explorer_profile_photo_acl.php?run=1) (same-tenant allow + cross-tenant deny). **Admin session** for browser runs. |

Other reviewed surfaces on the original scan passed existing regression scripts. No additional new medium-or-higher issues met the reporting bar.
