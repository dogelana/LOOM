<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Project Lifecycle Standard

## Active projects
Active projects live under `projects/<slug>/` and are discoverable by `api/projects.php`.

## Archived projects
Archived projects live under `archives/projects/<archive-slug>/`. Each archive contains:
- `project/` — the complete project tree
- `data/logs/` — project telemetry, when present
- `data/presence/` — project presence snapshots, when present
- `archive.json` — archive metadata

## Naming
Archiving `foo` produces `foo-old-1`. Future archives use the next available integer (`foo-old-2`, etc.). No archive or restore operation may overwrite an existing project or archive.

Restoring uses the archive slug as the preferred active slug. If that active slug already exists, LOOM selects the next available `-old-N` slug.

## Browser-local project state
LOOM Home migrates project-scoped `localStorage` and `sessionStorage` keys from the active slug to the archive slug when archiving, and from the archive slug to the restored slug when restoring. This keeps browser-local project state isolated from a newly created project using the original slug.

## Green Beans scratch reset migration
v0.9.1 includes a one-time migration `green-beans-reset-v1`. On first LOOM Home/API load it:
1. Archives the currently deployed `projects/green-beans` as `green-beans-old-N` (normally `green-beans-old-1`).
2. Moves server-side Green Beans logs/presence into that archive.
3. Installs the fresh Green Beans template from `templates/projects/green-beans` as the new active `green-beans`.
4. LOOM Home migrates each browser's old Green Beans local/session storage to the archive slug and clears the original namespace.

This migration is idempotent through `data/migrations/green-beans-reset-v1.done.json`.
