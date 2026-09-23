<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Modular Composition Standard

## Purpose

LOOM modules are teammates, not fragments of one monolith. Every module should be independently understandable, independently discoverable, and independently removable while still being able to collaborate with compatible modules through explicit contracts.

## Required design rules

1. **Self-contained first.** A module owns its manifest, runtime entry, styles/assets, lifecycle, and safe defaults. Removing an optional neighboring module must not crash it.
2. **Declare everything LOOM needs to know.** Actions, user actions, dependencies, presentation, Admin settings, extension providers, Pegboard metadata, scope, and order belong in manifests instead of hidden cross-file assumptions.
3. **Respect scope.** `module.scope = global` is LOOM-platform configuration. `module.scope = project` inside `/core-modules` is a LOOM-owned capability injected into every project. Project-local modules remain under `/projects/<slug>/actions`.
4. **Admin is a first-class surface.** Any configurable behavior should expose declarative `admin_settings` where practical. Developer-only controls remain server-gated and Admin-only.
5. **Action Registry / Pegboard truth.** A meaningful capability must have a stable action identity and lifecycle so its availability/activity is observable. Avoid invisible side systems.
6. **Optional extension contracts.** Cross-module collaboration must use discoverable extension/provider contracts or runtime descriptor discovery. Never hard-wire another project's private implementation.
7. **Teammate composition.** A module may become richer when another compatible module exists, but must retain a coherent fallback when that teammate does not exist. Example: LOOM's reusable avatar/profile capability can be extended by a project-specific avatar provider without merging the two modules into one.
8. **Hot discovery is authoritative.** Project runtime discovery polls the filesystem registry. Adding/removing/changing a module should be reflected without editing a central handwritten registry.
9. **Core IDs are protected.** A project-local module must not silently shadow a LOOM-owned project-core action ID. Core project modules are discovered first and win duplicate-ID resolution.
10. **No context-starved releases.** Official development releases are complete source packages. Delta archives may exist for diagnostics, but are not the canonical handoff.

## Human / AI developer checklist

Before shipping a module, ask: can a future developer identify its scope, lifecycle, Admin surface, dependencies, extension points, project boundaries, persistence ownership, and Registry/Pegboard identity from the module itself and LOOM standards? If not, the module is not modular enough yet.
