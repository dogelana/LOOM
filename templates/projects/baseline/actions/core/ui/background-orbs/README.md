<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM reusable module: Background Orbs

A non-interactive ambient background module for project pages. It renders slow infinite zero-gravity orbs behind project content.

Projects may optionally provide the `core.ui.background-orbs.provider` extension. The provider owns only replacement orb artwork and a suggested glow color. Motion, density, speed, glow strength, source selection, and Admin overrides remain core LOOM behavior.

Default source behavior is `project`: when a project provider exists it wins automatically. Without a provider the module safely falls back to LOOM's native glass orbs.
