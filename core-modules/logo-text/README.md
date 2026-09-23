# LOOM Core · Logo Text

`core.ui.load-logo-text` is now a release-managed project-scoped core module. It is available to every LOOM project, including existing Instance Projects.

If a project has no explicit Logo Text overrides, LOOM derives a balanced one- or two-line wordmark from the canonical project name. Hyphens, underscores and whitespace are treated as word boundaries for automatic layout, so `Lint-Away` becomes `LINT` / `AWAY`. Project main/accent colors become wordmark line colors by default. Legacy project-local manifests remain usable as project-specific defaults, while the release-managed implementation wins for fixes and upgrades.
