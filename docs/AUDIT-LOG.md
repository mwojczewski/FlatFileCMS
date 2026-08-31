# Filesystem audit log

Security and content mutations are appended to daily JSON Lines files under
`storage/audit/YYYY-MM-DD.jsonl`. Audit data is content-adjacent operational
data and never enters SQLite.

Each line contains an ISO 8601 UTC timestamp, the actor user ID when known, an
action name, a stable resource identifier, the direct client IP and optional
metadata. Entries are UTF-8 JSON with one complete event per line:

```json
{"timestamp":"2026-08-30T12:00:00+00:00","user_id":7,"action":"page.updated","resource":"pages/oferta","ip":"192.0.2.10","metadata":{"revision":"abc"}}
```

The writer opens files in append-only mode, obtains an exclusive lock for each
entry, flushes before releasing it and creates log files with mode `0600`.
Daily filenames provide time-based rotation without rewriting an active file.

Covered events include login success/failure, logout, password changes and
resets, page lifecycle operations and all page-builder block mutations. CLI
user creation, password recovery, media upload/deletion, navigation writes and
global configuration writes are also recorded. Authentication failures
intentionally omit secrets and submitted passwords.

Retention, archival and off-host collection remain deployment policy. Operators
may ship completed daily files after midnight, but must never expose
`storage/audit/` through the web server. Deleting audit files is intentionally
not available in the admin panel.
