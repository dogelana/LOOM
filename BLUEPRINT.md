## v0.15.27 architecture note — controller-first capture invariants

Project-shell controllers that rehome or capture modules are startup-structural, not ordinary content. Action Runtime therefore establishes `presentation.role=controller` modules alongside region providers before concurrent content loading. Profile Dock accepts both framed (`data-loom-frame-for`) and direct (`data-module`) User Profile representations, preserving correctness when global/project/module title bars are disabled. A controller-readiness visibility guard eliminates transient profile leakage before the MutationObserver reparents the profile into its dialog bank.

## v0.15.26 architecture note — canonical sharing + durable referral provenance

Sharing is now a LOOM-native capability rather than a project-specific URL copy feature. Public/share UI delegates canonical link generation and referral persistence to a protected Instance Vault backend. The project runtime controller declares user actions so sharing remains inspectable in Action Registry/Pegboard. Referral ownership follows durable Guest Identity provenance and aggregates attached guest identities when a permanent account is created later.

The deployment gate is also a runtime scheduling boundary: timeout clocks for module import/factory/mount/activation pause while `LoomDeploymentGuard.active` is true. Intentional maintenance therefore cannot masquerade as a module fault. Core User Profile/Profile Dock implementations are release-managed to prevent old Instance Projects from pinning obsolete startup semantics.

## 0.15.25 presentation/runtime note

LOOM module chrome is opt-in by default: project modules inherit hidden title bars and disabled collapse unless a global/project/module override says otherwise. Module title text is a project-scoped per-module override, not a hardcoded module requirement. Background energy is a release-managed core visual and inherits project branding while using circular particle geometry. Home project discovery must distinguish loading, empty, and error states. Release convergence must compare against the actual running bundle version and may not surface stale loop-guard warnings after a healthy matching boot.

## v0.15.24 architecture note — ephemeral release reload markers

Live Release Reload may append reserved `_loom_release` and `_loom_reload` query parameters only to force one fresh document request after the canonical manifest advances. Those parameters are transport metadata, not application state. The newly loaded runtime must consume and remove them with History API replacement before normal use, preserving all unrelated URL state. Release comparison is against the effective runtime engine version, not a stale asset-build constant.

## v0.15.22 architecture note — deployment gate

A LOOM release has two distinct safety boundaries. The canonical `.loom-deployment.json` remains the **commit-last release authority**, while `.loom-deploying.json` is a short-lived operational lease owned by the deployment transport. The lease never becomes release content or persistent Instance state.

When the lease is active, LOOM must fail closed for application traffic with `503 Service Unavailable`, not authorization-shaped 403 errors. The browser must stop ordinary polling/retry loops, use only the dependency-free deployment-status probe, and perform one clean reload after the lease disappears. Lease expiry is mandatory so a crashed deployer cannot create permanent maintenance mode.

## v0.15.21 architecture note — direct Showcase branding

Showcase inherits canonical Project Identity colors without automatic color transformation. Headline 1 defaults to the project primary color and Headline 2 defaults to the project accent color. Local Showcase overrides may select primary, accent, or a custom color. Legacy shifted color-mode values are compatibility aliases only and do not alter hue.

## v0.15.20 architecture note — edge admin chrome + bounded concurrent boot

Admin Tools are canonical shared chrome: the drawer's viewport-facing gripper is a persistent control, while project quicklinks are derived from capability-filtered canonical Admin destinations. Project/LOOM settings and delegated-access reads are request-local memoized primitives. Runtime boot treats region modules as structural dependencies, then prepares ordinary modules with bounded concurrency; remote layout hydration and cached registry/brand/admin-state revalidation stay off the critical first-paint path.

## 0.15.19 Architecture Addendum

LOOM treats module presentation chrome as policy, not hardcoded UI. Global defaults are declared by `loom.module-presentation`; projects inherit or override them; individual modules may inherit or override the project. Effective policy is resolved server-side into each runtime descriptor and enforced by the runtime. Hiding a title bar removes collapse interaction to avoid invisible controls.

Bootstrap loading is fail-open. Registry discovery and each module stage are bounded so a noncritical endpoint or broken module cannot indefinitely hold the application loader. Loader progress identifies the module currently starting, not the module that previously completed. Social-link canonicalization is local string/URL parsing and must never perform destination health checks during project bootstrap.

Showcase is a Project Identity projection: live project name, dynamic fallback bio, project font, project colors directly, and a deterministic generated badge when no project Showcase image exists. Generated art is presentation-only and does not create persistent files.

## 0.15.18 Architecture Addendum

