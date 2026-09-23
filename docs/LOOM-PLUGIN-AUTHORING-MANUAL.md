<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# LOOM Plugin Authoring Manual

**Applies from LOOM v0.11.20 forward.** This file is part of the LOOM distribution and should remain in every future release.

LOOM has two plugin scopes. They use the same action/module contract, lifecycle, manifest vocabulary, telemetry rules, presentation rules, and extension system. The only difference is **who owns the capability**.

- **LOOM core/global plugin** — reusable platform capability that should work in any project. Examples: Header Bar, User Profile, Loader, Background Orbs, Update Log.
- **Project plugin** — capability owned by one project. It may render project-specific UI or provide an extension that customizes a core LOOM plugin. Examples: Green Beans Avatar Provider, Green Beans Background Orb Provider.

The core rule is: **LOOM owns mechanisms; projects own project-specific content and optional providers.** Do not hard-code Green Beans, Dogelana, or any other project into the LOOM engine.

---

## 1. Folder placement

A deployed project discovers modules below its project `actions/` tree.

```text
projects/<project-slug>/
├── project.json
├── assets/
└── actions/
    ├── core/                 # installed reusable LOOM modules
    │   ├── ui/
    │   └── user/
    └── project/              # project-owned modules/providers
        ├── ui/
        └── user/
```

Reusable source copies live outside any one project:

```text
reusable-modules/
├── header-bar/
├── logo/
├── logo-text/
├── user-profile/
├── loader/
└── background-orbs/
```

A reusable module is installed into a project by copying/configuring it under that project's `actions/core/...` path. The reusable copy is the source template; the installed copy is what project discovery runs.

A project-specific plugin belongs only under `projects/<slug>/actions/project/...`. If a template should ship with it, mirror it under `templates/projects/<slug>/actions/project/...`.

---

## 2. Minimum plugin anatomy

```text
my-plugin/
├── manifest.json
├── action.js
├── action.css             # optional
├── assets/                # optional
└── README.md              # recommended
```

`manifest.json` is declarative. `action.js` is behavior. CSS/assets should remain local to the module when possible.

---

## 3. Universal manifest contract

LOOM currently uses schema version `1.3` for normal modules.

```json
{
  "schema_version": "1.3",
  "enabled": true,
  "action": {
    "id": "core.example.widget",
    "name": "Example Widget",
    "description": "One sentence describing the capability.",
    "kind": "system",
    "behavior": "stateful",
    "parent": "core.load"
  },
  "module": {
    "version": "1.0.0",
    "entry": "action.js",
    "styles": ["action.css"],
    "order": "00100"
  },
  "config": {},
  "presentation": {},
  "pegboard": {},
  "user_actions": [],
  "admin_settings": {}
}
```

### `action.id`

Must be globally unambiguous inside the project runtime. Use namespaced IDs:

```text
core.ui.background-orbs
core.user.profile
project.green-beans.avatar-provider
project.green-beans.shopping-list
```

Never reuse an ID for a different semantic capability.

### `kind`

- `system` — engine/module behavior.
- `user` is normally represented through `user_actions`, not by making the whole module a user action.

### `behavior`

- `stateful` — remains active while mounted.
- `transient` — performs work and completes.
- `pending` — explicitly held until completed/failed.

### `order`

Normal modules use five-digit ordering. `00000` is reserved for the first ordinary UI module in the current platform contract (typically Header Bar). Bootstrap modules such as Loader declare bootstrap behavior separately and do not steal the normal ordering slot.

---

## 4. JavaScript lifecycle

Every entry file exports `createModule(ctx)`.

```js
export async function createModule(ctx) {
  let root = null;

  return {
    async mount() {
      // Optional pre-activation setup. Do not assume visible UI yet.
    },

    async activate() {
      root = document.createElement('section');
      root.textContent = 'Hello from LOOM';
      ctx.mount(root);

      ctx.step('mount-ui', 'completed');
      await ctx.log('example.ready', { project: ctx.project });

      // Return cleanup when useful.
      return () => root?.remove();
    },

    async deactivate(detail = {}) {
      root?.remove();
      root = null;
    },

    async unmount(detail = {}) {
      root?.remove();
      root = null;
    }
  };
}
```

