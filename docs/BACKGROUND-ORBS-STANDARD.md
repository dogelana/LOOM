<!-- @loom-file release=0.15.49 revision=5 policy=package-priority -->
# LOOM Background Energy Field Standard — v0.12.04

`core.ui.background-orbs` remains the stable module/action ID for compatibility, but its LOOM-native default visual is now an electric circuitry-inspired **energy field**, not glass bubbles.

## Core ownership
LOOM core owns rendering, randomized zero-gravity motion, density, glow, special-particle timing, cleanup, reduced-motion behavior, and Admin controls. The default field uses many small luminous dashes/nodes/wisps rather than large circular bubbles.

Starter defaults:

- particle/orb volume: **96** (higher-visibility LOOM default)
- drift speed: 100%
- glow: 58%
- special particle: enabled
- special interval: about 42 seconds with randomized spacing
- special visible time: 14 seconds
- LOOM special particle: animated LOOM cube with a randomized motion preset/axis feel

## Project extension
A project may expose `core.ui.background-orbs.provider`. Regular project identity may provide:

- `getOrbAssetUrl()` / `orbAssetUrl`
- `getOrbEmoji()` / `orbEmoji`
- `glowColor` / `defaultGlowColor`

The optional special-particle teammate contract may additionally provide:

- `getSpecialOrbAssetUrl()` / `specialOrbAssetUrl`
- `getSpecialOrbEmoji()` / `specialOrbEmoji`
- `specialGlowColor`
- `specialOrbKind` (reserved for future provider kinds)

If a project does not provide a special override, LOOM falls back to its animated cube special particle. The project never owns the animation engine itself.

A project extension may supply custom artwork for both regular particles and the occasional special particle.

## Admin precedence
1. Explicit Admin settings
2. Project provider defaults while `sourceMode=project`
3. LOOM core defaults

`Use Project Defaults` clears core Admin overrides so the provider can take over again. `Use LOOM Defaults` clears existing overrides and sets `sourceMode=loom`.

## Accessibility / behavior
The field uses `pointer-events:none`, stays behind project UI, and never captures interaction. `prefers-reduced-motion` disables ambient motion and timed special-particle spawning.

## Asset guidance
Bundled SVG/PNG/WebP is preferred when exact cross-platform appearance matters. Native emoji is allowed for special/regular providers when platform-native emoji rendering is acceptable.