- Delegated authorization is capability-first and project-scoped; System Owner remains immutable. See `docs/DELEGATED-ACCESS-STANDARD.md`.
- Global navigation chrome owns Home/Profile definitions and Admin Tools presentation. The default Admin Tools surface is an auto-hiding side drawer filtered by effective capability.
- `core.seo.social` owns project SEO/social defaults, while public PHP gateways render metadata server-side for crawlers. See `docs/SEO-SOCIAL-METADATA-STANDARD.md`.
- HTML Framer URL capture creates a static local snapshot, not a live remote embed. See `docs/HTML-FRAMER-STANDARD.md`.

<!-- @loom-file release=0.15.27 revision=39 policy=package-priority -->
# LOOM — Modular Action Engine Blueprint v0.8.1

## v0.15.17 — Non-blocking project boot + live identity defaults

Project identity is cheap to resolve and remains canonical. Broad project discovery and user analytics must not sit on the critical module-activation path. Project-facing fallback copy is derived at read time from canonical identity, so renames propagate without copying text into modules. A missing project logo resolves to the static LOOM logo; explicit project assets always win.

## v0.15.16 — Project-first theming + canonical Admin chrome

Project Identity is now the default source for project-facing visual tokens. LOOM derives safe background/surface/border variations from the canonical primary/accent pair before project module overrides are applied, so modules inherit a coherent theme without sacrificing local control. Core LOOM attribution keeps its own defaults unless an Admin explicitly overrides them.

Admin navigation is also a shared engine concern. `LoomShell` exposes the canonical Admin destinations and project shells consume that same map, preventing developer-tool navigation from drifting page by page. Release-managed project modules continue to supersede legacy project-local implementation copies while legacy configuration may still contribute compatible defaults.

## v0.15.01 — Showcase core module

`loom.showcase` establishes a reusable project-content pattern: release-owned presentation code, project-scoped Admin configuration, canonical project-profile inheritance, and persistent uploaded media stored only beneath the Instance Vault overlay. The default summary source is the project profile `bio`; a Showcase-only override may be enabled without mutating that canonical profile, and Admin can return to inherited mode at any time.

## v0.8.1 Standard: module actions + semantic user actions + resilient presence

LOOM now treats user intent as a formal action layer rather than leaving user interactions as arbitrary module-specific logs. Each module manifest may declare `user_actions`. The owning module remains the stateful/system capability; user actions are semantic child capabilities rendered as compact Pegboard pills and recorded with normal `action.state` telemetry.

The runtime can bridge existing domain logs through `user_actions[].events` or modules may call `ctx.userAction()` / `ctx.runUserAction()` directly. Action IDs are stable across UI redesigns and intentionally describe intent rather than DOM implementation. LOOM does not standardize raw keystroke or pointer surveillance as user actions.

The v0.8 Pegboard automatically follows the newest live runtime for the selected client by default, while allowing an operator to disable follow mode and inspect history. System actions occupy a dedicated spine, modules are ordered in a compact grid, and user actions remain nested inside their module cards so the graph stays readable as projects grow.

See `docs/LOOM-ACTION-SCHEMA.md`, `docs/PEGBOARD-STANDARD.md`, `docs/PRESENCE-LIFECYCLE-STANDARD.md`, and `docs/MODULE-AUTHORING-CHECKLIST.md`.


## 1. Purpose
LOOM is a project-agnostic browser application engine where meaningful system and user capabilities are formal **Actions**. Green Beans is the first LOOM project, not a special case in the engine.

The cute vocabulary is presentation only: Pegboard = action graph, bulb = action node, string = relationship/dependency edge. Engineering internals remain Project, Module, Action, Event, Dependency, State and Session.

## 2. Action Dictionary as executable architecture
A leaf action module lives in its own folder and declares itself through `manifest.json`. The live Action Dictionary is derived from those manifests; it is not a second hand-maintained list that can drift from code.

A capability's folder/manifest existing means the capability exists. Its bulb is visible but dim until the selected session actually holds that state or fires that action.

## 3. Presence + Lifecycle is authoritative for illumination
LOOM v0.3.1 separates three truths:

1. **Capability exists** — module was discovered.
2. **Capability is presently held by this session** — action is in the session's active state snapshot and its bulb is illuminated.
3. **Capability executed at a moment in history** — an append-only event says what happened and when.

This prevents a module from looking active merely because its files exist.

### Heartbeat freshness
Every runtime sends a heartbeat containing its currently held stateful/pending actions. The server gives that heartbeat a bounded **freshness lease**. In v0.8.1 the periodic heartbeat runs every 5 seconds and the default freshness lease is 45 seconds. User activity, visibility transitions, and reconnects also trigger throttled immediate heartbeats so normal interaction remains fresh even when browser interval timers are throttled.

