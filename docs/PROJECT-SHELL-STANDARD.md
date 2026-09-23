<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# LOOM Project Shell Standard

Version: 0.11.24

The built-in project shell is LOOM-owned chrome surrounding project modules.

## Required public controls

- Project mark
- Project name
- LOOM Home
- Profile shell slot

`LOOM Home` is available to every user.

## Admin-only controls

- LOOM Admin
- Pegboard
- Action Registry

These controls are hidden from non-Admins and remain server-protected.

## Project mark resolution

1. `project.json` canonical `branding.logo_asset`
2. compatible project Logo module asset
3. animated LOOM cube fallback

The animated LOOM fallback uses the global LOOM Brand Motion settings.

## Profile Dock

If `core.user.profile-dock` is installed, it injects `👤 User Profile` into the shell profile slot and captures the live `core.user.profile` module out of the ordinary vertical module flow.


## v0.12.04 starter chrome defaults

Baseline/new projects use the reusable Header Bar and Footer Bar with intentionally compact starter geometry:

- Header width: `fit-content`
- Header branding alignment: `left`
- Footer width: `fit-content`
- Footer project branding/logo alignment: `center`

Projects remain free to override these through normal Admin module settings. Existing projects that have no explicit Admin override receive the packaged defaults when their reusable core module manifests update.
