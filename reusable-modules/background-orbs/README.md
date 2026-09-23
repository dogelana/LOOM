<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# LOOM reusable module: Background Energy Field

Compatibility action ID: `core.ui.background-orbs`.

The LOOM-native visual is now a dense field of small glowing electric circuitry particles, sparks, and wisps rather than large glass bubbles. The default density is 70 particles (about 5× the prior 14-orb starter).

Projects may provide `core.ui.background-orbs.provider` to replace the regular ambient artwork and optionally the occasional special particle. LOOM core always owns motion, density, timing, glow strength, reduced-motion behavior, source selection, and Admin overrides.

Without a project provider, regular particles are LOOM electric energy wisps and the occasional special particle is an animated LOOM cube. Green Beans is the reference provider: pea-pod regular particles plus a carrot (`🥕`) special particle.
