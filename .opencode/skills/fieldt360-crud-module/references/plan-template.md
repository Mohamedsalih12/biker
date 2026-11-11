# Implementation Plan Template

Present the plan using these sections, in order. Lead with Confirmed / Assumptions / Questions (see SKILL.md), then:

**A. Understanding**
Briefly state what the user wants to build.

**B. Existing patterns reused**
List the specific fieldt360 modules, files, and patterns you will reuse (name the closest reference module).

**C. Database design** (table)
For each column: name, type, nullable, default, foreign key, index, unique constraint. Mention foreign-key deletion behavior chosen per actual business need.

**D. Relationships**
Proposed Eloquent relationships, with explicit keys where needed.

**E. Authorization**
- Policy methods (`viewAny`, `view`, `create`, `update`, `delete`, `import`, `export`, `bulkDelete` as applicable)
- Required Spatie permissions, following existing naming
- Existing roles that should receive them, if determinable from the project. Do not auto-grant unless an established convention exists or the user approves.

**F. Files to create**
Every new file.

**G. Files to modify**
Every existing file that will change.

**H. User interface**
Describe: index page (use Livewire Powergrid with Filters, Search, Pagination, Export, Sort, Checkbox, Bulk Delete), search/filter controls, create form (Select2 for dropdowns), edit form (Select2 for dropdowns), show page, import modal.
**I. Import/export**
Required columns, validation, duplicate handling, error handling, authorization, large-data considerations.

**J. Testing**
The tests you intend to create.

**K. Risks**
Anything that could affect existing data, change existing behavior, require migrating existing records, change permissions, affect production performance, or need deployment steps.

Then stop and ask for approval.
