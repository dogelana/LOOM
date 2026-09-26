<!-- @loom-file release=0.15.33 revision=2 policy=package-priority -->
# LOOM Production Release & Persistent Storage Standard

LOOM release archives are code-first artifacts. A production release MUST NOT carry live users, guests, sessions, logs, replay streams, project state, database credentials, uploaded avatars, or other installation-specific payloads.

## Release-owned
Application source, schemas, migrations, generic project templates, core/reusable modules, static LOOM assets, security `.htaccess` files, and deployment metadata. Concrete product projects are Instance Projects and are not shipped inside the LOOM release tree.

## Installation-owned
`data/**` runtime state (except release-managed security files), database connection configuration, account/identity/profile stores, migrations ledger state, settings, project module state, logs, presence, replay streams, uploads, and project asset uploads.

For release-managed compatibility projects, a default logo under `projects/<project>/assets/logo.*` is release-owned. Normal product projects should instead live as Instance Projects, where their assets and project identity travel with project export/import bundles rather than LOOM core releases.

Bridge must preserve installation-owned paths even when they are absent from a release archive. Absence from a code-first release is not a deletion request. Only an explicit package-priority tombstone authorizes deletion of a release-owned path.
