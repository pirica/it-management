# AGENT_NOTES.md - CSS

## 1. Module Purpose
Contains the global application stylesheet for the IT Management system.

## 7. File Structure
- **`styles.css`** — single canonical stylesheet loaded from `includes/header.php` on standard module pages. Covers layout (sidebar, header, content), forms, tables, badges, switch-port manager, floor-plan gallery, and responsive breakpoints.
- **Module inline `<style>` blocks** — some modules (calendar, todo, explorer, system_status, rack planner, etc.) add page-specific CSS in their entry PHP files. Prefer shared utilities in `styles.css` for patterns reused across modules.

## 8. Responsive design (mandatory)

### Breakpoint variables (`:root`)
| Token | Value | Typical use |
|-------|-------|-------------|
| `--bp-mobile` | 480px | Auth cards, full-width buttons |
| `--bp-tablet` | 768px | Sidebar off-canvas, split-pane stack, table scroll |
| `--bp-laptop` | 1024px | Tablet landscape / narrow laptop |
| `--bp-desktop-lg` | 1440px | Large desktop padding |

### Global rules in `styles.css`
- **≤768px:** off-canvas sidebar, reduced content padding, list tables `min-width: 640px` inside `.content` (horizontal scroll on parent cards), flex toolbar overrides for legacy inline forms.
- **≤480px:** smaller body font, full-width header buttons, tighter auth/company picker padding (auth pages use inline CSS with matching `@media`).
- **769–1024px:** narrower sidebar (`230px`), table `min-width: 700px`.
- **≥1440px:** increased content/card padding.

### Shared utility classes (reuse in modules)
| Class | Purpose |
|-------|---------|
| `.itm-split-layout` / `.itm-split-sidebar` / `.itm-split-main` | Dual-pane layouts; stack below 768px |
| `.itm-empty-state-lg` | Large empty states; reduced padding on mobile |
| `.itm-responsive-table-wrap`, `.audit-table-wrap` | Horizontal scroll for wide tables |
| `.itm-page-toolbar` | Flex toolbar with wrap |
| `.itm-canvas-scroll` | Overflow scroll for canvas/visualizer shells |
| `.itm-nowrap-column` | Table nowrap on desktop; wraps on mobile (768px) |
| `.itm-user-config-sidebar-link` | `user-config.php` Personalized Sidebar labels and Recent Activity `{table_name}` links — `color: inherit` + no underline (hover underline only) |

### Module-specific responsive CSS
Modules with substantial inline CSS must include their own `@media` rules when layout is not covered by global utilities (examples: `modules/calendar/`, `modules/todo/`, `modules/explorer/`, `modules/ops_report/`, `modules/org_chart/`, `modules/rack_planner/`). Canvas tools (org chart, floor designer, rack visualizer) rely on scroll/zoom rather than reflowing fixed artboards.

## 10. Common Pitfalls
- Overwriting global variables without checking their impact on other modules. [Cursor-Fixed] — Floor designer scopes `--designer-width` / `--designer-height` on `.designer-wrapper`, not `:root`.
- Fixed-width sidebars (`280px`, `320px`) without a mobile stack breakpoint. [Cursor-Fixed] — `user-config.php` `.layout-2col` stacks to one column at `max-width: 768px`; prefer `.itm-split-layout` for new dual panes.
- `white-space: nowrap` on table headers/cells without a scroll wrapper or mobile override. [Cursor-Fixed] — Canonical pattern is horizontal scroll via `.content .card { overflow-x: auto }`, `.itm-responsive-table-wrap` / `.audit-table-wrap`, plus opt-in `.itm-nowrap-column` wrapping on mobile. Do **not** add a mobile rule that wraps all `th`/`td` then re-force `nowrap` on `.card th/td` (that undoes wrapping for every CRUD list).
- Duplicating `.audit-table-wrap` in module CSS — define once in `styles.css`. [Cursor-Fixed] — local one-line overrides removed from audit logs / system status inline styles.

## 12. Module Owner Notes (Optional)
The system uses CSS variables (`var(--accent)`, etc.) for theme consistency. Dark mode is toggled via `[data-theme="dark"]` on `document.documentElement`.
