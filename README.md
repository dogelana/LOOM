<!-- @loom-file release=0.15.04 revision=20 policy=package-priority -->

## v0.15.04 - Social Links

LOOM now includes the project-scoped **Social Links** core module (`loom.social-links`). It injects optional social/web icons directly into the standard project footer after project branding and before **More Tools**. No links are configured by default, so existing projects gain no visible footer clutter until an Admin opts in.

Admin can paste Facebook, Instagram, YouTube, TikTok and Website URLs plus one Custom Link. The custom link uses a curated icon picker from the same Font Awesome Free family as the bundled platform artwork. Every icon is rendered from local SVG path data with transparent backgrounds and one shared color. The color source can be **Project default**, **LOOM default (black)** or a manual color. Project Identity now owns the project's default social/footer icon color.

The icon subset is bundled locally from Font Awesome Free 6.7.2, including its license notice, so project footers do not depend on a third-party CDN.

The obsolete Windows `launch.bat` local-development helper is formally retired in this release and is listed as a package tombstone so compatible Deployer runs remove stale server copies.

## v0.15.03 - Live Release Reload

Open LOOM browser sessions can now detect a completed hot deployment and move themselves onto the newly committed release automatically. The watcher is driven by the canonical `api/version.php` endpoint and a fingerprint of `/.loom-deployment.json`. Compatible deployers already commit that manifest last, after release-managed files verify, so a browser does not refresh merely because one new file arrived early.

The global **Live Release Reload** core module (`loom.release.watch`) is enabled by default and is configurable from Admin. It polls every 8 seconds by default, confirms a changed deployment twice, shows a short LOOM update notice, then performs a cache-busted `location.replace()` while preserving the current path and query parameters. Focus/visibility changes also trigger a check.

The watcher also detects same-version hot patches through the deployment fingerprint. It dispatches `loom:release-will-reload` immediately before refresh so modules with transient draft state can persist it if desired. A 90-second loop guard prevents repeated refreshes if an upstream cache serves stale application code.

**Bootstrap note:** sessions that were already open on LOOM 0.15.02 do not contain the watcher yet, so the 0.15.03 deployment itself cannot force those old pages to refresh. Once a person loads 0.15.03, subsequent LOOM deployments can refresh that open session automatically.

## v0.15.02 - First-Run Identity + Guided Admin Setup

LOOM now gives a truly new browser installation an explicit first-guest onboarding screen before the project Home surface appears. The person can choose the initial username or leave it blank for a unique LOOM-generated username; guest profiles start with the canonical LOOM default avatar, and the screen explains how additional people can create separate guest profiles later through **Switch User**.

Administrator bootstrap is now explicit on every code path. Passive account/profile status requests can no longer silently create the first Admin. A guided `admin/setup/` route shows the currently selected LOOM identity, performs the one-time Admin claim only after acknowledgement, and can immediately turn that authorized guest identity into a permanent Admin account. Visiting `admin/` on an installation with no Admin redirects to the guided setup.

LOOM Home, Switch User, and User Profile shell controls now use fixed full-color emoji icons with color-emoji font fallbacks, avoiding missing-glyph icon rendering. Project branding strips on LOOM Home shrink to their content instead of stretching across the full project card.

## v0.15.01 - Showcase Core Module

LOOM now includes `loom.showcase`, a universal project-scoped core module for a project image and summary. Showcase follows the project's canonical LOOM bio by default, supports a project-local Showcase bio override, and provides a one-click **Use Project Bio** reset. Admin can drag/drop or upload the Showcase image; the image is stored in the persistent project overlay under `instance/**`, so release/Git updates cannot overwrite it.

## v0.15.00 - Project Studio + Module Control + Git Era

LOOM Home can now create persistent **Instance Projects** directly. They are scaffolded from the baseline template into `instance/projects/<slug>/project/`, served through the generic Instance Project runtime, and are therefore insulated from future release ZIPs.

