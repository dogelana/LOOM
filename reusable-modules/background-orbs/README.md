<!-- @loom-file release=0.15.49 revision=5 policy=package-priority -->
# LOOM reusable module: Background Energy Field

Compatibility action ID: `core.ui.background-orbs`.

The LOOM-native visual is now a dense field of small glowing electric circuitry particles, sparks, and wisps rather than large glass bubbles. The default density is 96 particles with a 135% drift-speed baseline and stronger glow.

Projects may provide `core.ui.background-orbs.provider` to replace the regular ambient artwork and optionally the occasional special particle. LOOM core always owns motion, density, timing, glow strength, reduced-motion behavior, source selection, and Admin overrides.

Without a project provider, regular particles are LOOM electric energy wisps and the occasional special particle is an animated LOOM cube. Projects may provide their own regular and special particle artwork through the provider contract.
