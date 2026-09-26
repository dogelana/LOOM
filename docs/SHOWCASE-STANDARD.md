<!-- @loom-file release=0.15.01 revision=1 policy=package-priority -->
# Showcase Standard

`loom.showcase` is a LOOM-owned project-scoped core module. Its code is release-owned under `core-modules/showcase/`; its mutable per-project configuration is Admin state, and its uploaded image is persistent Instance Vault state.

## Bio semantics

The default mode is `project`. In that mode the runtime reads the current effective project `bio`, so the Showcase summary remains the exact same bio LOOM uses for the project. A `custom` mode may store a Showcase-only `bioOverride`. Returning to project mode does not need to destroy the previous override; it simply stops using it.

## Media semantics

Admin upload and drag/drop normalize browser-readable images to PNG and persist them at `instance/projects/<project>/overlay/assets/showcase.png`. Release archives and Git must never contain that file. `project-asset.php` is the public serving boundary.

## Control semantics

Showcase is discoverable through the normal project-core module scan and Module Control Center. Disabling the module hides it without deleting its Admin configuration or uploaded image.