### Lifecycle reasoning

1. **Discovery** proves the capability exists.
2. **Import** loads module code.
3. **mount()** is optional preparation.
4. **activate()** makes the capability active.
5. **deactivate()** turns it off without pretending the module never existed.
6. **unmount()** releases final UI/listeners/resources.

A module must be safe to activate/deactivate again. Clean up timers, observers, DOM listeners, intervals, overlays, and global styles that the module created.

---

## 5. The `ctx` contract

Important context APIs include:

```js
ctx.project
ctx.identity
ctx.runtimeId
ctx.action
ctx.module
ctx.config
ctx.presentation
ctx.descriptor
ctx.order
ctx.orderDisplay
```

### UI and presentation

```js
ctx.mount(node)
ctx.resolveMountTarget()
ctx.applyPresentation(node)
ctx.query(selector)
ctx.create(tag, props)
```

Prefer `ctx.mount()` over manually appending into arbitrary project DOM. This lets region/slot presentation metadata remain portable.

### API access

```js
ctx.apiUrl('some-endpoint.php')
ctx.fetchApi('some-endpoint.php', options)
```

### Assets

For project assets, prefer:

```js
const url = await ctx.resolveAssetPath('assets/logo.png');
```

This participates in LOOM's content-hash cache-busting standard. `ctx.resolveAsset()` exists only for legacy compatibility.

### Identity changes

```js
await ctx.setUserLabel(projectUsername);
await ctx.setUserId(userId);
```

As of v0.11.20, visible usernames/profile pictures are project identities. Email/password/User ID are permanent-account data.

### Telemetry / Pegboard

```js
ctx.step('resolve-data', 'active');
ctx.step('resolve-data', 'completed', { count: 12 });
await ctx.log('example.loaded', { count: 12 });
```

Do not log continuous pointer movement, animation frames, hover positions, or other high-frequency decorative activity.

---

## 6. User actions

Interactive semantic actions belong in `manifest.json`:

```json
"user_actions": [
  {
    "id": "user.example.save",
    "name": "Save Example",
    "behavior": "transient"
  }
]
```

Run them through the runtime:

```js
await ctx.runUserAction('user.example.save', async () => {
  await saveSomething();
}, { objectId });
```

A user action should represent **intent**, not raw UI mechanics. "Save profile" is useful. "mousemove at x=417" is not.

---

## 7. Admin settings

A reusable module may declare safe editable config fields:

```json
"admin_settings": {
  "title": "Example Widget",
  "description": "Administrator-controlled presentation.",
  "fields": [
    {
      "id": "density",
      "label": "Density",
      "type": "range",
      "min": 0,
      "max": 100,
      "step": 1,
      "unit": "%"
    },
    {
      "id": "glowColor",
      "label": "Glow color",
      "type": "color"
    }
  ]
}
```

Supported field styles currently include `text`, `number`, `range`, `color`, and `select`.

Admin settings are persistent overrides. The Admin page does not rewrite module source code. At discovery time LOOM merges:

```text
manifest config defaults
        +
Admin project overrides
        ↓
effective ctx.config
```

Keep security-sensitive authority on the server. A manifest Admin field is configuration, not authentication.

---

## 8. Core module vs project provider

This is the most important modularity rule.

Suppose LOOM owns a generic avatar system. The project should **not fork User Profile** just to provide a bean avatar creator. Instead, User Profile exposes/consumes an extension contract, and Green Beans provides it.

Project provider:

```js
export async function createModule(ctx) {
  const provider = {
    label: 'Green Beans',
    async getDefaultAvatarUrl() { /* ... */ },
    async openCreator({ host, onApply, onCancel }) { /* ... */ }
  };

  return {
    extensions: {
      'core.user.profile.avatar': provider
    },
    async activate() {}
  };
}
```

Core consumer:

```js
const provider = ctx.getExtension('core.user.profile.avatar');
if (provider?.getDefaultAvatarUrl) {
  const url = await provider.getDefaultAvatarUrl();
}
```

