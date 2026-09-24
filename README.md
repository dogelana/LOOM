## v0.15.42 - Viewport-Safe HTML Framer Stability

HTML Framer auto sizing now understands the difference between a normal scrolling document and a viewport-style application/game. Document pages expand the iframe to their natural height. Viewport apps keep their own 100%-height layout and intentional internal overflow while LOOM gives them one stable viewport-sized frame. The bridge no longer rewrites foreign nested scroll/hidden containers, and busy HUD/text updates no longer retrigger height negotiation. This eliminates the Showcase/Framer up-down flashing seen with full-screen-style games such as the supplied Lint Away package. Historical injected bridge copies are stripped at serve time so only the current layout bridge runs.

## v0.15.41 - Stable HTML Framer Layout + Optional Launch Takeover

HTML Framer auto-fit now suppresses parent/child height feedback loops and confirms shrinking measurements before moving the surrounding project layout. Projects can optionally choose exactly one installed HTML frame to automatically open in full-screen takeover mode on project load; the setting is off by default and remains project-portable.

## v0.15.40 - True HTML Framer Auto-Fit + Admin Accordion Stability

HTML Framer auto mode now expands the delivered app instead of accepting nested vertical scroll containers, with a real viewport bootstrap and continuous layout re-measure handshake on desktop and mobile. Fixed-height mode is still the intentional scrolling mode. Admin settings return to collapsed-first deterministic accordions that remember state and sort recent panels only on render, never while the Admin is interacting inside a card.

## v0.15.39 - HTML Framer Full-Screen Takeover

HTML Framer modules can now become a true full-page experience on demand. Every frame may expose a polished **Full screen** control that lifts the frame above the project shell, hides LOOM chrome from view, fills the browser viewport on desktop or mobile, and returns the module to its exact original location when closed. Auto-fit frames preserve the no-inner-scrollbar contract; Fixed-height frames retain their intentionally scrollable viewport behavior. Admin can set the default globally for new frames and enable/disable the control per frame.

## v0.15.38 - HTML Framer Auto-Fit + Admin Settings UX

HTML Framer now stretches to the delivered document height by default on both desktop and mobile, so framed packages do not need an inner scrollbar. Fixed-height scrolling remains available as an explicit Admin presentation choice. Desktop/tablet and mobile frame presentation settings are independent, and Admin settings cards expand into a clean full-width lane with overflow-safe responsive controls.

## v0.15.37 - Portable Project Assets + HTML Framer Migration

Imported Instance Project assets now use one canonical LOOM asset proxy instead of direct `/instance/...` URLs, so project logos and other project-owned assets remain public through LOOM while the Instance Vault itself stays web-denied. This specifically repairs migrated project logos that previously returned HTTP 403 after import.

HTML Framer packages are now classified as **project structure**. A normal **Project** export carries the frame registry, extracted package files, and preserved source ZIPs; **Project + Data** adds project-owned runtime/data state without duplicating those frame packages. Imports restore the Framer package into the destination Instance Project. Green Beans remains external/importable and is not bundled with the engine.

## v0.15.35 - Empty-Install Admin Stability

LOOM Admin now treats **zero projects as a valid installation state**. Removing bundled product projects in 0.15.33 exposed an old assumption that a successful project discovery must also return at least one project; that caused Project Settings, Users, and Access to disappear on a clean install.

System Owners and LOOM Admins now retain the complete Admin navigation. Project-scoped surfaces show a clear empty state with Create Project / Import Project paths until an Instance Project exists, while Access Manager keeps LOOM-wide administration available immediately.

## v0.15.34 - Page-Width HTML Framer

HTML Framer now uses a dedicated **page-width breakout lane**. Ordinary project modules remain inside the existing capped content column, while framed HTML can size itself against the full usable project page. The default new-frame width is now **100%**; per-frame and project defaults still support 50–100%.

The Module Presentation contract now recognizes `layout.widthScope = "page"` for root-mounted full-canvas surfaces. This fixes the old behavior where a 95% HTML frame was actually 95% of the inner 1320px project container rather than 95% of the page.

## v0.15.33 - Instance-First Project Separation

