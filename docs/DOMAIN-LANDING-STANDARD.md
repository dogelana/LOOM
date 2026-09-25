<!-- @loom-file release=0.15.49 revision=3 policy=package-priority -->
# LOOM Domain Landing / Project-at-Root Standard

Status: **implemented and active in LOOM 0.15.08**.

## Behavior

LOOM may be installed at a domain root or a subdirectory. An authorized Admin chooses what the exact installation base opens:

- **LOOM Home** (default), or
- one active **release project** or **Instance Project**.

LOOM Home is permanently available at the reserved `/home/` path relative to the installation base.

Examples:

- `https://example.com/` -> selected project
- `https://example.com/home/` -> LOOM Home
- `https://example.com/admin/` -> LOOM Admin
- `https://example.com/LOOM/` -> selected project when LOOM is installed at `/LOOM/`
- `https://example.com/LOOM/home/` -> LOOM Home

The project is rendered **at the installation base URL without a visible redirect** to `/projects/<slug>/app/`.

## Ownership and persistence

The global core capability is `loom.domain-landing`. Its mutable routing authority is stored at:

`instance/config/domain-routing.json`

Release ZIPs and Git never own this file. Hot drops therefore cannot silently change which project owns the installation base.

Default state is always LOOM Home until an Admin explicitly chooses a project.

## Server routing

The package ships a stable root `index.php` front controller. `.htaccess` keeps `index.php` ahead of `index.html` through the normal `DirectoryIndex` rule. Admin changes **do not rewrite `.htaccess`**.

At an installation-base request the front controller:

1. reads persistent Domain Landing configuration;
2. honors the global module enable/disable state;
3. validates that the selected project is still active;
4. serves LOOM Home or the selected project's shell;
5. falls back to LOOM Home if the selected project is unavailable.

The permanent `home/index.php` route serves the ordinary LOOM Home shell with an installation-base `<base>` so Home works both at a domain root and below a subdirectory.

## Root-mounted project shell

The front controller reuses the project's real project shell. It injects a canonical project `<base>` plus:

```js
window.LOOM_MOUNT_CONTEXT = {
  project: "example-project",
  loomBase: "/",
  publicBase: "/",
  homeUrl: "/home/",
  canonicalProjectUrl: "/projects/example-project/app/?project=example-project",
  rootMounted: true,
  source: "release"
};
```

This preserves existing project/module relative-path behavior while letting the shell resolve project identity from explicit mount context instead of requiring `/projects/<slug>/` in `location.pathname`.

Instance Projects use the package-owned `projects/_instance/app/` shell and receive their Instance Project slug through the same context.

## Permanent Home links

LOOM-owned project, Admin, Admin Setup, Pegboard and Action Registry links now target the permanent `/home/` alias. This prevents a “LOOM Home” button from sending the user back to the landing project when that project owns the base URL.

## Admin experience

Admin -> **LOOM Settings -> Domain Landing** provides:

- Base URL opens: **LOOM Home / A LOOM project**
- active project selector
- installation-base preview
- permanent LOOM Home URL preview
- Save Base Landing
- Restore LOOM Home to Base
- quick links to open both destinations

Changing routing requires an explicit browser confirmation. The server validates the selected project again before saving.

## Module Control behavior

Disabling `loom.domain-landing` in Module Control immediately makes the installation base resolve to LOOM Home. The saved project preference remains in the Instance Vault so re-enabling the module can restore it.

## Safety behavior

- only server-authorized Admin API actions may change routing;
- `/home/` and `/admin/` remain recovery paths;
- missing/archived target projects fall back to Home;
- no `instance/**` path is exposed directly;
- routing changes do not mutate package files or `.htaccess`;
- root responses are `no-store` to avoid stale landing targets;
- release deployment, Git, tombstones, Live Release Reload and Instance Vault ownership remain unchanged.

## Current scope

0.15.08 implements **one landing target for the current LOOM installation base**. The persisted schema keeps a `hostBindings` field reserved for a future exact-host/subdomain mapping release, but host-specific bindings are not yet exposed in Admin.
