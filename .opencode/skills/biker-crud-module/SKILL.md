---
name: biker-crud-module
description: Build a new CRUD module in the existing biker Laravel 13 production app (Blade, Bootstrap, Spatie Activitylog, Maatwebsite Excel, Select2, PHPUnit). Use this whenever the user provides a table name plus column definitions (optionally with relationships or business rules) and wants a new module, CRUD, screen, or "add a table for X" in biker — even if they never say "skill" or "CRUD". Delivers migration, model, routes, controller, Blade views, search/filter, bulk delete, Excel export, activity logging, and tests, only after the user approves an implementation plan. Do NOT use for bug fixes, changes to existing modules, or non-biker projects.
---

# biker CRUD Module

Build one new module in **biker**, an existing production Laravel 13 / PHP 8.5 app. The input is a table name and columns, plus optional descriptions, relationships, and business rules.

You act as a senior Laravel architect, database designer, security engineer, and QA engineer in one. This is not a greenfield project: **the existing biker codebase is the source of truth, in particular User module.** Consistency with it matters more than theoretical architectural purity, because the people maintaining this app need new modules to look and behave like the old ones.

## The two-gate workflow

Work happens in two stages with a hard stop between them. The user is going to review the plan before any code exists, because migrations on a production app are expensive to undo.

1. **Inspect and plan** (read-only). Do not create or modify application code.
2. **Stop and wait** for the user's explicit approval. Silence is not approval.
3. **Implement and verify**, only after approval.

If implementation reveals a material issue (database structure, existing behavior, authorization, data integrity, architecture, production safety), stop and ask again. Use judgment for minor details that do not change the approved design.

## Stage 1: Inspect, analyze, plan

### Inspect first

Find the closest existing module and use it as the primary reference. Read:

- Related models, migrations, factories, seeders
- Controllers, routes, policies
- Blade views (index/create/edit/show, layouts, modals, pagination, flash messages)
- Existing exports and activity-log usage
- Relevant tests and test helpers
- `composer.json` and installed package versions when package behavior matters

Use Laravel Boost MCP and any project skills if available. Prefer inspecting over guessing. If existing modules contradict each other, pick the best pattern and say which you picked and why.

Do not add packages, introduce new architectural patterns, or refactor unrelated code. Do not introduce React, Vue, or Tailwind.

### Analyze

Work out the structure, relationships, types, nullability, defaults, keys, indexes, unique constraints, searchable/filterable/sortable fields, validation, business rules, authorization, logging, export, bulk-delete behavior, security and performance risks, and required tests.

Separate your findings into three labeled groups:

- **Confirmed**: verified in the codebase or stated by the user
- **Assumptions**: inferred but unverified
- **Questions**: need the user's decision (ask only the essential ones)

### Present the plan, then STOP

Use the structure in `references/plan-template.md` (sections A to K). Use tables where they help. Do not dump code during planning. End by asking for approval and make no code changes.

## Stage 2: Implement (after approval)

Modify the real project files; do not just show hypothetical code. Follow the approved plan. Read the matching reference file before each area:

| Area | Read |
|---|---|
| Migration, model, validation, routes, controller, index/search, bulk delete, Blade UI | `references/implementation.md` |
| Excel export | `references/export.md` |
| Activity logging | `references/implementation.md` (Activity logging section) |
| Tests | `references/testing.md` |
| Security and performance review | `references/security-performance.md` |
| Final verification and report | `references/final-report.md` |

### Rules that apply throughout

- **Reuse before creating.** Match the closest module's controller structure, validation approach (Form Requests if that is the convention), logging, layouts, modals, pagination, flash messages, and test patterns.
- **Authorize on the server, always.** Hiding a Blade button is not security. Every sensitive endpoint (including export, bulk delete) is independently protected by auth middleware.
- **Select2 for dropdowns** on the create and edit forms.
- **Production safety.** Never delete production data, reset the database, run destructive migrations, change production config, deploy, or run irreversible commands without explicit approval. Call out any migration risk to existing data before implementing.
- **Be honest about verification.** Never claim a test or command passed unless you ran it and saw the result. Report failures plainly.

## Response style

Concise and technically precise. Tables in the plan. No code dumps during planning.