LOOM no longer ships Green Beans as a release-managed project or starter template. Fresh installations now contain the LOOM engine, generic baseline template, and Instance Project runtime only; concrete products are imported or created as independent Instance Projects.

Green Beans can be migrated by exporting the project from the old installation, installing this clean release, then importing the project bundle. In LOOM 0.15.37 and newer, a project-only export also includes HTML Framer packages. Older project-only exports may omit Framer bytes; use an older Project + Data or Full LOOM backup when those packages must be recovered. Project import/export and full-backup tooling remain intact.

Developer surfaces no longer assume `green-beans` when no project is selected: Pegboard, Action Registry, and Admin Users require an explicit project context instead of silently binding to a bundled product.

## v0.15.32 - HTML Framer Layout + Admin Stability

HTML Framer now defaults imported frames to **95% of project page width**, centered, with a per-frame width slider and a project-level default for newly imported frames. Dynamic HTML frame modules now pass through LOOM's canonical Module Presentation policy, so title bars and collapse/expand controls inherit the same global → project → per-module chrome rules as every other content module instead of forcing their own chrome.

Admin request pressure is also reduced. Access Manager separates its lightweight project-access state from the heavier people catalog, renders existing grants before the optional chooser catalog finishes, and bulk-loads canonical usernames instead of performing one profile lookup per person. Duplicate in-flight Admin requests are coalesced, returned 503 responses enter a short local cooldown instead of spawning retry bursts, and Project Identity/logo saves no longer rebuild and resend the entire module settings payload. Admin shell privilege caching is keyed by both browser client and active permanent user so Switch User cannot leave stale Admin chrome behind.

## v0.15.31 - Admin + Backup Scope + Deployment Convergence Hardening

LOOM Admin can no longer be taken down by the project-list response-contract mismatch that appeared in 0.15.30. Project discovery returns an explicit success contract, skips a single broken project without losing every other project, and no longer assumes Green Beans is the default Admin project.

Backup artifacts now have an explicit scope independent from whichever project happens to be selected in the UI. Full LOOM State is always **Entire LOOM installation**; all-project exports are always global project collections. Existing 0.15.30 generated metadata is repaired lazily when Backup & Restore lists it, and download authorization is based on export type rather than stale scope metadata.

The deployment guard now actively watches the transaction state and does not reopen/reload a browser until the Bridge gate is really released. With Bridge Suite 8.5, the gate remains active through a fresh post-manifest verification pass, making “update complete” one deterministic boundary instead of several loosely related signals.

## v0.15.30 - Export, Import & Restore

LOOM now has a first-class portable bundle system for project migration, backups, and complete installation recovery. Admin can export one project without server-specific data, one project with its project-owned data, all projects with or without project data, or a System Owner-only full LOOM state backup.

Portable project bundles import as Instance Projects by default, so a project can be moved to another LOOM installation—or promoted out of the release tree—without continuing to ship that project in LOOM core releases. Every bundle includes a versioned manifest and SHA-256 file inventory; imports are verified and previewed before any write occurs.

Full LOOM backups intentionally separate application state from infrastructure secrets. Database rows can be exported as portable LOOM data, but database host/user/password configuration, live auth sessions, Admin/capability secrets, and guest recovery secrets are not exported. If a destination has no database yet, imported database data is staged safely until one is configured.

Open **Admin → Backup & Restore** for export/import controls. See `docs/EXPORT-IMPORT-BACKUP-STANDARD.md` for the portability contract.

## v0.15.29 - Identity/Admin Access Clarity

LOOM Admin now treats usernames as the primary human-facing identity everywhere while keeping Guest/User/Client IDs as secondary support metadata. Identity Manager no longer performs expensive legacy discovery during its normal list request, avoiding host timeout/HTML error pages that previously surfaced as JSON parsing failures.

Delegated project administration is intentionally simpler: all new project-scoped administrative grants are **Project Admin** grants. The Access tab makes the selected project unmistakable, reloads grants whenever that project changes, and labels every grant with its project. Legacy Project Manager grants are preserved safely until an Admin revokes them; LOOM does not silently increase their permissions.

Global user controls remain one system: Home, Share, Switch User, and Profile now also appear together beneath Powered by LOOM according to footer display-mode settings.

