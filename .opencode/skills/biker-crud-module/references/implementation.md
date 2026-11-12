# Implementation Reference

Contents: Migration · Model · Validation · Authorization · Routes · Controller · Index/search/filter · Bulk delete · Blade UI · Activity logging

Always defer to how the closest existing biker module does each of these.

## Migration

Follow existing biker migration conventions. Decide column types, lengths, precision/scale, nullability, defaults, keys, indexes, unique and composite constraints, timestamps.

- Use `decimal`, never floating-point, for money.
- Index only what is used for searching, filtering, sorting, joins, and foreign keys. No unnecessary or redundant indexes.
- Choose foreign-key deletion behavior from the real business rule. Do not use `cascade` just because it is convenient.
- No destructive behavior, and no renaming or dropping existing columns, without explicit approval and a stated impact.
- Preserve production data.

## Model

Use biker conventions. Include where appropriate: `$fillable` or `$guarded`, `$casts`, relationships, scopes, deletes using spatie/laravel-deleted-models. Add accessors/mutators only when genuinely useful.

Infer relationships from the verified database structure, not from naming alone. Use explicit keys when needed. Keep the model simple.

## Validation

Use the established approach. Server-side validation is authoritative. Cover: required, types, max lengths, numeric ranges, dates, foreign keys (`exists`), unique and update-specific unique (ignore current record), uploaded files, business rules. Never allow mass assignment of protected fields.

## Authorization

For bulk operations, authorize every affected record under the app's access rules. Prevent IDOR, privilege escalation, unauthorized record access, exports, and bulk deletion. Do not invent a new permission architecture.

## Routes

Named routes following existing organization. Use auth middleware, route model binding. Each sensitive endpoint is protected on its own.

## Controller

Methods: `index`, `create`, `store`, `edit`, `update`, `bin`, `restore`. Keep it thin; use the existing service/action pattern for reusable business logic. Use dependency injection, route model binding, authorization, validation, transactions where required, appropriate redirects and flash messages, and the existing error-handling conventions.

## Index, search, filtering

- Use **Livewire Powergrid** for Filters, Search, Pagination, Export, Sort, Checkbox, Bulk Delete
- Keep the Server-side search box, preserved query parameters.
- Search only relevant fields. Eager load what the view needs. Never load the whole table into PHP to filter.
- Group `OR` search conditions inside a closure so they cannot bypass other filters.

Included by default.

- Checkbox per row and a select-all checkbox.
- Validate submitted IDs and reject malformed ones.
- Authorize every selected record and respect the user's data scope.
- Prevent deletion of protected or system-critical records.
- Handle foreign-key restrictions correctly.
- Use a transaction where appropriate.
- Confirm before deleting.
- Report an accurate result. Never silently skip records the user cannot delete; say which were not deleted and why.

## Blade + Bootstrap UI

Reuse the existing layout, navbar, cards, tables, buttons, modals, alerts, form controls, pagination, icons, and JS patterns. Create index, create, edit, bin.

Create and edit forms use **Select2** for dropdown fields.

Index page includes: search, filters, add button, edit action, row checkboxes, select-all, bulk delete, export button, pagination, success/error messages, empty-state message. Actions are permission-aware. Use accessible labels and escape user-controlled output (default Blade `{{ }}`).

## Activity logging

Use the existing Spatie Activitylog setup and conventions. Log view, create, update, delete, bulk delete, export, with useful non-sensitive metadata (user, action, model, record ID, number of affected records).

- Never log passwords, tokens, API keys, secrets, or unnecessary personal data.
- Logs must reflect successful, committed operations. Log after the transaction commits, and do not write a success log for a failed operation.
- Avoid duplicate logs from multiple layers (for example model events plus a manual log) unless the existing architecture deliberately does that.
