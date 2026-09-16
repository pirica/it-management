# AGENT_NOTES.md - Switch Port Icons

## 1. Module Purpose
PNG icons for switch port tiles in Equipment / IDF UIs (RJ45 and SFP, Unknown vs active states).

## 4. Business Rules (Critical for Agents)
- Mandatory mapping in `AGENTS.md` § Switch Port Manager icon mapping — Unknown uses `*_Unknown.png` variants.
- Filenames: `rj45_38x31.png`, `rj45_38x31_Unknown.png`, `sfp_38x38.png`, `sfp_38x38_Unknown.png`.

## 12. Module Owner Notes (Optional)
Equipment index refreshes icons client-side after status save — keep dimensions consistent when replacing files.
