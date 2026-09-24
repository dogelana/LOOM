# LOOM Export / Import / Backup Standard

LOOM 0.15.30 introduces a portable bundle protocol for moving projects between LOOM installations and for recovering installation-owned state without weakening the Clean Instance Protocol.

## Bundle format

A bundle is a ZIP containing `loom-export.json` plus one or more payload trees. The manifest identifies the source LOOM version, export type, projects, included and excluded data classes, database portability information, compatibility requirements, and SHA-256/size metadata for every declared payload file.

LOOM verifies declared hashes before import preview. Uploading a bundle never applies it.

## Export types

### Project only

Includes the project runtime, canonical project metadata, module/configuration state needed to reproduce the project, project overlays and project-owned assets. It intentionally excludes server-specific user/session/referral/telemetry data.

Project-only bundles import as Instance Projects by default, even when the source project was release-managed. This is the preferred project-sharing and migration format.

### Project + data

Includes Project only plus that project's persistent Instance Vault payloads. Use it for project backup/migration when project-owned state matters.

### All projects / All projects + data

The same two models applied across every discovered LOOM project.

### Full LOOM state

System Owner only. Includes portable persistent Instance state and, when available, a schema-aware database payload for LOOM application tables. Database connection configuration is not exported. Active authentication sessions, capability secrets, admin tokens, guest recovery secrets, and the database copy of root-admin authority are excluded. The destination installation's immutable System Owner pointer is preserved during a full restore so an import cannot lock out or silently replace the destination root authority.

The full bundle is intentionally sensitive because it can contain identities, attribution history, project data, uploads, audit history and password hashes needed to restore permanent accounts. Store it as securely as a server backup.

## Database portability

LOOM exports database application rows rather than database connection credentials. `loom_auth_sessions` and guest recovery-token tables are never exported. Database credentials remain local to each LOOM installation.

When importing a bundle containing database data:

- if a LOOM database is configured and initialized, Admin may apply the portable rows transactionally;
- if no database exists yet, LOOM stages the payload beneath protected Instance storage and exposes it later under Backup & Restore;
- staged payloads can be applied after the destination database is configured.

This separates data portability from infrastructure secrets.

## Import safety

Import supports preview plus explicit strategies:

- **Create new only** — fail if a target project already exists.
- **Merge safely** — overlay portable files/data without deleting unrelated destination files.
- **Replace** — snapshot affected persistent state first, then replace the selected target.
- **Skip conflicts** — leave existing targets unchanged.

A full LOOM restore requires System Owner authority and the exact confirmation phrase `RESTORE LOOM`.

Destructive project replacement and full restore create rollback snapshots beneath protected Instance storage before mutation. Generated exports, staged imports, pending database payloads and rollback snapshots never belong to a normal LOOM release package.

## Authority

- System Owner: full backup and full restore.
- LOOM Admin: all-project exports/imports when permitted by normal Admin authorization.
- Project Admin: project-scoped export capability through the API for projects they actually administer; no unrelated/global data exposure.

Server-side capability checks are authoritative. UI visibility alone is never treated as security.

## Clean Instance Protocol

Normal LOOM release ZIPs still ship **zero real `/instance/**` runtime state**. Portable backup ZIPs are different artifacts created intentionally at runtime and stored inside the protected Instance Vault until downloaded or removed.

This distinction is permanent: release packages install LOOM code; portable bundles move or recover LOOM state.

## Legacy Release Project → private Instance Project

The portable project format is also the supported bridge for projects that historically shipped inside LOOM releases but should become private installation-owned projects.

Recommended sequence:

1. Export **Project only** (or **Project + Data** when project-owned persistent state must travel).
2. Import that bundle back into the same LOOM installation using the same slug and **Replace**.
3. LOOM snapshots the existing target and installs the bundle beneath `instance/projects/<slug>/project/`.
4. Verify the project now reports `source=instance` and behaves correctly.
5. Only then remove the historical release copy from a future LOOM source package/Git branch.

Because `project_dir()` prefers an Instance Project over a release project with the same slug, this migration can be verified before the old release copy is physically retired.
