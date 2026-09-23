<!-- @loom-file release=0.12.09 revision=1 policy=package-priority -->
# LOOM Production Release & Persistent Storage Standard

LOOM release archives are code-first artifacts. A production release MUST NOT carry live users, guests, sessions, logs, replay streams, project state, database credentials, uploaded avatars, or other installation-specific payloads.

## Release-owned
Application source, schemas, migrations, templates, core/reusable/project modules, static product assets, security `.htaccess` files, the canonical default Green Beans logo, and deployment metadata.

## Installation-owned
`data/**` runtime state (except release-managed security files), database connection configuration, account/identity/profile stores, migrations ledger state, settings, project module state, logs, presence, replay streams, uploads, and project asset uploads.

The default project logo at `projects/<project>/assets/logo.*` is a release-owned base asset. Admin/user replacements belong under `projects/<project>/assets/uploads/**`; `project.json` may point at the uploaded asset without modifying the base release file.

Bridge must preserve installation-owned paths even when they are absent from a release archive. Absence from a code-first release is not a deletion request. Only an explicit package-priority tombstone authorizes deletion of a release-owned path.
