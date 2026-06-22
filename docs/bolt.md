# ⚡ BOLT’S JOURNAL (PHP Edition)

## 22-06-2026 - Optimized Equipment Module Listing
**Learning:** The equipment index page was executing a company-wide query to fetch all switches and a query for all location types on every request, even when the Switch Port Manager (SPM) UI component was not being displayed. By gating these queries behind the `$showSwitchPortManager` flag and deriving visible switch IDs from the current page's results, we significantly reduced database load.
**Action:** Always gate component-specific queries behind their respective visibility flags. Avoid company-wide "fetch all" queries if the data is only needed for a subset of the UI that is not always active.

**Measured Impact:**
- Baseline: 5 queries, ~164ms.
- Optimized: 3 queries, ~39ms.
- Result: 2 redundant queries removed per standard list request.
