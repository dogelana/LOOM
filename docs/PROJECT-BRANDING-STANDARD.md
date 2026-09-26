<!-- @loom-file release=0.15.70 revision=2 policy=package-priority -->
# LOOM Project Branding Standard

Status: implemented in LOOM 0.15.14.

## Ownership model

Every project has one **canonical Project Identity brand**. It owns the human project name, canonical two-line wordmark, primary color, accent color, and canonical wordmark font.

The internal slug is routing/storage identity only. It must never be used as visible fallback brand text when a human project name is available. `example-app` therefore renders from `Example App`, and automatic wordmark balancing yields `EXAMPLE` / `APP`.

## Canonical wordmark

Project Identity supports:

- **Auto**: derive a balanced one- or two-line wordmark from the human project name.
- **Custom**: explicit canonical line 1 and line 2.

Primary and accent project colors are canonical wordmark colors. The canonical font defaults to League Spartan and can be changed in Project Identity.

## Location inheritance

Locations inherit Project Identity unless explicitly overridden:

- LOOM Home project cards: canonical branding.
- Project Loader: canonical branding.
- Header Wordmark (`core.ui.load-logo-text`): canonical by default; module can be disabled to hide header text or switched to a header-only custom override.
- Footer Bar: canonical by default; footer text can independently use Project Identity, custom footer wording, or Hidden (logo only), with independent local color/font overrides.
- Social Links: Project color mode continues to inherit the project's configured social/footer color, which defaults to the canonical primary color.

Disabling Header Wordmark never changes Footer or Loader branding. Hiding Footer wordmark never changes Header or Loader branding.

## Backwards compatibility

The action ID `core.ui.load-logo-text` remains stable. Pre-0.15.14 explicit Logo Text wording/color/font overrides are interpreted as header-local custom overrides. Existing project-local Loader copies are superseded by the release-managed `core.ui.loader` implementation while remaining harmless legacy files inside persistent Instance Projects.
