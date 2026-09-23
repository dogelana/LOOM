<!-- @loom-file release=0.15.13 revision=3 policy=package-priority -->
# LOOM Project Branding Standard — v0.11.02

`core.ui.branding` is a reusable head-level LOOM module. It owns browser document branding, not visible project layout.

## Title
The default title is exactly the project `name` from `project.json`. If an Admin-configured `tagline` is non-empty, the title becomes `Project Name · Tagline`. Blank tagline means no suffix. Scaffold/template labels must never be exposed in the title.

## Favicon
The default favicon source is the current project Logo module asset. This keeps favicon branding synchronized automatically when a logo changes. Admins can upload a custom image; the Admin UI decodes it in-browser, fits it into a transparent 64×64 canvas, sends PNG data, and the server stores it as PNG-compressed `assets/favicon.ico`. Admin can switch back to Current Logo without deleting the custom file.

## Cache busting
All favicon sources are resolved through LOOM asset resolution and content-hash cache busting.


## Project brand colors and automatic wordmark (0.15.13)

Project identity may define `brand_primary_color` and `brand_accent_color`. When omitted, LOOM uses project legacy brand defaults when available, then LOOM defaults (`#111111` main and `#168346` accent). Project-default Social Links use the effective main/social color.

`core.ui.load-logo-text` is universal. Without explicit Logo Text overrides, the canonical project name is split at word boundaries into one or two visually balanced lines. Hyphens, underscores, en dashes and em dashes are treated as display word separators during automatic splitting; the canonical project name itself is not rewritten. Project names are capped at 140 characters.
