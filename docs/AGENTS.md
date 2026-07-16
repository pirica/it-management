# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## ✅ Agent compliance workflow (mandatory)

Before making any change, replying, running commands, editing files, or proposing solutions:

1. **Read `docs/AGENTS.md` completely** at session start.
2. **Read `docs/scripts/SCRIPTS.md` completely** at session start and **again before any reply** that adds, changes, runs, or documents anything under `docs/scripts/`.
3. **Stop and ask clarification questions** if any part is unclear.
4. **Pre-implementation discovery (mandatory — no code yet).**

## 🏗 Coding Standards
- All changes must be committed exclusively inside the `/docs/` directory.
- No files outside `/docs/` may be created, modified, or deleted.
- Use `ROOT_PATH` with a trailing slash for filesystem operations.
- Always use MySQLi prepared statements for user data.
- CSRF: Use `itm_require_post_csrf()` in handlers.