## v0.15.28 - Unified User Controls + Identity Continuity

LOOM now renders Home, Share, Switch User, and Profile controls through one shared core control system. Project headers gain the missing Switch User control, explicit identity changes reload the current destination cleanly, guest chooser names stay synchronized with canonical LOOM usernames, and inherited ambient defaults now provide 192 energy particles with special particles appearing about twice as often.

## v0.15.27 - Profile Dock Capture Hardening

LOOM now treats shell/controller modules as structural startup providers so Profile Dock is established before ordinary project content mounts. User Profile capture works whether the profile is wrapped in module chrome or mounted directly with title bars hidden, and a dock-readiness flash guard prevents the full profile from briefly appearing in normal project flow. Existing Instance Projects inherit the release-managed fix automatically.

## v0.15.26 - Sharing & Referral Network

LOOM now has canonical Share controls and durable referral attribution across projects and LOOM-owned pages. Share links preserve their destination through first-time guest onboarding, then attribute the resulting durable Guest Identity or permanent account without exposing account secrets in the URL. Referral state lives in the protected Instance Vault, appears in User Profile statistics, and is inspectable by Administrators. Project share cards continue to use server-rendered current project identity/SEO metadata.

This release also hardens hot deployment behavior: Action Runtime watchdogs pause while Bridge's deployment gate is active, and User Profile/Profile Dock are now release-managed core modules so older Instance Projects inherit the modern non-blocking implementation.

Public crawler discovery gains About, Docs, Privacy, Terms, `robots.txt`, and `sitemap.xml`, while private LOOM surfaces remain explicitly non-indexable.

## v0.15.25 - Quiet Module Chrome + Circular Energy + Honest Home Loading

Module title bars are now hidden by default across LOOM, so modules such as Showcase render without an unwanted generic title unless a project explicitly enables chrome. Project Admin can still override title-bar visibility, collapse behavior, initial state, and now the visible title text for each individual module.

The Background Energy Field now renders small circular glowing particles instead of elongated circuitry strands. Ordinary LOOM particles inherit the project accent/primary colors while the special LOOM-logo orb remains independent. LOOM Home now shows a real project-discovery state instead of briefly claiming there are no active projects, and the release watcher now treats the running bundle version as authoritative over stale config/cache state so an already-current page does not display a false reload-loop warning.

## v0.15.24 - Clean Release Reload URLs

Live Release Reload still performs one cache-busted navigation after a verified manifest-last deployment, but its internal `_loom_release` / `_loom_reload` parameters are now transient. The freshly loaded LOOM runtime removes them from the address bar without reloading again and without disturbing real project/query/hash state. The watcher also compares against the current runtime engine version, eliminating false repeated-upgrade detection caused by a stale loom-brand build marker.

## v0.15.22 - Transaction-Safe Live Deployment

LOOM now cooperates directly with Bridge 8.3 during production releases. While Deployer holds its short-lived server deployment lease, APIs return a deployment-specific 503 and browser traffic collapses into one quiet status poll instead of repeatedly retrying authorization/module endpoints. Once the verified manifest-last transaction finishes, open pages reload the clean release automatically.

The browser guard is loaded before normal LOOM engine code on Home, project shells, Admin, Pegboard, Registry, and Activity. PHP-owned pages show the same update state server-side, while `api/deployment-status.php` remains available as the single health probe during the transaction.

## v0.15.21 - Straightforward Showcase Colors

Showcase now follows Project Identity colors directly: the first headline uses the project's primary color and the optional second headline uses the project's accent color. The automatic hue-shift control has been removed to keep branding predictable. Either headline can still be switched to primary, accent, or a custom color in Showcase settings. Legacy shifted modes resolve safely to the corresponding unshifted project color.

## v0.15.20 - Admin Drawer + Complete Project Tools + Faster Startup

The Admin Tools drawer now behaves like a true edge drawer: its gripper is always visible, remains in front of the panel, and stays reachable while the drawer is hidden. LOOM Home exposes the complete project-scoped admin toolset according to effective capabilities. Startup was also reworked around request-local server caches, stale-while-revalidate client caches, non-blocking layout hydration, and bounded parallel module preparation so one slow path cannot serially hold the whole project loader.