This pattern is also used for project Background Orbs.

### Provider rule

A project provider should expose the **smallest meaningful project-specific surface**. For example:

- Background Orbs project provider supplies orb artwork + suggested glow color.
- LOOM Background Orbs core module owns animation, quantity, speed, Admin controls, reduced-motion handling, and rendering.

That division keeps LOOM reusable.

---

## 9. Extension IDs

Extension IDs are API contracts. Name them like stable interfaces:

```text
core.user.profile.avatar
core.ui.background-orbs.provider
```

Once projects depend on an extension ID, changing its shape is a compatibility change. Add fields compatibly when possible rather than silently redefining old fields.

Use:

```js
ctx.getExtension(extensionId)
ctx.getExtensionProviders(extensionId)
```

when one or multiple providers may exist.

---

## 10. Presentation metadata

Modules can declare where/how they fit into reusable UI regions rather than hard-coding coordinates.

Typical concepts:

```json
"presentation": {
  "region": "header-bar",
  "slot": "brand-text",
  "layout": {
    "order": 20,
    "offsetX": "0px",
    "offsetY": "10px"
  }
}
```

Use optical offsets only when mathematical centering is visibly wrong (for example, font glyph metrics vs mascot artwork). Do not use offsets to compensate for broken layout architecture.

---

## 11. Bootstrap modules

Some capabilities must run before ordinary project modules, such as Loader. They declare a bootstrap role rather than taking `00000`.

Conceptually:

```json
"bootstrap": {
  "role": "loader",
  "priority": 0,
  "countSelf": false
}
```

Bootstrap code must remain very small and avoid depending on modules that have not loaded yet.

---

## 12. Project identity rules (v0.11.20+)

Do not assume a permanent LOOM user has one universal public persona.

```text
LOOM account
├── User ID
├── email
├── password hash
├── privilege
└── project identities
    ├── Project A → username + avatar
    ├── Project B → different username + avatar
    └── Project C → different username + avatar
```

A plugin that displays a user's public name/profile picture inside a project should consume the **current project identity**, not the legacy `loom_users.username` account column.

Passwords are account authentication credentials and remain global to the account. Do not create project passwords unless a separate product requirement explicitly introduces project-level access secrets.

---

## 13. Database rules

Plugins should not open their own arbitrary database connection. Use LOOM server APIs/helpers and add tables through the shared schema when a durable platform capability requires them.

Requirements:

- `utf8mb4`
- additive/non-destructive migrations whenever possible
- `CREATE TABLE IF NOT EXISTS`
- foreign keys only when the parent lifecycle is well-defined
- snapshot before destructive/repair operations
- temporary-file fallback where the platform capability supports pre-database operation

Never disable SQL foreign keys merely to make a migration succeed.

---

## 14. Security rules

Client-visible state is never authoritative for Admin access.

- Admin authorization is validated server-side.
- Password hashes never leave the server.
- Admin password replacement revokes old login sessions.
- IP addresses are metadata, not identity proof.
- Bans follow LOOM subject identity, not IP.
- Direct URLs to Admin/Pegboard/Registry remain server-gated.

Project plugins must not invent a weaker authorization mechanism.

---

## 15. Caching and versions

LOOM fingerprints module manifests/entry/styles. Project assets should use content-hash cache busting. Do not tell users to hard-refresh as the normal update mechanism.

For this release line, package versions advance only the third segment:

```text
0.11.20
0.11.20
...
0.11.99
```

The module's own internal `module.version` can advance independently when its contract changes.

---

## 16. Recommended core plugin checklist

Before shipping a global/core plugin:

1. It does not contain project names or project-specific business logic.
2. It has a stable `action.id`.
3. It has deterministic cleanup.
4. Admin controls are declarative where practical.
5. User intent is represented through `user_actions`.
6. Asset URLs are cache-busted.
7. Pegboard steps describe meaningful internal lifecycle stages.
8. Reduced motion/accessibility behavior is respected for decorative animation.
9. Its source copy is under `reusable-modules/`.
10. Relevant project templates receive the installed copy if they are supposed to ship with it.

---

