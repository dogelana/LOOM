# LOOM Core · Logo Text

`core.ui.load-logo-text` is now a release-managed project-scoped core module. It is available to every LOOM project, including existing Instance Projects.

If a project has no explicit Logo Text overrides, LOOM derives a balanced one- or two-line wordmark from the canonical project name. Hyphens, underscores and whitespace are treated as word boundaries for automatic layout, so `Example-App` becomes `EXAMPLE` / `APP`. Project main/accent colors become wordmark line colors by default. Legacy project-local manifests remain usable as project-specific defaults, while the release-managed implementation wins for fixes and upgrades.

## 0.15.14 branding ownership

The canonical project wordmark now belongs to **Project Identity**, not this module. `core.ui.load-logo-text` is the **Header Wordmark renderer**. Disabling it removes header wordmark text only. Loader, LOOM Home, Footer, and project-aware modules continue using the canonical Project Identity wordmark.

Header settings default to inheriting Project Identity wording, main/accent colors, and font. Admin may switch any of those sources to Custom for a header-only override. Pre-0.15.14 explicit Logo Text values are recognized as custom header overrides for backwards compatibility.

