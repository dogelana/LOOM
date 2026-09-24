<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Database Integrity Standard

LOOM treats durable SQL data as recoverable state, not as something to wipe when mappings disagree.

## Authorization source of truth
A valid authenticated permanent account whose `loom_users.privilege` is `Admin` is sufficient for Admin authorization. `loom_admin_state` is a bootstrap/recovery pointer and must never downgrade a valid Admin account.

## Integrity Doctor
Admin → Database → Integrity Doctor can:

- scan the expected LOOM tables and identity relationships without changing data;
- create a protected server-side snapshot of every LOOM table, one JSONL file per table;
- reconcile the current authenticated Admin user, client mapping, admin-state pointer, client cache, and username registry when those relationships are unambiguous;
- remove expired authentication sessions;
- report orphaned or ambiguous data without deleting it.

Safe Repair never deletes permanent user accounts, project history, events, avatars, IP history, moderation records, or module settings. Unknown corruption is reported for manual review rather than guessed at.

Database snapshots are stored under protected `data/admin/database-backups/` and intentionally do not include the database password.