## v0.15.19 - Social Fast Path + Showcase Identity + Module Chrome

This release hardens project startup and turns module chrome into a first-class LOOM setting. Social profile fields are handle-first and purely local at startup; module loading is fail-open with bounded timeouts; Showcase inherits Project Identity more deeply and creates its own deterministic project badge when no image has been uploaded. Admin settings are browser-local accordions by default, while collapse/title-bar behavior is configurable globally, per project, and per module.

## v0.15.18 - Delegated Admin + Global Navigation + Static URL Framing + Social SEO

LOOM now has capability-based delegated administration with an immutable System Owner, multiple secondary LOOM Admins, and project-scoped Admin/Manager access for permanent accounts or durable Guest Identities. Admin-only navigation defaults to a shared auto-hiding side drawer, global Home/Profile buttons are centrally configurable across header/footer placements, and redundant Admin console navigation has been reduced.

HTML Framer can capture a bounded public URL as a **static snapshot package** without pretending it is a live production embed. Public project routes now server-render automatic canonical/SEO/Open Graph/Twitter metadata from live project identity, using Showcase image → project logo → LOOM logo fallbacks with content-versioned image URLs. Restricted Admin/developer pages expose only generic LOOM share metadata and remain noindex.

## v0.15.17 - Faster Boot + Live Project Identity

LOOM now keeps expensive analytics and broad project discovery off the critical project-loading path. Social Links no longer waits on a full project-list request, project cards avoid recursive module scans, and user analytics are streamed/cached and hydrated after the profile UI is already usable. Blank project bios are live name-aware fallbacks, Showcase uses canonical live project identity, and the static LOOM logo is the default icon for new or logo-less projects.

## v0.15.16 - Project Theme Inheritance + Shared Admin Chrome

LOOM now treats Project Identity colors as the default visual source across project-facing page backgrounds, module headers, shared surfaces, header/footer chrome, and Showcase while preserving local module overrides. Empty UI regions collapse correctly, Showcase media is transparent/contained by default, Powered by LOOM branding is fit-content by default, the Admin toolbar is canonical across LOOM pages, and generated usernames are human-readable maker names.

## v0.15.15 - Drafts + Toast Feedback

LOOM now protects unfinished project creation work with browser-local drafts and provides one reusable feedback/toast system across LOOM-owned surfaces and project runtimes. Theme Key has been clarified as the advanced **Theme Preset** field.

## v0.15.14 - Canonical Project Branding

Project Identity now owns one canonical text brand. Header, Footer, Loader, Home, and project-aware modules inherit it unless a location explicitly overrides or hides it. The internal project slug is never used as visible brand text when the project name is available.

## v0.15.13 - Project Branding + Instance Delivery + Reload Warning

This release makes new and existing Instance Projects inherit core branding/footer fixes from LOOM itself. Project creation now establishes a usable two-color brand immediately, Instance Project module assets use a path-preserving virtual filesystem, empty More Tools sections disappear, Admin Open Project works from any route depth, and release refreshes provide a one-minute warning by default.

## v0.15.12 - Session Liveness

This maintenance release hardens LOOM presence and tracking continuity. Browser lifecycle transitions and transient heartbeat failures can no longer permanently strand an otherwise usable tab in a stale state. The runtime self-recovers, retries heartbeat transport, and restarts interaction capture when necessary.

## v0.15.11 - Activity Explorer Shell Fix

This maintenance release fixes the Activity Explorer bootstrap dependency order so the historical activity page can mount the standard LOOM shell correctly. All v0.15.10 Framed Action Reader and Activity Explorer functionality remains intact.

## v0.15.10 - Framed Actions + Activity Explorer

LOOM 0.15.10 extends interoperability and observability. HTML Framer now has an Action Reader that turns observable interactions in foreign static HTML bundles into declared LOOM user actions without requiring the foreign package to know LOOM. Admin also gains Activity Explorer for searchable historical user/session/action/interaction review.

Social Links uses the fixed platform order Website → YouTube → Facebook → TikTok → Instagram.

<!-- @loom-file release=0.15.39 revision=54 policy=package-priority -->

## v0.15.09 - Responsive Module Visibility

