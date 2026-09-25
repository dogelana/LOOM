<!-- @loom-file release=0.15.49 revision=4 policy=package-priority -->
# LOOM Project Lifecycle Standard

## Active projects
Active projects live under `projects/<slug>/` and are discoverable by `api/projects.php`.

## Archived projects
Archived projects live under `archives/projects/<archive-slug>/`. Each archive contains:
- `project/` — the complete project tree
- `data/logs/` — project telemetry, when present
- `data/presence/` — project presence snapshots, when present
- `archive.json` — archive metadata


## Instance-first release boundary (v0.15.33)
LOOM production releases do not ship any concrete product as an active release-managed project or project template. Product projects belong under the Instance Project protocol and move between installations with project export/import bundles. Historical product-specific reset migrations are retired and must never recreate product projects on a fresh install.
## Naming
Archiving `foo` produces `foo-old-1`. Future archives use the next available integer (`foo-old-2`, etc.). No archive or restore operation may overwrite an existing project or archive.

Restoring uses the archive slug as the preferred active slug. If that active slug already exists, LOOM selects the next available `-old-N` slug.

## Browser-local project state
LOOM Home migrates project-scoped `localStorage` and `sessionStorage` keys from the active slug to the archive slug when archiving, and from the archive slug to the restored slug when restoring. This keeps browser-local project state isolated from a newly created project using the original slug.

## Historical product reset migrations
Older product-specific scratch-reset migrations are retired compatibility history only. Current releases must not create, restore, or assume a named product project.
