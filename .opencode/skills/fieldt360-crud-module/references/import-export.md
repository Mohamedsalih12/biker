# Excel Import / Export Reference

Contents: Export · Export button behavior · Import · Import modal

## Export

Use the Livewire Powergrid **installed** version (check `composer.json` / vendor). Follow the project's existing export classes if any exist.

The export must:

- Respect current search and filter conditions.
- Respect authorization and the user's accessible data scope.
- Include headings and appropriate formatting.
- Exclude sensitive or internal fields unless explicitly required.
- Sanitize values against spreadsheet formula injection (cells starting with `=`, `+`, `-`, `@`, tab, or carriage return).
- Avoid unnecessary memory use. For large datasets do not blindly use `FromCollection`; prefer `FromQuery` with chunking, or a queued export, and recommend that in the plan when relevant.
- Be authorized independently at the endpoint.
- Log the export activity.

### Export button behavior

Prevent accidental double clicks, show a loading/processing state, and do not leave the button permanently disabled if the request fails (re-enable on error or after the download starts).

## Import

Use the existing Maatwebsite Excel implementation and the APIs supported by the **installed** version (check `composer.json` / vendor). Follow the project's existing import classes if any exist.

Included by default. Validate:

- File type and file size
- Required headings and column structure
- Required fields, data types, dates, numeric values
- Foreign keys, uniqueness, business rules

Handle empty rows, duplicate rows, invalid rows, missing values, invalid relationships, invalid dates, and invalid numbers. Reuse the normal application validation and business rules wherever practical instead of duplicating them.

**Imports are all-or-nothing by default** (wrap in a transaction). If the actual business operation clearly calls for a different approach, explain that in the plan and get approval.

Provide useful results: successful rows, failed rows, validation errors (with row numbers), and duplicate information where relevant. Never expose sensitive information in error output.

Authorize the import endpoint independently. Log the import and its outcome (success or failure, row counts), following the logging rules in `implementation.md`.

## Import modal

Provide: file selection, required file guidance (accepted types, expected headings, template if useful), validation feedback, upload/submit button, loading state, success/error result, and clear/reset behavior on close. JavaScript validation is only a convenience; server-side validation stays authoritative.