### Graceful close path
On a normal `pagehide`, LOOM uses `navigator.sendBeacon()` when possible to send a lifecycle-close payload containing the active state snapshot. The server records final `action.state = inactive` events, then `session.end`, and marks presence `closed`.

### Missed-heartbeat fallback
A browser may temporarily stop delivering timer callbacks, a laptop may sleep, or a network may hiccup. Therefore a missed heartbeat is **not authoritative evidence of unload**. When freshness lapses LOOM records `session.stale`, retains the last-known held-action snapshot, and marks the session `stale/resumable`.

LOOM does **not** emit inferred inactive events and does **not** end the session merely because freshness lapsed. The next valid heartbeat emits `session.resumed` and the same session continues.

This distinction is fundamental: heartbeat freshness answers “have we heard from the runtime recently?” while lifecycle close answers “did this runtime actually end?”

## 4. Lifecycle states
Global behavior classes:
- **system + stateful** — held runtime state, e.g. logo mounted;
- **system + transient** — one system operation then complete;
- **user + transient** — click/gesture that flashes;
- **user + pending** — workflow remains illuminated until completed/cancelled/failed.

Useful visual states: available, active, **stale/resumable**, transient pulse, failed, inactive/unloaded.

## 5. Parent actions and sub-lights
Global actions stay coarse enough to be readable. Internal work appears as sub-lights. `Load Core` therefore stays one bulb while discover/validate/ready are subordinate lights.

v0.3 also adds a built-in **Session Presence** bulb above Core. Its sub-lights represent heartbeat freshness, graceful close and stale-heartbeat detection.

## 6. Discovery and hot deployment
Browsers cannot securely enumerate arbitrary server folders. LOOM uses a server discovery endpoint to recursively find manifests. Runtime polling allows a dropped module folder to appear without editing a central registry. Removing a module unloads it and removes its capability.

## 7. Project isolation
`/projects/<slug>/` owns project metadata, app surfaces, assets and action folders. Engine/Pegboard code is shared. Multiple projects can coexist without destructive edits.

The canonical Green Beans logo is:

`projects/green-beans/assets/logo.png`

This path is authoritative. LOOM does not search the project root or sibling folders for same-named files. Modules request explicit project-relative paths through `resolveAssetPath()`.

## 8. Session-aware Pegboard
A Pegboard must answer **whose runtime are we looking at?**

Identity layers:
1. `clientId` — random, stable browser-install identity in localStorage;
2. `sessionId` — runtime/tab identity in sessionStorage;
3. `userId` — optional authenticated application account.

The selector groups by client/user and chooses the newest session by default. Historical sessions remain selectable.

Raw IP and invasive hardware fingerprints are deliberately not the primary identity mechanism.

## 9. Event history versus presence
Action history is append-only. Heartbeats are high-frequency liveness data and remain separate so they do not pollute analytics. Presence snapshots can immediately correct the current lights; event logs reconstruct what happened.

When freshness lapses, LOOM writes a one-time `session.stale` transition. That event records uncertainty without rewriting the session into a false ending. A later heartbeat records `session.resumed`.

## 10. Analytics foundation
Per selected session/client the Pegboard exposes:
- event count,
- unique actions touched,
- failures,
- session count,
- currently held bulbs,
- heartbeat freshness / stale duration,
- first/last timestamps,
- runtime/client/session IDs,
- graceful-close reason and separate heartbeat-stale state,
- basic privacy-safe browser context.

Future analytics can derive path frequency, action duration, abandoned pending workflows, dependency hotspots, next-action transitions and feature removal impact.

## 11. Green Beans proof module
`core.ui.load-logo` proves the lifecycle:
- module exists → dim bulb exists;
- runtime resolves and mounts `assets/logo.png` → bulb stays lit;
- normal close → immediate inactive/unload history;
- missed heartbeat → stale/resumable state while the last-known held snapshot is preserved;
- remove module folder → capability itself disappears after discovery refresh.

Its sub-lights resolve the asset, create the element and mount the logo.

## 12. Production storage
The demo uses JSONL + presence files. The included MySQL schema supports live/stale/closed presence, heartbeat freshness, end reason, active-action snapshots and inferred-event support for Hostinger deployment.

## 13. Security boundary
The Pegboard is operational telemetry. Production deployments must authenticate/authorize the viewer and restrict project/session visibility.

