<!-- @loom-file release=0.15.04 revision=1 policy=package-priority -->
# Social Links

## Module

- Action ID: `loom.social-links`
- Scope: project
- Default state: enabled, with no visible links
- Footer position: after project branding, before More Tools

## Supported links

Facebook, Instagram, YouTube, TikTok, Website, and one Custom Link are configured independently. Blank URL fields render nothing. Runtime URL normalization accepts pasted domain names by prepending HTTPS and rejects non-HTTP(S) protocols.

## Icons

The release bundles a small Font Awesome Free 6.7.2 icon subset as SVG path data. Brand icons come from Font Awesome Free Brands; the website globe and curated custom icon choices come from Font Awesome Free Solid/Brands. Icons are transparent vector geometry using `currentColor`, so one shared color changes the entire row without recoloring image files. The Font Awesome license is included with the module.

## Color ownership

Project Identity owns `social_color`, the project's preferred footer/social icon color. The Social Links module exposes three color modes:

1. Project default — inherit `social_color`.
2. LOOM default — use solid black `#000000`.
3. Custom color — use the module's manual color value.

Existing projects that predate this field safely fall back to black until an Admin saves another project default.

## Persistence

Module URL/color settings are ordinary project module settings and persist in the Instance Vault through the existing Admin settings system. Project Identity color changes persist through `project-overrides.json`. No runtime social data is written into the replaceable release tree.