Admin Project Settings now contains a unified Module Control Center. Global LOOM modules, project-core modules, project modules, and HTML Framer modules can be enabled/disabled immediately without editing manifests. Toggle state is stored in the Instance Vault.

The first Administrator is no longer claimed silently. A first-run consent screen explains bootstrap ownership and requires explicit agreement before Admin authority is created.

The soft-login guest/profile system is surfaced again through a visible **Switch User** control in LOOM chrome.

LOOM is now Git-compatible: `.gitignore`, `.gitattributes`, and a Git workflow standard define Git as release-source control while the Instance Vault remains outside repository history.

## v0.12.16 — HTML Framer

LOOM now includes the project-scoped **HTML Framer** core module.

Admin can drop multiple static HTML ZIP packages into Project Settings. Each published package becomes its own sandboxed, dynamically registered LOOM module while the source ZIP and extracted files remain safely inside the project's Instance Vault.

HTML Framer preserves valid local relationships, repairs uniquely resolvable path mistakes, auto-attaches otherwise orphaned CSS/JS roots, and asks Admin to choose when multiple HTML entrypoints are genuinely ambiguous.

See `core-modules/html-framer/README.md` and `docs/HTML-FRAMER-STANDARD.md`.

## v0.12.15 — Retired Cleanup + Green Beans Defaults

- Reinforces permanent removal of the retired Hello World autocoder test module.
- Green Beans default bio is now: Green Beans is the family food app that turns "what are we doing for dinner?" into a plan, a shopping list and a table full of happy people. Say what you need, approve what you like and share the good stuff. Plan. Eat. Share.
- Project shell LOOM Home now has a home icon and is horizontally centered to match User Profile.
- Profile Dock shell-button geometry is aligned with the LOOM Home control.
- Clean Instance Protocol remains unchanged.

## v0.12.14 — Polished Footer + Default UI

- Permanently removes the Hello World autocoder test module.
- Footer project branding remains centered and fit-to-content by default.
- Fit-content branding now has two guarded total-padding controls: 100px extra width by default (50px each side) and 20px extra height by default (10px top/bottom).
- Both fit-content padding controls range from 0–200px and are shown only while the branding row is in fit-content mode.
- Compact screens automatically constrain extreme padding so Admin settings cannot destroy responsive layout.
- The LOOM design system, shell chrome, project shell, Home surface, identity entry, and footer receive a visual-only polish pass with no workflow/functionality changes.
- Clean Instance Protocol remains unchanged: no real `instance/**` state is shipped.


## v0.12.10 canonical runtime version attribution

LOOM UI surfaces must not hard-code historical release numbers. The project loader now resolves its displayed version from the canonical deployment/version authority, with engine configuration only as a fallback. Loader telemetry records the same resolved value. Static cache keys for current runtime surfaces were advanced to 0.12.10, and stale footer/version fallbacks were removed.

## v0.12.09 production-safe releases

Release ZIPs are now code-first: live users, guest records, sessions, logs, presence, replay data, project state, database credentials, settings, uploaded avatars and similar installation-owned payloads are not shipped. Bridge preserves those paths on existing installations. Green Beans now uses the user-provided base logo with canonical colors `#279E38` and `#A9DF4F`.

## v0.12.05 stable branding + corrected footer defaults

- Whole footer starter default restored to full width.
- Only the project-branding row defaults to fit-content and centered.
- Header wordmark fitting no longer observes/resizes itself.
- Logo host dimensions are CSS-responsive and stable across the mobile breakpoint.

# LOOM v0.15.03 — Modular Application Engine

## v0.12.13 — Clean Instance Protocol

LOOM now treats the Instance Vault as a permanent protocol rather than a migration feature.

