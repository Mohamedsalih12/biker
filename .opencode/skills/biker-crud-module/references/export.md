# Excel Export Reference

Contents: Export · Export button behavior

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