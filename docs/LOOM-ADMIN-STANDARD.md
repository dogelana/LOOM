<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Admin Standard — v0.11.0

## Initial administrator
A fresh LOOM installation has no administrator. The first stable client that reaches the bootstrap path becomes the initial Admin.

Before that Admin has a permanent account, authorization uses:
- matching bootstrap Client ID, plus
- a secret HttpOnly administrator credential.

The visible Client ID by itself is never sufficient.

## Permanent Admin
When the bootstrap Admin creates a permanent account, LOOM binds the Admin record to that account's `userId`. From then on, protected pages require authentication as that Admin user. Logging out removes Admin access; signing in on another browser restores it.

## Protected surfaces
Server-side Admin gating applies to:
- `/admin/`
- `/pegboard/`
- `/registry/`
- session/event telemetry APIs
- project archive/restore mutation API
- database configuration/migration API

The regular project app may show Admin/Pegboard/Registry buttons only after Admin privilege is confirmed.

## Declarative module settings
Reusable modules expose safe controls through `manifest.json` `admin_settings`. LOOM Admin writes validated overrides separately from module source. The runtime merges defaults + overrides.

Current reusable core controls include:
- Header Bar: height, radius, background, full/fit-content width, left/center/right branding position.
- Logo: normalized 10–100 scale where 50 = legacy 100 and 100 = legacy 200.
- Logo Text: line content, font, font size up to 144px, line colors.
- User Profile: no Admin controls.