## 17. Recommended project plugin checklist

Before shipping a project plugin:

1. It lives below `actions/project/`.
2. Its `action.id` is project-namespaced.
3. It contains only project-owned behavior/content.
4. If it customizes a core feature, prefer an extension provider over forking the core module.
5. Removing the project plugin causes the core feature to fall back safely to LOOM defaults.
6. Project defaults can be overridden by Admin only through the generic core module when that is the intended architecture.
7. It does not modify unrelated global account or Admin data.

---

## 18. Small complete core example

`manifest.json`:

```json
{
  "schema_version": "1.3",
  "enabled": true,
  "action": {
    "id": "core.ui.example-banner",
    "name": "Example Banner",
    "description": "Reusable example banner.",
    "kind": "system",
    "behavior": "stateful",
    "parent": "core.load"
  },
  "module": {
    "version": "1.0.0",
    "entry": "action.js",
    "styles": [],
    "order": "00150"
  },
  "config": {
    "message": "Hello"
  },
  "admin_settings": {
    "title": "Example Banner",
    "fields": [
      {"id":"message","label":"Message","type":"text","maxLength":120}
    ]
  }
}
```

`action.js`:

```js
export async function createModule(ctx) {
  let node;
  return {
    async activate() {
      ctx.step('create-banner', 'active');
      node = document.createElement('div');
      node.textContent = ctx.config.message || 'Hello';
      ctx.mount(node);
      ctx.step('create-banner', 'completed');
      return () => node?.remove();
    },
    async deactivate() { node?.remove(); },
    async unmount() { node?.remove(); }
  };
}
```

---

## 19. Small complete project-provider example

```json
{
  "schema_version": "1.3",
  "enabled": true,
  "action": {
    "id": "project.example.background-provider",
    "name": "Example Background Provider",
    "description": "Project-owned artwork for the generic LOOM background system.",
    "kind": "system",
    "behavior": "stateful",
    "parent": "core.ui.background-orbs"
  },
  "module": {
    "version": "1.0.0",
    "entry": "action.js",
    "styles": [],
    "order": "00004"
  }
}
```

```js
export async function createModule(ctx) {
  const orb = new URL('./assets/orb.png', import.meta.url).href;
  return {
    extensions: {
      'core.ui.background-orbs.provider': {
        label: 'Example Project',
        glowColor: '#44FF99',
        orbAssets: [orb]
      }
    },
    async activate() {
      ctx.step('register-provider', 'completed');
    }
  };
}
```

The project supplies artwork. The core module supplies physics/animation/Admin controls. That is the intended LOOM architecture.


## Persistent project feature state (v0.11.20)

A project plugin that needs per-user persistent JSON state should prefer LOOM's generic `api/project-state.php` service instead of inventing its own account/database layer. Send the current `project`, a stable `moduleId`, and the LOOM `clientId`. LOOM resolves the permanent user when available, enforces project moderation/authentication, uses protected local JSON before SQL exists, and uses `loom_project_module_state` once SQL is active.

This keeps a project plugin portable: the plugin owns its state shape, while LOOM owns identity, authentication, persistence, and migration.

## Global profile vs project identity

Do not store login credentials in a project plugin. LOOM owns:

- permanent account: User ID, email, password hash, privilege;
- LOOM global profile: global username and global profile picture;
- project identity: whether username/profile picture inherit the LOOM profile or use project-specific overrides.

Project usernames are unique inside their project. A user may intentionally use the same username in multiple projects, including by inheriting the LOOM username.


## Global LOOM core settings modules

LOOM-owned configuration that applies across projects belongs under `core-modules/`, not inside a project action tree. A global core-settings manifest uses the same declarative `admin_settings.fields` vocabulary but is surfaced in **Admin → LOOM Settings**. Project manifests continue to appear in **Admin → Project Settings**.

Use global settings only for engine-owned behavior such as the LOOM animated mark, loader experience, or LOOM Home. A project extension may provide project-specific content, but it must not silently overwrite global LOOM settings.

### Project-scoped LOOM core modules

