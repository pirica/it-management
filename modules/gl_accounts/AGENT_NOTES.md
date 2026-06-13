# AGENT_NOTES.md - GL Accounts

## 1. Module Purpose
Manages General Ledger (GL) accounts for financial tracking.

## 2. Key Tables
- **gl_accounts** — stores GL account names and codes.

## 3. Required Relationships
- **gl_accounts** → depends on **companies**.
- **gl_accounts** → referenced by **budgets**, **expenses**, **forecasts**.

## 4. Business Rules (Critical for Agents)
- **Unique Name/Code**: Must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 12. Module Owner Notes (Optional)
Standard financial accounting codes.
