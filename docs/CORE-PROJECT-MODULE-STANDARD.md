<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Core Project Module Standard

LOOM supports platform-owned modules that run inside **every project** without being copied into each project's source tree.

## Location and scope

```text
/core-modules/<module-name>/manifest.json
/core-modules/<module-name>/action.js
```

The manifest uses:

```json
{
  "module": {
    "scope": "project",
    "entry": "action.js"
  }
}
```

`api/modules.php` discovers these modules before project-local actions and emits them into each project's runtime registry. The normal runtime fingerprint/poll cycle provides hot loading. Admin also discovers them as project settings when they expose `admin_settings`; their overrides remain project-scoped even though their source code is globally owned by LOOM.

## Contrast with global core modules

- `scope: global`: configures LOOM-owned global surfaces; it is not injected into project runtime.
- `scope: project`: LOOM owns one source copy, but the runtime injects it into every project.
- `/projects/<slug>/actions`: project-owned/reusable modules installed for one project.

## Compatibility

Historical reusable core modules such as Header Bar, Logo, Branding, User Profile, etc. may still be packaged inside individual projects/templates. They are reusable project modules, not automatically universal merely because their action IDs begin with `core.*`. Migration to universal project-core scope should be deliberate and dependency-safe.