LOOM 0.15.09 adds a project-scoped **Hide on mobile** policy to Module Control. Modules stay enabled for normal project sessions while Action Runtime suppresses selected modules at 767px and narrower. Background Orbs are enabled by default again and are the first module family to ship with Hide on mobile enabled by default.

The policy is generic, persists in the Instance Vault, reacts live when the viewport crosses the mobile boundary, and keeps enable/disable state independent from responsive visibility. See `docs/RESPONSIVE-MODULE-VISIBILITY-STANDARD.md`.

## v0.15.08 - Live Domain Landing / Project at Base URL

Domain Landing is now live. The global `loom.domain-landing` capability lets an Admin make the exact LOOM installation base open LOOM Home or one selected active project. LOOM Home remains permanently available at `/home/`. The selection persists at `instance/config/domain-routing.json`, supports release projects and Instance Projects, and safely falls back to Home when the module is disabled or the selected project is unavailable.

The implementation uses a stable root PHP front controller rather than dynamically rewriting `.htaccess`. Root-mounted projects reuse their canonical shell through an injected `<base>` and `LOOM_MOUNT_CONTEXT`, so the address bar stays on the domain/install base while normal module/API/assets continue to resolve. The same routing works when LOOM is installed in a subdirectory such as `/LOOM/`.

## v0.15.07 - Mobile Identity Entry + Domain Landing Plan

The shared first-run / Switch User experience is now treated as a true mobile full-screen dialog. Narrow phones use dynamic viewport height, safe-area padding, a compact LOOM intro header, an independently scrolling content region, stacked full-width touch actions and sticky bottom action areas so New Guest, Continue, login and first-profile controls cannot disappear below the viewport. Short-height phones receive an additional compact layout.

This release also adds `docs/DOMAIN-LANDING-STANDARD.md`, the implementation plan for making a selected LOOM project own the installation base URL while keeping LOOM Home permanently available at `/home/`. The routing capability is intentionally documented but **not activated yet** in 0.15.07; the plan requires a server-side front controller plus a mount-context refactor so projects can run at `/` without fragile relative-path assumptions.

## v0.15.06 - Identity Switching, Orb Defaults + Canonical Home Version

Background Orbs are now opt-in. Both the LOOM background-orb renderer and project-owned background-orb providers ship disabled by default. Module Control remains authoritative: an Admin may explicitly force-enable either module for a project, and the runtime honors that saved override. Disabled manifests remain visible in Admin rather than disappearing from the catalog.

Switch User now always exposes a path to create a new guest. When a permanent account is active, the identity screen offers **New guest**, **Choose guest**, and **Continue**; choosing a guest path signs the permanent account out on that browser without deleting it. The chooser content is scroll-safe on smaller displays.

The project User Profile dock now gives its Switch User control a dedicated responsive button style instead of inheriting the square close-button dimensions.

LOOM Home no longer embeds an old release number as display fallback. Its hero/version badges are populated from `api/version.php` with no-store requests and are refreshed on `pageshow`/visibility return. Canonical version and changelog endpoints also emit explicit no-cache headers.

## v0.15.05 - Admin Routing, Showcase Frame + Mobile Hardening

LOOM now allows Apache directory-index resolution to fall back from `index.php` to `index.html` inside `/admin/`. This makes the package-owned first-Administrator route `/admin/setup/` resolve its existing `admin/setup/index.html` entrypoint on ordinary Apache/Hostinger configurations while `/admin/` continues to prefer `index.php`.

Showcase 1.0.1 now visually merges with LOOM's native expand/collapse frame: the module body removes its duplicate top border/rounded corners when the LOOM frame header is present, producing one continuous card instead of a rounded card nested beneath another rounded bar.

The release also hardens narrow-screen behavior across project shells, collapsible module frames, Showcase, Social Links, Admin, and first-Administrator setup. Main interactive surfaces retain viewport metadata, content containers are constrained to the viewport, long labels/text can shrink or wrap, and mobile controls preserve practical touch targets.

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

### 0.15.37 portability boundary
Project-owned visual assets are served through the LOOM asset proxy so Instance Vault paths never leak into public URLs. HTML Framer packages are part of Project structure and travel with ordinary Project export/import bundles.