## 14. Next milestones
Dependency topological sorting/cycle detection; authenticated operator accounts; MySQL adapter; SSE/WebSocket telemetry; action-duration timing; permissions/capabilities; deployment snapshots/rollback; visual Action Dictionary editor; automated module contract tests; richer replay controls.

## Protected module/card ordering
Modules may declare `module.order` as a five-digit string such as `00010`. LOOM sorts discovery and activation by the effective numeric value and reflows direct module roots by that same value after mount. `core.ui.load-logo` owns the engine-reserved absolute-first slot `00000`; non-reserved modules requesting zero are clamped behind it. This prevents filesystem traversal order, action IDs, network timing, hot discovery, or third-party plugin metadata from visually jumping a normal module above the protected first module.


## Project lifecycle (v0.9.1)
LOOM now treats project archival/restoration as a first-class engine concern. Active projects live under `/projects`; archives live under `/archives/projects`; archive names are collision-safe `-old-N` slugs; server telemetry and browser-local project namespaces move with the project. The Green Beans `green-beans-reset-v1` migration is the reference implementation for replacing a project with a true scratch template without leaving old module folders behind after an in-place ZIP deployment.

## v0.9.2 Layout-region rule
Page composition is module-driven. Layout/container modules expose named LOOM regions and slots; content modules declare their target in `presentation.mount`. The engine resolves those targets through `ctx.mount()` and applies normalized layout hints while each module retains ownership of its own visual CSS. Green Beans currently demonstrates `header-bar` with `brand` and `utility` slots.


## v0.9.3 cache invariant
All LOOM-owned and project-resolved assets must use automatic cache-busting. Project assets resolve through `ctx.resolveAssetPath()` and receive a content-hash URL. Header branding uses independent bounded `brand-media` and `brand-text` slots so child asset intrinsic dimensions cannot control region geometry.

## v0.9.4 user identity invariant
A LOOM client identity is a stable browser-profile identity (`clientId`), not an authentication credential. Projects may install the reusable `core.user.profile` module to expose identity, persistent display username, and lifetime analytics. The server-side profile is keyed to the client ID; clearing browser storage creates a new client unless a future authenticated `userId` layer reconnects identities. Semantic user actions remain the basis of usage analytics; raw IP addresses are not identity keys.


## v0.9.5 branding invariant
Green Beans branding is modular: the mascot image belongs to `core.ui.load-logo` in the Header Bar `brand-media` slot, while the wordmark belongs to `core.ui.load-logo-text` in `brand-text`. Canonical wordmark styling is League Spartan, uppercase, `GREEN` = `#279E38`, `BEANS` = `#A9DF4F`. The logo asset remains `projects/green-beans/assets/logo.png` and is resolved through LOOM content-hash cache busting.


## v0.10.0 privilege/admin architecture
LOOM distinguishes client identity from authorization. The stable browser Client ID identifies a client, while Admin authorization additionally requires the server-issued HttpOnly administrator credential. Core UI modules are reusable project modules configured by manifest data. Administrator controls are declarative `admin_settings` and persist as project config overrides rather than source-code edits.


## v0.11.0 account + SQL persistence layer
LOOM now distinguishes browser clients from permanent users. Username uniqueness is enforced by server storage, permanent accounts add email/password sign-in, and one user ID may bind multiple client IDs. The first-client Admin bootstrap can be upgraded into a permanent Admin user account.

SQL persistence is optional at install time. LOOM runs in temporary/local mode until an administrator configures MySQL/MariaDB, initializes the schema, and migrates temporary records. Supported new writes then mirror to SQL while local files remain safety/cache copies.

Developer surfaces (Admin, Pegboard, Action Registry, telemetry history, archive mutations) are server-gated Admin tools.


## v0.11.01 bootstrap-loader architecture
LOOM supports a registry-discovered bootstrap Loader via `module.bootstrap.role = loader`. It is instantiated before ordinary project modules, reads effective branding from standard Logo/Logo Text descriptors, tracks module import/mount/activation completion, and dismisses after bootstrap. It is reusable and remains visible in Pegboard history as a normal core capability.


## v0.11.02 branding core
Document title/tagline/favicon are now owned by reusable `core.ui.branding`. Visible Header/Logo/Logo Text remain separate modules. Default favicon follows the current Logo module asset.

## v0.11.09 avatar extension architecture
LOOM User Profile owns identity media persistence. Project modules may extend `core.user.profile.avatar` with a default avatar and/or creator API. Project defaults are contextual fallbacks; explicit user choices (custom upload, LOOM default, or project default) are persistent. Generated project avatars are converted into ordinary LOOM custom avatars at apply time.

