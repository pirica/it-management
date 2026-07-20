# AGENT_NOTES.md - js/vendor

## 1. Module Purpose
Third-party JavaScript libraries shipped vendored in-repo (no npm build). Examples: SheetJS (`xlsx.full.min.js`), other minified dependencies loaded from `includes/header.php` or module pages.

## 4. Business Rules (Critical for Agents)
- No Composer/npm — vendor files are committed directly.
- Upgrading a vendor file requires smoke-testing export/import and any module that loads the script.

## 10. Common Pitfalls
- Do not replace with CDN links without an explicit request — offline/Laragon installs rely on local copies. [Cursor-Valid]