- `/instance/**` is the only mutable installation-owned filesystem boundary.
- Release ZIPs never contain `/instance`; they contain `instance.sample/` only.
- There is no pre-Instance rescue/import path.
- Missing `/instance` means a fresh vault is created.
- Existing `/instance` means all local durable state continues in place.
- Database credentials live only at `instance/config/database.json`.
- Filesystem fallback state lives only beneath `instance/data/`.
- Project customization and uploaded project assets live only beneath `instance/projects/`.
- Project release metadata is `project.default.json` and is package-owned.
- LOOM fails loudly if the Instance Vault cannot be written instead of silently writing state into release files.

This is the baseline storage protocol for future LOOM releases.

LOOM is a modular browser application engine with hot-discovered project capabilities, project/global core modules, identity, Admin controls, Action Registry/Pegboard observability, project lifecycle management, and optional durable SQL persistence. Green Beans remains the reference/starter project while LOOM itself carries the platform architecture.

> Historical sections below are retained because they document the evolution and contracts that current modules still depend on.

v0.8 standardizes **semantic user actions** as first-class LOOM telemetry alongside system actions. v0.8.1 fixes presence semantics so heartbeat freshness can never masquerade as a completed session. Green Beans now declares user-action capabilities in every module manifest; interactive modules automatically emit timestamped `action.state` events for meaningful user operations such as create, edit, delete, buy, consume, schedule, and complete-session.

## Run it

```bash
php -S 127.0.0.1:8788
```

Open `http://127.0.0.1:8788/`, then open Green Beans and the Pegboard in separate tabs.

## v0.8 / v0.8.1 highlights

- Pegboard automatically follows the **newest live session** for the selected client/user by default.
- Manual historical selection turns auto-follow off; it can be re-enabled with the checkbox.
- Same-browser session changes are detected immediately through LOOM's project event bus; server polling remains the cross-device fallback.
- Project modules are arranged in a compact ordered grid instead of one huge uncontrolled branch.
- User actions render as **nested pills inside their owning module card**, not as dozens of giant graph nodes.
- User-action pills light while active, pulse when completed, show execution counts, remain individually inspectable, and retain full timestamped event history.
- The Pegboard canvas disables text selection while panning/clicking, fixing the browser-wide blue-selection effect.
- The Action Registry now shows both module/system actions and their declared user actions.
- `module.schema.json` v1.1 defines the portable `user_actions` contract for future LOOM projects.

## Green Beans module order

`00000` Logo, `00001` Logo Text, `00005` Object Hub, `00010` Shopping List, `00020` Meals, `00030` Recipes, `00040` Scheduling, `00050` Bought / History, `00060` Consumption, `00070` Nutrition, `00080` Research, `00090` Home Stock, `00100` Preferences, `00110` Collections, `00120` Sessions, `00130` Explore.

`00000` remains engine-protected for `core.ui.load-logo`.

## User-action rule

LOOM records **semantic user intent**, not raw surveillance-style input telemetry. A module should register actions such as `shopping.item.add`, `recipes.recipe.create`, or `scheduling.object.place`. It should not emit one action for every keystroke or mouse movement.

Existing Green Beans modules use two supported paths:

1. **Manifest event bridge** — a `user_actions[].events` entry maps an existing domain log event such as `meal.created` to a standardized user action.
2. **Direct runtime action** — `ctx.userAction(...)` / `ctx.runUserAction(...)` handles user interactions that do not naturally produce a domain log event, such as calendar navigation or switching Consumption views.

See `docs/LOOM-ACTION-SCHEMA.md`, `docs/PEGBOARD-STANDARD.md`, `docs/PRESENCE-LIFECYCLE-STANDARD.md`, and `docs/MODULE-AUTHORING-CHECKLIST.md`.

## Presence + lifecycle

Presence now separates **heartbeat freshness** from **session lifecycle**:

- capability exists → dim/available;
- stateful action held by a fresh live runtime → illuminated;
- transient user action → action pill lights/pulses;
- graceful close → final inactive lifecycle states are recorded and the session is actually closed;
- missed heartbeat → presence becomes **stale/resumable**, with the last-known held-action snapshot preserved;
- next successful heartbeat → the same session resumes immediately;
- historical session → event history is replayable without pretending the runtime is live.

