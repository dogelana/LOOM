<!-- @loom-file release=0.15.16 revision=3 policy=package-priority -->
# LOOM-Owned Shell Standard

Version: 0.11.25

LOOM-owned pages use the shared `engine/loom-shell.js` component.

## Covered surfaces

- LOOM Home
- LOOM Admin
- Pegboard
- Action Registry
- future LOOM-owned utility pages

The shared shell mounts the canonical LOOM header and footer from `LoomBrand`.

## Public global-profile control

The shell exposes `👤 LOOM Profile` to normal users.

This surface is intentionally global-only. It may show and edit:

- LOOM global username
- LOOM global profile picture
- permanent-account email and User ID
- effective privilege
- sign in / sign out
- permanent-account creation
- global profile creation/update timestamps
- copy profile picture from a selected project into the LOOM-wide profile

It must not expose a project's username override, project avatar mode, project identity ID, project analytics, or other project-scoped identity settings.

Those belong to `core.user.profile` when opened from inside a project.

## Shared chrome rule

Do not recreate the LOOM header or footer per page. New LOOM-owned pages should include:

- `engine/loom-brand.js`
- `engine/identity.js`
- `engine/loom-global-profile.js`
- `engine/loom-shell.js`

and then call `LoomShell.mount(...)`.

## 0.11.26 visual integration rules

LOOM-owned full-screen tools must reserve physical page rows for the shared shell. They must not place a `position:fixed; inset:0` workspace over the shared header/footer.

Pegboard is the reference implementation:

```text
shared LOOM header
bounded application workspace
shared LOOM footer
```

Page-specific controls may be injected into the shared shell navigation area instead of creating a second page header.

Every user-visible LOOM mark should use `LoomBrand.mountCube(...)`. Static LOOM image assets may remain in the package for compatibility/export, but should not be used as visible platform chrome.

## Static favicon (0.11.31+)

Every LOOM-owned shell page uses the static LOOM icon as its browser favicon. `LoomShell.mount()` enforces this automatically. Project pages remain free to use project-owned favicons.

## 0.11.32 Pegboard + visitor presence
Pegboard now uses a physically bounded shared-shell layout and no longer references removed legacy header DOM. LOOM Home records fresh client identities as temporary visitors before permanent account conversion.


## Canonical Admin navigation (0.15.16+)

`LoomShell` owns the Admin destination map. When the active identity is an Admin, shell pages render one shared Admin-only group containing LOOM Settings, Project Settings, Users, Identity Manager, Database, Activity, Pegboard, and Action Registry. Project shells call `LoomShell.renderAdminLinks(...)` so custom project chrome uses the same source of truth rather than copying links manually.

The shared shell normalizes the public Home link as **🏠 LOOM Home**. Bootstrap-only Admin Setup is intentionally not part of the everyday Admin tool group.