A module may be physically owned once by LOOM under `core-modules/` while still storing independent configuration for every project. Declare `module.scope = "project"` and provide a normal runtime `module.entry`. LOOM injects that module into every project registry and surfaces its `admin_settings` under **Admin → Project Settings**. Overrides are written to the selected project's protected Admin-settings record, not to global LOOM settings.

Use this pattern for platform capabilities that must exist consistently in every project but whose values logically belong to each project. Core-project IDs are protected from project-local shadowing. `loom.page.styling` is the reference implementation.

## Update logs

The root `CHANGELOG.md` describes LOOM itself and is shown on LOOM Home. Project release notes belong to `projects/<slug>/CHANGELOG.md` and may be rendered by a project-scoped update-log plugin. Never inject the LOOM platform changelog into a project content surface.

## Project release-history plugins
LOOM platform history and project history are separate namespaces. Root `CHANGELOG.md` is a LOOM Home concern. A project plugin that renders release notes must read only `projects/<project>/CHANGELOG.md` through `api/changelog.php?scope=project`.

## Avatar provider default asset metadata
When a project avatar provider supplies a stable default image, declare it in the extension contract so non-project LOOM surfaces can retrieve it safely:

```json
"extensions": {
  "core.user.profile.avatar": {
    "label": "Example Project",
    "default": true,
    "default_asset": "assets/default-avatar.png"
  }
}
```

The path is relative to the provider module folder. LOOM validates that the resolved file stays inside the project directory.

## Routing and module URL invariants (v0.11.21+)

A LOOM project must be safe to open through either:

```text
/projects/<project>/app/
/projects/<project>/app/index.html
```

Project slug discovery must not depend on a fixed number of trailing URL segments. Find the `projects` path segment and read the next segment, or pass an explicit `?project=<slug>` query parameter.

Module-registry `entry_url` and stylesheet URLs returned by the live API should normally be web-root-absolute. Static fallback registries may contain LOOM-root-relative paths such as:

```text
./projects/example/actions/project/example/action.js
```

The LOOM Action Runtime owns conversion of those fallback paths into absolute URLs from the LOOM installation root. Project modules must never prepend `engine/` or assume the dynamic import is resolved relative to the current page.

If the console shows either of these patterns:

```text
modules.php?project=projects
/engine/projects/<project>/actions/...
```

the project slug or module-root resolver is broken. Do not work around it by hardcoding server-specific `/LOOM/` paths inside individual project modules.

## Collapsible project modules (v0.11.22+)

LOOM automatically gives ordinary root-mounted `presentation.role = "content"` modules a compact collapse/expand shell. Collapse state belongs to the user + project rather than the module implementation, so feature authors do not need to build their own persistence.

Opt out only when the module must never be collapsed:

```json
"presentation": {
  "role": "content",
  "collapsible": false
}
```

The shell collapses to the module's manifest `action.name`. Do not encode UI state inside the name.

## Reserved project ordering

LOOM reserves these semantic positions regardless of a conflicting manifest order:

```text
00000  core.ui.header-bar
00010  core.user.profile
99999  project.system.update-log
```

Structural/provider modules may have earlier numeric orders without appearing as ordinary content. Project Update Log remains bottom-most if it is not captured by Orb Dock.

## Footer Bar

`core.ui.footer-bar` is the reusable project footer region. It owns the footer container, project-brand stack, `orbs` slot, and LOOM attribution. Projects should not recreate footer markup in `app/index.html`; provide only a `footer-root` mount region.

## Orb Dock and orb-compatible modules

`core.ui.orb-dock` can move ordinary content modules into footer quick access. A module may declare default Orb Dock behavior in presentation metadata:

```json
"presentation": {
  "role": "content",
  "orb": {
    "defaultCaptured": true,
    "required": false,
    "defaultEmoji": "🗒️",
    "label": "Project Updates"
  }
}
```

When `required` is true, Admin cannot disable capture while Orb Dock is installed. Emoji is optional. Without one, LOOM derives a two-letter badge: `Project Updates` → `PU`; `Projects` → `PR`.