## v0.11.10 user administration contract
User/account administration is a protected LOOM platform surface. Project bans preserve the profile/account and all history while blocking project access. Project-visible data excludes banned subjects by default unless the administrator explicitly enables retained-data inclusion for that subject. IP history is metadata only and is never an authentication or identity mechanism.

## v0.11.12 — release-history separation
LOOM distinguishes project branding from engine release history. Project name/tagline/favicon are owned by `core.ui.branding`; platform changes are owned by `core.system.update-log` and sourced from the root changelog. LOOM Home also visually distinguishes administrator-only capabilities from normal user navigation.

## Integrity repair ordering
Identity recovery must obey relational parent-first ordering. `loom_users` is the authoritative parent for `loom_user_clients` and `loom_auth_sessions`; the Doctor must restore or identify the permanent user before writing those child rows. Foreign-key checks are never disabled during recovery.

## Ambient background extension contract
`core.ui.background-orbs` owns ambient background behavior. `core.ui.background-orbs.provider` is a project extension point limited to replacement orb artwork and a suggested glow color. This preserves reusable LOOM behavior while allowing project-specific visual identity.

## v0.11.20 identity boundary
A LOOM account is authentication/authority. A project identity is presentation/persona. Core and project plugins must consume the project identity for in-project username/avatar presentation and use User ID only for durable account ownership/authorization. The plugin authoring manual is now a required packaged platform document.


## v0.11.20 — LOOM platform branding and global settings
LOOM-owned surfaces now use the canonical procedural cube mark and clearer end-user terminology. Global LOOM core settings are distinct from project settings in Admin, and the LOOM platform update log lives on LOOM Home while project release logs remain project-scoped.

## v0.11.20 loader/update/profile boundaries
Project loading surfaces are project-first: project logo and logo text own the primary animation, while LOOM attribution remains restrained. Root LOOM release history is a Home concern; project release history is project-owned. LOOM global profile media may be intentionally copied from a project identity without merging the two identity scopes.

## v0.11.22 UI composition
Project content modules now support account-persistent collapse/expand state. LOOM Footer Bar owns project branding + LOOM attribution, and Orb Dock provides configurable footer quick-access orbs that can capture content modules such as Project Update Log.

## v0.11.23 Footer polish
Footer Bar is now a vertically stacked three-row surface with independently configurable Project Branding, More Tools, and LOOM Attribution rows. Orb Dock shrink-wraps to its actual orb content. LOOM collapse headers now visually fuse with expanded module cards.

## 0.11.24 project shell
LOOM project chrome now keeps `LOOM Home` publicly accessible, resolves a project-first baked-in logo independent of the optional visible Logo module, and supports the reusable Profile Dock controller. LOOM-owned pages use the same LOOM header/footer treatment.

## 0.11.25 global profile and shared shell
LOOM Home and LOOM-owned utility pages now share one platform shell and expose a reduced global-only LOOM Profile. Project identity controls remain project-scoped.

## 0.11.26 polish
Centered Orb Dock headings, repaired Pegboard shell layout/footer placement, fixed trusted project-default avatar copying, and standardized visible LOOM marks on the animated procedural cube.

## 0.11.27 safe-fit cube host
Animated LOOM cube hosts now separate the parent container size from the internal 3D cube size. The LOOM Home hero keeps a large dedicated box while rendering a smaller contained animation.

## 0.11.28 shell mark containment
Shared LOOM header/footer marks now keep their larger shell-owned containers while rendering smaller animated cubes inside them, preventing 3D perspective overflow.

## 0.11.29 Green Beans orb override
Green Beans now overrides reusable LOOM ambient Background Orbs with transparent native `🫛` emoji orbs. LOOM core remains generic and supports both emoji and image providers.

## 0.11.30 Green Beans orb glyph compatibility
Green Beans' emoji-style ambient orbs are now bundled project SVG assets instead of native Unicode glyphs, eliminating missing-emoji rectangles on older client systems.

## 0.11.31 favicon + module asset repair
LOOM-owned interfaces now share the static LOOM favicon, and module-relative asset resolution is a first-class runtime capability. This repairs the Green Beans bundled ambient orb provider so it no longer falls back to generic LOOM spheres.

## 0.11.32 Pegboard + visitor presence
Pegboard now uses a physically bounded shared-shell layout and no longer references removed legacy header DOM. LOOM Home records fresh client identities as temporary visitors before permanent account conversion.

## v0.12.00 — Durable Guest Identity Layer

Identity is now modeled as a graph rather than a destructive temporary→permanent conversion:

