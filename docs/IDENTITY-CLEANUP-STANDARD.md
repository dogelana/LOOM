# LOOM Identity Cleanup & Data Purge Standard

LOOM treats identity deletion as a destructive System Owner operation. Cleanup is never inferred from ordinary account/profile editing and never runs without an explicit preview and exact typed confirmation.

## Targets

- **One permanent user** — removes one permanent account; the System Owner is never eligible.
- **One Guest Identity** — removes one selected Guest Identity and its selected associated data.
- **All permanent users** — removes all permanent accounts except the System Owner.
- **All Guest Identities** — removes all Guest Identities.
- **All users and identities** — removes all non-owner permanent users plus all Guest Identities.
- **Factory identity wipe** — separately protected operation that also removes the System Owner and Admin identity/access state.

## Storage scope

The operator explicitly chooses durable local Instance state, SQL persistence, or both. If SQL is selected but no usable SQL connection exists, cleanup fails rather than pretending the database was cleaned. A factory identity wipe on a SQL-backed installation must target both stores.

## Data classes

Cleanup exposes explicit switches for project data, activity/history, referrals, continuity, media/avatar files, audit history, and attached Guest Histories. Core authentication material for a deleted permanent user (account, sessions, mappings, password-reset tokens, username registry ownership) is always removed.

Audit history is not deleted by default. This preserves an administrative record unless the System Owner intentionally selects audit deletion.

## Guest preservation rule

Deleting a permanent account does **not** automatically destroy the Guest Histories that had been attached to it. They are detached and remain as Guest Identities unless **Delete attached guests too** is explicitly selected. This prevents account cleanup from silently erasing historical anonymous project state.

## System Owner protection

Ordinary single/bulk deletion excludes the immutable System Owner. Factory-level owner removal requires the exact phrase `WIPE ALL IDENTITIES AND OWNER`. Other bulk operations require `DELETE ALL SELECTED IDENTITIES`; single identity deletion requires `DELETE IDENTITY DATA`.

## Authority and reversibility

Only the System Owner may invoke the purge endpoint. Preview data must be regenerated whenever target, storage scope, or deletion options change. Deletion is intentionally permanent; backups and database snapshots are the recovery mechanism.

<!-- @loom-file release=0.15.51 revision=1 policy=package-priority -->