A heartbeat timeout is never treated as proof that a user left. User pointer/keyboard/form/touch/focus activity, visibility changes, and reconnects also trigger immediate throttled heartbeats, so an actively-used app stays fresh even when browser interval timers are throttled.

Default heartbeat: 5 seconds. Default freshness lease: 45 seconds.

## Storage

The demo uses JSONL telemetry and small presence files for simple PHP hosting. Green Beans domain data remains in the shared Object Hub local model for this browser demo. `database/mysql-schema.sql` remains the production migration direction.

## Documentation requirement

Every LOOM push that changes action semantics, lifecycle behavior, module schema, Pegboard behavior, or telemetry must update the relevant documentation and changelog in the same package.

See `CHANGELOG-v0.8.1.md` for the heartbeat-resilience fix, `CHANGELOG-v0.8.md` for the user-action release, and prior changelogs for earlier milestones.


## v0.9.1 Project Archive + Green Beans scratch reset
LOOM Home now has Active and Archived project tabs, archive/restore controls, collision-safe `-old-N` naming, and a one-time Green Beans reset migration. Deploying this build over an existing LOOM install will archive the current `green-beans` project first and then install a clean Green Beans project containing only the Logo and Logo Text modules. See `docs/PROJECT-LIFECYCLE-STANDARD.md`.

## v0.9.2 — Module Layout Regions
LOOM modules can now declare portable presentation rules. The Green Beans blank project demonstrates this with a standalone Header Bar region (`00000`) and separate Logo (`00001`) and Logo Text (`00002`) child modules mounted into its `brand` slot. Future modules can independently mount into the header's `utility` slot or define additional regions without hard-coding page structure into the app shell.


## v0.9.3 — Bounded Header + Automatic Cache Busting
The active Green Beans scratch project still contains only Header Bar, Logo, and Logo Text. Header branding now uses bounded media/text slots, and LOOM automatically versions project assets by content hash plus module code/styles by fingerprints. See `docs/CACHE-BUSTING-STANDARD.md`.

## v0.9.4 — Reusable User Profile
Green Beans now includes the reusable LOOM `core.user.profile` module. It gives the current browser client a user-controlled persistent display username and exposes current-project plus all-LOOM usage analytics and session history. The stable client ID persists in browser localStorage; the username profile is also saved server-side against that client ID. This is not yet an authenticated cross-device account system.


## v0.9.5 — Canonical Green Beans Branding
The active Green Beans project and its scratch template now use the latest mascot logo. The stacked wordmark uses League Spartan (all caps), with `GREEN` in `#279E38` and `BEANS` in `#A9DF4F`.


## v0.9.6 — Wordmark fit
The Header Bar wordmark now scales as a two-line unit against both the brand-text slot width and height, preventing the BEANS line from being clipped.


## v0.9.7 — Wordmark alignment
The brand-text slot now matches the logo slot vertically, and the entire two-line League Spartan wordmark is centered as one unit with safe top/bottom breathing room.


## v0.9.8 — Optical alignment
LOOM modules can now declare presentation `offsetX` / `offsetY`. Green Beans uses this to optically align the visible League Spartan wordmark with the mascot rather than relying only on mathematical box centering.


## v0.10.0 — LOOM Admin
LOOM now has a protected `/admin/` page. The first stable LOOM client on a fresh installation becomes the initial administrator and receives a secret HttpOnly browser credential. Core reusable modules can expose validated administrator controls through manifest `admin_settings`; overrides are persisted separately from module source.


## v0.11.0 — Accounts + SQL Persistence
LOOM now supports unique usernames, optional email/password accounts, cross-browser User IDs, Admin-only developer pages, and an Admin-guided MySQL/MariaDB setup. Until SQL is connected, current state uses protected temporary local/server files; after initialization and migration, SQL is the durable persistence layer.

