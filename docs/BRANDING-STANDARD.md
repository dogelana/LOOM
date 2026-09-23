<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Project Branding Standard — v0.11.02

`core.ui.branding` is a reusable head-level LOOM module. It owns browser document branding, not visible project layout.

## Title
The default title is exactly the project `name` from `project.json`. If an Admin-configured `tagline` is non-empty, the title becomes `Project Name · Tagline`. Blank tagline means no suffix. Scaffold/template labels must never be exposed in the title.

## Favicon
The default favicon source is the current project Logo module asset. This keeps favicon branding synchronized automatically when a logo changes. Admins can upload a custom image; the Admin UI decodes it in-browser, fits it into a transparent 64×64 canvas, sends PNG data, and the server stores it as PNG-compressed `assets/favicon.ico`. Admin can switch back to Current Logo without deleting the custom file.

## Cache busting
All favicon sources are resolved through LOOM asset resolution and content-hash cache busting.