Admin can orb any eligible content module through Orb Dock's `orb-manager` tool. Captured module source code does not change: LOOM moves the mounted module into the dock's modal content bank and restores normal behavior when Orb Dock is absent.

Emoji rendering must use the native glyph/image alpha. Apply visual shadows with `filter: drop-shadow(...)` for emoji/artwork rather than a rectangular background shadow.

## Footer row composition (v0.11.23+)

`core.ui.footer-bar` is a three-row layout region. Project modules should not manually position themselves into arbitrary footer coordinates.

- Branding is the first row.
- `core.ui.orb-dock` mounts into the second row through the `orbs` slot.
- LOOM platform attribution is the third row.

Footer rows are separately configurable. Quick-access content should shrink-wrap its actual orb count rather than requesting full-width layout unless an Admin explicitly changes the More Tools row to full width.

## Collapsible card integration (v0.11.23+)

LOOM owns the collapse title/header for ordinary content modules. Module authors may keep their normal rounded standalone card design; when LOOM wraps the module, the runtime flattens only the top two radii so the LOOM header and module surface visually become a single card.

## Built-in project shell and Profile Dock (0.11.24+)

The project top bar is part of the LOOM shell, not a project module. It must remain useful even when optional visual branding modules are removed.

Project shell brand resolution:

1. canonical `project.json -> branding.logo_asset`
2. compatible installed Logo module asset when available
3. animated LOOM cube fallback

A project plugin must not assume that removing `core.ui.load-logo` removes all project branding.

`core.user.profile-dock` is a reusable shell controller. It captures `core.user.profile` after that module mounts and moves the live module into a modal launched from the shell's `profile` slot. This changes presentation only; User Profile remains the authoritative identity module and owns all of its existing actions and persistence.

The LOOM Home button in a project shell is a normal navigation control and must remain visible to non-Admin users. Admin, Pegboard, and Action Registry controls remain separately gated.

## LOOM-owned pages and shared shell (0.11.25+)

New LOOM-owned utility pages must use the shared shell instead of recreating platform header/footer markup.

Load `engine/loom-shell.js` after `loom-brand.js`, `identity.js`, and `loom-global-profile.js`, then mount it against the standard `loomShellHeader` and `loomShellFooter` containers.

Project modules should not import the global shell. Projects have their own LOOM project shell contract; the global shell is for LOOM-owned pages such as Home, Admin, Pegboard, and Registry.

## Full-screen LOOM tools (0.11.26+)

A LOOM-owned full-screen application must compose around the shared shell rather than cover it. Use a bounded workspace between the shared shell header and footer. If the tool has its own toolbar controls, place them into the shared shell's navigation/control region rather than creating a duplicate platform header.

## Background Orb provider artwork (0.11.29+)

Project Background Orb providers may supply either native emoji (`orbEmoji` / `getOrbEmoji`) or project-owned image artwork (`orbAssetUrl` / `getOrbAssetUrl`). Keep motion, density, speed, and glow strength in LOOM core rather than duplicating them in the project provider.

## Module-relative assets (0.11.31+)

Modules may resolve files bundled inside their own folder using:

```js
await ctx.resolveAssetPath('assets/icon.svg', 'module');
```

LOOM prefixes the discovered module `folder` and routes the final explicit relative path through the normal project-root asset resolver. This is the preferred method for portable module-owned artwork.

## LOOM-owned favicon

LOOM-owned global interfaces use the static `/assets/loom-logo.png` favicon. Animated procedural LOOM marks are used in visible page chrome; the browser favicon remains static.

## Guest-safe state and merge behavior (v0.12.00+)

LOOM treats unauthenticated Guest data as durable. Project modules using generic project state must use stable IDs for independently meaningful list objects whenever possible. During Guest History attachment, lists of objects with stable `id` fields can be unioned and recursively merged instead of allowing a newer whole-state blob to erase another device's progress.

Do not implement module cleanup code that deletes client-owned state merely because a client becomes attached to a permanent user. Source Guest state is provenance and is retained by the LOOM identity layer.

When a module has domain-specific conflict semantics that cannot be safely expressed by the generic recursive merge rules, preserve both values and surface the conflict rather than silently discarding either history.