LOOM Admin also gains reusable Header width/branding-position controls, 2× logo scaling, and Logo Text font sizes up to 144px.

Release history is now maintained only in `CHANGELOG.md`.


## v0.11.01 — Reusable Loader
Projects can install `reusable-modules/loader` as a LOOM bootstrap module. It displays project branding while normal modules load and reports live module readiness progress. Bootstrap Loader is outside normal five-digit module layout ordering, so Header Bar remains `00000`.


## v0.11.02 — Project Branding
LOOM now includes a reusable Project Branding module for clean project-name titles, optional taglines, and logo-derived/custom favicon management.

## v0.11.03 identity migration
Existing LOOM browser usernames from builds before permanent accounts are automatically bridged into the validated server profile before account creation. This preserves the user's established identity while still enforcing global username uniqueness.

## v0.11.09 — profile avatars and true fit-content header
Header Bar `fit-content` now actually shrink-wraps its branding. LOOM User Profile now owns a reusable avatar pipeline with a native default, compressed upload storage, and optional project extensions. Green Beans supplies a default bean avatar plus a ten-color creator.

## v0.11.09 — Green Beans avatar artwork
The Green Beans project avatar extension now uses the supplied canonical Default Avatar, String Bean, and Bean Pod PNG artwork. Its creator currently exposes only Bean Type and Background Color.

## v0.11.09 — Green Beans avatar headwear
The Green Beans project avatar creator now supports Bean Type, Headwear (None/Ball Cap), and Background Color. Ball-cap variants use the supplied project artwork for each bean body type.

## v0.11.09 — Headwear preview icon refinement
The Green Beans avatar creator still supports Bean Type, Headwear, and Background Color, but the Headwear selector now uses dedicated trait icons: a universal red X for None and a close-up ball-cap preview for Ball Cap.

## v0.11.10 — User administration and moderation
LOOM now captures IP history as transparent network metadata, exposes a protected Admin Users console, supports confirmed account-field changes, and provides project-scoped soft bans with optional retained-data inclusion. IP is never used as authentication or ban identity.

## v0.11.12 — Admin clarity + Update Log
LOOM Home now clearly labels authenticated administrator sessions and visually separates Admin-only controls from ordinary project actions. Release history has moved out of project taglines and into the reusable `core.system.update-log` module backed by the single root `CHANGELOG.md`.

## v0.11.13 — database integrity and Admin repair
Permanent Admin account privilege is now the authorization source of truth. The new Database Integrity Doctor can scan, snapshot, and safely reconcile known identity mapping problems without deleting application data.

## v0.11.14 — foreign-key-safe identity recovery
The Integrity Doctor now restores or safely adopts the permanent `loom_users` parent record before repairing any client/auth/Admin child relationship. MySQL foreign keys remain enabled and are treated as a safety mechanism, not bypassed.

## v0.11.16 — Branded Home and startup performance
LOOM Home now renders standardized project branding derived from the reusable Logo and Logo Text modules. Startup telemetry is background-queued, module files are preloaded, and the bootstrap Loader has a shorter intentional dwell.

## v0.11.16 — Background Orbs
LOOM projects can now use the reusable Background Orbs core module. Projects may replace the orb artwork and suggested glow through an extension; Green Beans supplies bean-shaped orbs by default. Admin can switch between project and LOOM defaults and control volume, speed, glow strength, and glow color.

## v0.11.20 — Project identities
Permanent LOOM account credentials are now separated from project personas. Username and profile picture persist per project; email/password/User ID/privilege persist globally. The distribution now permanently includes `docs/LOOM-PLUGIN-AUTHORING-MANUAL.md` for both core/global and project module authors.

## v0.11.20
LOOM now separates global profile identity from project overrides, and Green Beans includes its first standalone persistent feature module: Shopping List.


## v0.11.20 — LOOM platform branding and global settings
LOOM-owned surfaces now use the canonical procedural cube mark and clearer end-user terminology. Global LOOM core settings are distinct from project settings in Admin, and the LOOM platform update log lives on LOOM Home while project release logs remain project-scoped.