```text
Client / Device ──belongs to──> Guest Identity ──attached to──> Permanent User
       │                      │                              │
       │                      ├─ snapshots                  ├─ authenticated access
       │                      ├─ source project state       └─ multiple Guest Histories
       │                      ├─ recovery                   
       │                      └─ merge provenance
       └─ network/session observations
```

Guest Identity is durable. Multiple devices may produce multiple Guest Histories and attach to one user over time. Fuzzy evidence can help Administrators locate histories but cannot authenticate or merge them automatically. Source data is retained through attachment; active merged state is conflict-aware.

## v0.12.02 — Universal project-core modules + canonical project management

LOOM now has an explicit project-core tier: a module in `/core-modules` with `module.scope = project` is LOOM-owned once and injected into every project's runtime registry. Page Styling and the Hello World Auto Coder Test are reference implementations. Project-local modules can extend core capabilities through explicit extension contracts, but cannot shadow protected core IDs.

Project identity is canonical in `project.json`, not distributed across UI state. LOOM Home and LOOM Admin are two management surfaces over the same project metadata and `assets/logo.png`. The baseline project creator installs a reusable project shell/core stack while universal project-core modules remain globally sourced and hot-discovered.



## 0.12.04 Starter chrome + electric energy field
Header/Footer starter geometry is compact by default: Header = fit-content + left brand alignment; Footer = fit-content + centered project branding. `core.ui.background-orbs` remains the compatibility action ID but LOOM's native rendering is now a dense electric circuitry energy field (70 default particles) with occasional animated LOOM cube specials. Project providers may independently extend both the regular particle identity and the special particle identity; Green Beans demonstrates pea-pod regular particles + carrot special particle.

## Deployment authority layer

LOOM 0.12.04 formalizes deployment metadata as part of the platform architecture. `/.loom-deployment.json` describes every shipped file, its per-file revision, content hash, and authority policy. Runtime/server state and release-managed source are intentionally different classes of object. Deployment tools must resolve policy before version, and version before causal fallback. This prevents a fresh package timestamp or stale default data file from overwriting legitimate live state.


## 0.12.08 — Foundation Ten architecture

The platform now treats identity lineage, migration history, capability boundaries, design tokens, health, audit causality, replay capture and cache discipline as first-class core foundations. These are additive contracts intended to make later UI/QOL work safer rather than forcing the full 100-idea vision into one release.


## Production-safe release boundary (0.12.09)

LOOM release archives contain release-owned code/assets only. Persistent installation state is classified by deployment policy and intentionally omitted from release archives. The Bridge treats omission of server-priority/server-only paths as preservation, never as deletion. Green Beans base branding is release-managed; custom project uploads remain installation-managed.

## Canonical runtime attribution (0.12.10)

User-visible LOOM release labels are consumers of canonical release authority, not independent version sources. Project loaders, shell branding, and telemetry must resolve the canonical deployment release dynamically. A UI component must never preserve a historical release number merely because its own source file was unchanged in later releases.

## Instance Vault invariant (0.12.11)

Application releases and installation state are physically separated.

- `/instance/**` is mutable installation state and MUST NEVER appear in a release ZIP.
- `/instance.sample/**` is release-owned documentation/template material and contains no credentials or user data.
- Package code may write persistent files only through Instance Vault path helpers.
- Project release defaults are immutable; Admin project customization is stored as instance overrides.
- A deployment can replace every release-managed file without replacing an existing Instance Vault.

## Clean Instance Protocol baseline (0.12.13)

Pre-Instance storage layouts are not part of the LOOM architecture.

The permanent invariant is:
- release-owned application tree is replaceable,
- `instance/**` is persistent installation state,
- databases are persistent external state,
- `instance.sample/**` is examples only,
- mutable filesystem state may never be introduced outside `instance/**`,
- absence from a release package never implies deletion of persistent state.

## Visual quality invariant (0.12.14)

LOOM should ship attractive, responsive defaults before a project adds its own styling.
Admin-adjustable geometry must be bounded and responsively constrained so valid settings cannot make the default UI unusable.

Fit-content spacing controls express total added room; LOOM divides totals evenly across opposing sides.

## HTML Framer interoperability boundary (0.12.16)

Static HTML packages may be hosted as sandboxed dynamic modules, but LOOM must never misrepresent opaque frame internals as native actions/capabilities. Imported packages are Instance Vault state and remain outside release deployment.


## 0.15.00 project ownership boundary

Release-managed projects are source artifacts. Projects created by an installation are Instance Projects and MUST live beneath `instance/projects/<slug>/project/`. A release must never assume it knows every project that exists on an installation.

