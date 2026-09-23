<!-- @loom-file release=0.15.07 revision=1 policy=package-priority -->
# LOOM Domain Landing / Project-at-Root Standard

Status: **planned architecture; not enabled in 0.15.07**.

## Goal

LOOM may be installed at a site's base URL while an Admin chooses whether that base URL opens **LOOM Home** or one selected project. Example:

- installation base: `https://example.com/`
- selected landing project: `green-beans`
- visiting `https://example.com/` renders Green Beans at the base URL
- LOOM Home remains permanently reachable at `https://example.com/home/`
- `/admin/`, `/api/`, `/pegboard/`, `/registry/`, assets, project canonical URLs and Instance Vault behavior continue normally

The same concept must also work when LOOM is installed in a subdirectory. If the installation base is `https://example.com/LOOM/`, a project may own `/LOOM/` while LOOM Home moves to `/LOOM/home/`.

## Core architecture

The user-facing control should be exposed as a **global LOOM core capability** tentatively named `loom.domain-landing`, but routing itself must happen server-side before project JavaScript starts. It must not depend on a browser-only module.

Persistent configuration belongs in the Instance Vault, for example:

```json
{
  "schemaVersion": "1.0",
  "homePath": "home",
  "landing": {"mode": "project", "project": "green-beans"},
  "hostBindings": []
}
```

Recommended location: `instance/config/domain-routing.json`.

Default behavior remains `landing.mode = "loom-home"`, so existing installations do not change until an Admin explicitly selects a landing project.

## Stable server entrypoint

Do **not** have Admin settings rewrite `.htaccess` every time the selected project changes. Ship one generic routing rule in the release and keep the mutable decision in Instance Vault state.

The root request should enter a small LOOM front controller. The controller decides:

1. exact installation-base request + `loom-home` mode -> serve LOOM Home;
2. exact installation-base request + project mode -> serve the selected project through a root-mount-capable project shell;
3. `/home/` -> always serve LOOM Home;
4. protected/reserved paths such as `/admin/`, `/api/`, `/assets/`, `/engine/`, `/projects/`, `/pegboard/`, `/registry/` -> bypass landing selection entirely.

The recovery routes `/home/` and `/admin/` are reserved and cannot be assigned to projects.

## Root-mounted project shell

A normal LOOM project currently knows its nested project path. A root-mounted project cannot rely on `../../../` or on finding `/projects/<slug>/` in `location.pathname`.

Before enabling domain landing, LOOM should introduce a shared mount context similar to:

```js
window.LOOM_MOUNT_CONTEXT = {
  project: "green-beans",
  loomBase: "/",
  publicBase: "/",
  canonicalProjectUrl: "/projects/green-beans/app/",
  loomHomeUrl: "/home/",
  rootMounted: true
};
```

Project shells should resolve API, engine, Admin, profile, Home and asset URLs from this context instead of assuming one physical depth. Direct canonical project URLs continue to work with `rootMounted:false`.

The preferred implementation is one server-rendered/shared project-shell entrypoint used for both release projects and Instance Projects, rather than duplicating special root-only HTML.

## Admin experience

Global Admin settings should provide:

- **Base URL opens:** LOOM Home / Project
- **Landing project:** active project selector
- read-only preview of the resulting base URL
- read-only **LOOM Home recovery URL** (`/home/`)
- explicit confirmation before changing the base landing target
- a one-click **Restore LOOM Home to base URL** action

Project settings may show whether the project currently owns the installation base, but the authority remains global because only one target can own a given installation base.

## Future host/domain bindings

The first implementation only needs one landing target for the current installation base. The configuration should nevertheless be shaped so a later release can map exact hosts/subdomains to projects without redesigning persistence, for example:

- `example.com` -> Green Beans
- `shop.example.com` -> Store project
- `admin.example.com` -> no project binding / LOOM Home

Host matching must be exact and validated. Unknown hosts fall back safely to LOOM Home.

## Safety requirements

- Only a server-authorized Admin may change the landing target.
- The selected project must exist and be active at save time and request time.
- Missing/archived/broken selected projects fall back to LOOM Home, never to an error loop.
- `/home/` and `/admin/` remain permanent recovery paths.
- Mutable routing configuration lives under `instance/**`, never in the release tree.
- The router must not expose `instance/**` directly.
- No dynamic `.htaccess` mutation is required for ordinary target changes.
- Root routing must not interfere with Bridge equalization, Git, tombstones, Live Release Reload, or Instance Projects.
- Route responses should avoid stale target caching; host-sensitive responses should be safe for proxies/CDNs.

## URL and SEO behavior

When a project owns the base URL, the browser should remain on the base URL rather than being redirected to its nested `/projects/.../app/` implementation path. The project may emit a canonical URL for the base mount. Its nested canonical LOOM project URL remains operational for Admin/debug/recovery, but a root-bound project should avoid advertising duplicate canonical public URLs.

LOOM Home buttons inside a root-mounted project must point to `/home/`, not back to `/`.

## Recommended implementation sequence

1. Add persistent Domain Landing configuration + Admin API validation.
2. Add the permanent `/home/` LOOM Home route without changing current root behavior.
3. Introduce the mount-context abstraction and remove hardcoded relative-depth assumptions from shared project shells.
4. Add a generic server-side project entrypoint that can render release projects and Instance Projects at arbitrary mount paths.
5. Add the root front controller and generic `.htaccess` rewrite.
6. Add Admin controls (`loom.domain-landing`) with safe preview/restore behavior.
7. Test root and subdirectory installations, release projects, Instance Projects, Admin recovery, guest identity, Live Release Reload, cache busting, social/footer assets, and mobile paths.
8. Only then expose optional host/subdomain bindings.

## Acceptance tests before activation

A release implementing this standard is not complete until automated tests prove:

- default install still opens LOOM Home at the base URL;
- selecting Green Beans makes the exact base URL render Green Beans while the address bar stays at the base URL;
- `/home/` opens LOOM Home while the project owns `/`;
- project `LOOM Home` controls point to `/home/` while root-mounted;
- `/admin/` and `/admin/setup/` remain reachable;
- direct project URL still works;
- an Instance Project can be the landing project;
- archiving/removing the selected project safely falls back to LOOM Home;
- installation under `/LOOM/` behaves the same relative to that base;
- Live Release Reload and canonical version detection continue working;
- `instance/**` remains untouched by release hot drops.
