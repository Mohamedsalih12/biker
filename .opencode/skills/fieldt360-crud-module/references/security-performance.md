# Security and Performance Review

Run this review before calling the module complete. Follow Laravel 13 security best practices and the existing fieldt360 security architecture.

## Security checklist

* Broken access control, IDOR, privilege escalation
* Mass assignment
* SQL injection, unsafe dynamic queries, unsafe dynamic sorting (allowlist only)
* XSS (escaped output) and CSRF (tokens on every state-changing form/request)
* Unsafe file uploads (type, size, storage location)
* Spreadsheet formula injection in exports
* Sensitive information leakage (responses, logs, import error output)
* Unauthorized imports, exports, bulk deletion
* Missing validation or missing authorization on any endpoint
* Excessive database exposure (selecting or returning more columns than needed)

## Performance checklist

* N+1 queries; unnecessary eager loading
* Missing or redundant indexes
* Large unbounded queries; entire datasets loaded into memory
* Inefficient search, import, or export (prefer query/chunked/queued approaches for large data)
* Pagination used for large datasets

Do not optimize prematurely, but fix obvious production risks and mention any that remain.