## 0.15.00 module-state authority

Manifest `enabled` is the package default. Admin enable/disable choices are installation state and override the default through the Instance Vault. Release updates may add/change module defaults but must not erase an Admin's persisted state.

## 0.15.00 source-control boundary

Git may own the replaceable release tree. Git must not own real `instance/**`. Bridge/Git deployment must preserve Instance Projects and all Instance Vault state.

## v0.15.02 — First-run identity and Administrator ownership

A new LOOM installation must establish human identity before exposing project Home. A browser with no guest profiles presents a first-run acknowledgement surface that explains guest-profile separation, accepts an optional initial username, and creates the first guest with the canonical LOOM default avatar. Blank usernames are resolved server-side into unique `LOOMUser-*` names.

Administrator authority is an explicit ownership transition, never a side effect of reading account/profile status. The only bootstrap path is a deliberate claim through Admin status with `claim=true`. The guided `admin/setup/` surface binds that claim to the currently selected LOOM identity and, when a permanent account is authenticated, persists Admin authority onto that account.

Shell navigation uses fixed semantic full-color emoji rather than font-dependent monochrome glyphs for the three common identity/navigation affordances: Home, Switch User and User Profile.

## v0.15.03 — Completed-deployment client convergence

LOOM clients may remain open while a release is deployed. Client convergence is therefore a platform concern rather than a deployment-transport concern. `loom.release.watch` observes only the canonical deployment commit marker: the deployment manifest fingerprint returned by `api/version.php`. Because compatible Deployer behavior commits that manifest last, clients ignore partially transferred release state and refresh only after canonical health is coherent.

The release watcher lives in the shared LOOM brand/runtime surface so Home, Admin, developer surfaces and project shells participate without project-specific code. It is globally configurable and default-on. A release transition emits `loom:release-will-reload` before cache-busted navigation so stateful modules can snapshot ephemeral drafts.

## v0.15.04 — Footer social-link composition

`loom.social-links` is a reusable project-scoped core capability that composes into the standard footer without moving project-specific social configuration into release-owned project modules. The module activates after the footer region exists, inserts its own row directly after the branding row, and remains visually absent when no links are configured.

Icon geometry is bundled from Font Awesome Free and rendered with `currentColor`; link configuration remains project module settings in the Instance Vault. The project-wide default social color is part of Project Identity, while the module may explicitly choose LOOM black or a manual override. This keeps project identity, module configuration, and package-owned vector assets separated by ownership.

## v0.15.05 — Directory entrypoints and responsive composition

Admin directory routing now explicitly permits both `index.php` and `index.html`, in that order. Package-owned subdirectories may therefore expose static entrypoints such as `/admin/setup/` without weakening `Options -Indexes` or changing the primary `/admin/` PHP console.

Collapsible module presentation owns the top edge of an expanded content card. Modules that draw their own card surface must integrate their top border/radius with the LOOM frame rather than visually nesting a second rounded header boundary. Showcase is the reference implementation.

Responsive composition remains an engine concern as well as a module concern: module-frame wrappers guarantee `min-width:0`/`max-width:100%`, project shells may wrap utility controls at phone widths, and individual modules must provide sensible single-column behavior where their content would otherwise become too narrow.

## v0.15.06 — Opt-in ambient modules and canonical Home release identity

A module manifest's `enabled` field is a default, not a permanent exclusion. Module Control may persist an explicit per-project/global state that overrides the manifest default. Discovery and Admin catalog code therefore retain disabled-by-default modules and evaluate `moduleStates` before deciding whether they run. Background Orbs and project orb providers are the first platform feature intentionally shipped opt-in under this rule.

LOOM Home does not treat a package-embedded version string as display authority. The visible release comes from the canonical `api/version.php` response. Static markup may show a neutral loading/unavailable state only; it must not claim an older release when canonical lookup fails.

Identity switching must expose guest creation regardless of whether the active browser context is guest or permanent-account based. Leaving a permanent account for a guest signs out the browser session but never deletes the permanent account.

## v0.15.07 — Mobile identity surface and future domain landing

Identity entry is a platform-level full-screen dialog. Mobile implementations must use dynamic viewport sizing, safe-area-aware padding, a bounded scrolling content region, and reachable touch actions across first guest, guest chooser, create guest, account login and permanent-account switch states.

Domain landing is planned as global server-side LOOM infrastructure with an Admin-facing core control. A project selected as the installation landing target may eventually render at the exact LOOM base URL while LOOM Home moves to the reserved `/home/` path. Mutable routing authority belongs under `instance/config/`; project shells must use an explicit mount context instead of assuming a fixed `../../../` physical depth. See `docs/DOMAIN-LANDING-STANDARD.md`.

