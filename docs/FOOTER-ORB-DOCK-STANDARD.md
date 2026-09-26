<!-- @loom-file release=0.15.49 revision=3 policy=package-priority -->
# LOOM Footer + Orb Dock Standard

`core.ui.footer-bar` owns the footer region and exposes the `orbs` slot. `core.ui.orb-dock` mounts into that slot and can capture content modules into responsive quick-access orbs. Project Update Log can be configured as a required project orb. Orb appearance is configured through project Admin settings; capture/emoji mapping is stored as an Orb Dock admin override.

## v0.11.23 three-row footer composition

Footer Bar owns three vertically stacked rows in this order:

1. Project Branding
2. More Tools
3. LOOM Attribution

Each row independently exposes width mode, alignment, padding, corner radius, and background configuration. The More Tools row defaults to `fit-content`; Orb Dock itself also uses `max-content` up to `100%`, so a one-orb dock stays compact while a larger dock expands naturally and becomes responsive at the viewport boundary.

## 0.11.26 title alignment and LOOM attribution

Orb Dock section titles are always centered over the actual dock width, independent of the footer row's left/center/right placement.

The footer's LOOM attribution mark is procedural and animated through `LoomBrand`; do not substitute a static LOOM image for user-visible attribution.
