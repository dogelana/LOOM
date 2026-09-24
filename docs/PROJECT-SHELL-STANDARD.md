<!-- @loom-file release=0.15.34 revision=4 policy=package-priority -->
# LOOM Project Shell Standard

Version: 0.15.34

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

## Two width lanes (v0.15.34)

The project shell exposes two distinct horizontal layout intents:

- **Content lane** — the default `#feature-stage` flow, capped at 1320px for readable ordinary modules.
- **Page lane** — an opt-in breakout for root-mounted modules declaring `presentation.layout.widthScope = "page"`. It resolves against the shell's full usable inline width (page width minus the shell gutter), with container-query units where supported and a viewport-width fallback.

This distinction keeps normal project pages visually disciplined while allowing full-canvas tools such as HTML Framer to use the space users expect. A module's own percentage width is then measured inside the page lane, so 95% means 95% of the usable page rather than 95% of the 1320px content column.