## v0.15.08 — Domain Landing is active

The LOOM installation base is now a server-routed mount point. `loom.domain-landing` controls whether that mount point serves LOOM Home or one active project; mutable authority is persisted under `instance/config/domain-routing.json`. `/home/` is reserved permanently for LOOM Home. Root-mounted projects retain their canonical project shell as their resource base and receive explicit `LOOM_MOUNT_CONTEXT`, avoiding duplicated runtime shells and preserving module-relative paths. Release projects and Instance Projects share the same routing contract.


## v0.15.09 — Responsive module visibility

Module Control now owns a second project-scoped state dimension beside `enabled`: `hideOnMobile`. The value persists under each module's `moduleStates` record in the Instance Vault and is merged into the runtime descriptor's `presentation.responsive.hideOnMobile` value. Action Runtime uses a 767px viewport media query to suppress eligible modules without importing or activating them, and reconciles live when the viewport crosses the boundary.

Background Orbs are enabled by default again. The core orb module and project orb provider default to `hideOnMobile: true`; all other modules default false unless an Admin chooses otherwise.


## v0.15.10 — Framed Action Reader + Activity Explorer

HTML Framer's compatibility boundary now includes an Action Reader. Import-time analysis inventories stable interactive surfaces and JavaScript function/listener hints. Serve-time sandbox instrumentation observes UI interactions and posts them to the parent frame runtime, which emits normal declared LOOM user-action lifecycle events. Dynamic/unseen controls use generic framed action descriptors, preserving compatibility with applications that create UI after load.

This deliberately stops short of wrapping arbitrary foreign JavaScript functions. LOOM can reliably observe externally meaningful UI behavior without mutating foreign execution semantics.

Admin Activity Explorer is the historical observability counterpart to live Pegboard. It reads the existing project event stream plus privacy-scrubbed interaction replay and supports project, subject, session, category, date and text filters.


## v0.15.11 — Activity Explorer shell dependency correction

The Activity Explorer is a LOOM-owned Admin surface and therefore follows the same shared-shell bootstrap contract as other LOOM-owned surfaces. `LoomBrand` must be loaded before `LoomShell`; identity and global-profile helpers remain available before shell mount. This release corrects that ordering without changing observability semantics.


## v0.15.12 — Session liveness and recovery

Presence is now treated as a recoverable lease rather than a fragile timer. The runtime uses acknowledged, self-scheduling heartbeats with retry, lifecycle recovery hooks, and a longer mobile-tolerant lease. A transient pagehide may close the server-side runtime snapshot, but it does not permanently destroy the in-document tracker; restoration reopens the logical session with a fresh runtime ID and restarts interaction capture.


## v0.15.13 — Core branding inheritance and Instance Project virtual filesystem

Release-managed project-scoped core modules are the upgrade boundary for LOOM-owned behavior. `core.ui.load-logo-text`, `core.ui.footer-bar`, and `core.ui.orb-dock` now live under `core-modules/**`; project-local copies may contribute project-specific default configuration but no longer pin old implementation code inside an Instance Project.

Project identity now includes optional main/accent brand colors. Logo Text derives a balanced one/two-line wordmark from the canonical project name when no explicit module override exists. A project name may contain up to 140 characters.

Instance Project static files are served through a path-preserving virtual namespace so normal browser-relative semantics remain intact even though the persistent bytes live in `instance/projects/<slug>/project/**`.

Live Release Reload warns before refreshing. The default warning is sixty seconds and emits `loom:release-will-reload` immediately with the intended reload time so modules can save drafts or present their own UX.


## v0.15.14 — Canonical Project Branding

Brand data is now separated from presentation location. Project Identity owns canonical wordmark wording, main/accent colors, and canonical font. Header Wordmark, Footer, Loader, Home, and future brand-aware modules consume that identity. Location modules may hide or override their own rendering without mutating canonical project branding.


## v0.15.15 — Feedback and pre-project draft boundary

LOOM now distinguishes feedback behavior from feedback presentation. The engine owns the toast/tooltip/requirement mechanism; global settings own platform defaults; project `core.ui.toast-theme` only provides project presentation. Native modules receive the service through runtime context instead of implementing private notification systems.

Project creation drafts intentionally remain browser-local until creation succeeds. This preserves the Clean Instance Protocol: pre-project transient UI state does not manufacture an Instance Project or write mutable data outside a valid project ownership boundary.
