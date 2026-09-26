<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Module Collapse Standard

As of v0.11.22, ordinary root content modules are collapsible by LOOM itself. State is persisted per project and persistent user via `project-state.php` under the `core.ui.module-layout` state key. Anonymous client state is promoted when an account becomes permanent. Modules may opt out with `presentation.collapsible=false`.

## v0.11.23 visual shell rule

When expanded, the LOOM collapse header and module surface must read as one card. LOOM removes the module root's top corner radii and overlaps the shared border by one pixel. When collapsed, the header regains its own compact full-radius card shape.