## v0.11.20 — project-first loading and profile reuse
The project Loader once again makes the project's logo/wordmark the primary animated visual, with the LOOM cube reduced to a small platform signature at the bottom. LOOM platform updates live only on LOOM Home; projects can install a project-scoped update log backed by their own changelog. The global LOOM profile picture can now be copied from any existing project identity.

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

## v0.12.00 — Identity Permanence & Recovery

LOOM now treats unauthenticated work as durable **Guest History** rather than disposable temporary state. Every new browser/install receives a Guest Identity; independent devices can accumulate independent progress and later attach those histories to the same permanent LOOM account one device at a time.

Attachments preserve provenance. Project source state remains retained, uniqueness-constrained identity/profile data is snapshotted before promotion, conflicts are recorded instead of silently discarded, and Guest IDs remain inspectable after attachment or Guest→Guest reconciliation.

Unattached guests may protect their history with a recovery code. LOOM Admin now includes an Identity Manager for support-assisted recovery, manual Guest attachment, Guest→Guest merge, account creation from a Guest Identity, related-history evidence review, and conflict inspection. IP/network similarity is evidence only and never automatic authentication proof.

See `docs/IDENTITY-PERMANENCE-RECOVERY-STANDARD.md` for the full contract.


## v0.12.04 starter layout + electric energy field

- New/default project Header Bar starts `fit-content` and left-aligned.
- New/default project Footer Bar starts `fit-content` with centered project branding/logo.
- Background Orbs keeps its compatibility ID but now renders LOOM electric circuitry particles/wisps instead of glass bubbles.
- Default ambient density increases from 14 to 70 particles, with a higher Admin range.
- LOOM periodically emits an animated cube as an extensible special background particle.
- Project orb providers can override the special particle independently; Green Beans uses a carrot emoji while retaining pea-pod regular particles.

## v0.12.02 project/core development contract

- Official LOOM handoffs are complete repackaged source trees.
- LOOM-owned capabilities that must run in every project can live once under `/core-modules` with `module.scope = "project"`.
- Project-local/reusable modules remain under each project's `/actions` tree.
- New projects can be created from LOOM Home using the baseline template and managed from either LOOM Home or Admin → Project Settings.
- See `docs/MODULAR-COMPOSITION-STANDARD.md`, `docs/CORE-PROJECT-MODULE-STANDARD.md`, and `docs/PROJECT-MANAGEMENT-STANDARD.md`.

## Deployment metadata (0.12.04+)

Every complete LOOM release carries `/.loom-deployment.json`. The manifest assigns every shipped path a per-file revision and an authority policy. Application/source paths are release-managed, while live identity/user/runtime data is explicitly server-prioritized or server-only so normal website use cannot be erased by a later package extraction.

See `docs/DEPLOYMENT-METADATA-STANDARD.md`.

## Canonical release authority (0.12.06+)

The platform release displayed by LOOM is sourced from `/.loom-deployment.json` through `api/version.php`. The deployment manifest is committed **last** by compatible deployers, after release-managed files verify on the server. README text, cache-bust tokens, or an individually updated module are never sufficient evidence that a deployment completed.
## Green Beans Meal Creator (0.12.07)

Green Beans now includes a project-owned `project.meal-creator` module that composes Shopping List ingredients into persistent named meals. The integration is extension-driven: Meal Creator owns meal records, Shopping List owns ingredient records, and meal groups are synchronized without coupling either module to the other's DOM. Shopping List exposes **Add as Meal** and **Shift + Enter** only when the Meal Creator capability is discovered.



## 0.12.08 Foundation Ten

LOOM now has explicit soft guest profiles, generation-based identity claims, additive schema migration ledger, capability contracts, design tokens, health/audit/replay foundations and disciplined registry caching. See the new standards in `/docs`.
