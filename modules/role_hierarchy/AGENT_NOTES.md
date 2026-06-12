# AGENT_NOTES.md - Role Hierarchy

## 1. Module Purpose
Defines the hierarchical relationship between different user roles for inheritance and permissions.

## 2. Key Tables
- **role_hierarchy** — mapping of parent-child role relationships.

## 3. Required Relationships
- **role_hierarchy** → depends on **companies**.
- **role_hierarchy** → depends on **user_roles**.

## 12. Module Owner Notes (Optional)
Determines how permissions flow between roles.
