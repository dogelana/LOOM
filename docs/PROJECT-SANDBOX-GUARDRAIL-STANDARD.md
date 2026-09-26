<!-- @loom-file release=0.12.09 revision=2 policy=package-priority -->
# LOOM Project Sandbox & Guardrail Standard — v0.12.08

Projects are isolated by default. A project module operates inside its own project scope and must not infer that another project's files, state, identities, or actions are directly available.

## Rules

- Project slugs are resolved through LOOM's canonical project resolver.
- Sandbox path resolution rejects absolute paths, traversal, drive prefixes, and escape outside the project root.
- Same-project access is allowed through declared module/action contracts.
- Cross-project access is denied by default and requires an explicit privileged bridge/capability; Admin is the only initial privileged bypass.
- Physical server paths are never returned by the public sandbox endpoint.
- Project state remains project-scoped even when a permanent LOOM account spans projects.

This layer is deliberately additive. Existing project modules continue operating, while new cross-project behavior must be explicit rather than relying on hidden coupling.
