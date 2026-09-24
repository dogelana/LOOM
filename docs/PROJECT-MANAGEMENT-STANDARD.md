<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Project Management Standard

## Canonical project identity

`projects/<slug>/project.json` owns the project identity fields:

- `slug` (filesystem identity; chosen at creation)
- `name`
- `tagline`
- `description`
- `bio`
- `version`
- `theme`
- `branding.logo_asset` (canonical default: `assets/logo.png`)
- `branding.logo_alt`

The logo file is normalized to PNG on upload and stored at `projects/<slug>/assets/logo.png`.

## Management surfaces

Admin can manage the same canonical identity from:

1. **LOOM Home** — fast project overlay for identity/logo and navigation to full settings.
2. **LOOM Admin → Project Settings** — project identity plus all project module settings.
3. **Inside a project** — the Admin-only `Project Settings` shortcut deep-links directly to that project’s settings.

Both surfaces write the same project metadata APIs; neither maintains a shadow copy.

## Baseline project creation

LOOM Home's `Deploy / Add Project` creates from `/templates/projects/baseline`. The template provides the project shell and standard reusable core stack. LOOM-owned `core-modules` with `scope: project` are not copied; they inject automatically at runtime.
