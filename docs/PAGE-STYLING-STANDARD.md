<!-- @loom-file release=0.15.49 revision=3 policy=package-priority -->
# LOOM Page Styling Standard

Version: 0.12.02

`loom.page.styling` is a LOOM-owned **project-scoped core module**. The implementation lives once under `core-modules/page-styling/`, but its configuration is stored independently for each project through the normal protected project Admin-settings system.

## Ownership rule

- Code ownership: LOOM core.
- Configuration ownership: selected project.
- Admin surface: **Admin → Project Settings → Page Styling**.
- Runtime: automatically injected into every live project module registry.
- Project-local modules may not shadow a project-scoped LOOM core module ID.

## Default visual contract

The default values intentionally match the generic LOOM baseline shell: a soft neutral-tinted background, readable dark text, LOOM accent, white translucent chrome, fluid content width, 18px page gutter, and 24px module spacing. Installing the release should therefore preserve the existing appearance until an administrator changes project values.

## Styling tokens

The module publishes stable CSS custom properties including `--loom-page-background`, `--loom-page-text`, `--loom-page-accent`, `--loom-page-muted`, `--loom-page-surface`, `--loom-page-border`, `--loom-page-radius`, `--loom-page-content-max`, `--loom-page-gutter`, and `--loom-page-section-gap`. It also bridges the existing project-shell aliases `--green`, `--ink`, and `--muted`. Future project modules should prefer the `--loom-page-*` tokens when they want to participate in project-level styling.
