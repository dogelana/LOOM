## v0.15.38 - HTML Framer Auto-Fit + Admin Settings UX

- HTML Framer now defaults to **Stretch to delivered content** height on desktop and mobile. The sandbox posts document-height changes back to LOOM through a dedicated layout bridge, and LOOM resizes the iframe instead of clipping content behind an inner scrollbar.
- Fixed-height iframe scrolling is now an explicit Admin choice rather than the default behavior. Existing numeric frame heights remain preserved as the value used when Fixed mode is selected.
- HTML Framer presentation is split into independent Desktop/Tablet and Mobile (≤760px) controls for height behavior, fixed height, and page-width percentage.
- Newly imported ZIP frames and static URL captures inherit the selected project HTML Framer desktop/mobile defaults. Legacy frame registries without height-mode fields safely resolve to auto-fit.
- Admin settings cards no longer visually stretch neighboring cards when expanded. Open settings cards receive the full grid lane, project/global module fields use a responsive two-column layout on larger screens, and controls are constrained against horizontal overflow.
- HTML Framer manager rows now use responsive Desktop and Mobile device panels so the editor stays clean on narrow Admin layouts.

## v0.15.37 - Portable Project Assets & HTML Framer Migration

- Fixed imported Instance Project logos returning HTTP 403 because LOOM Home could generate a direct URL beneath the intentionally web-denied `/instance` vault.
- Added a canonical project-asset resolver/proxy contract: project-owned assets now load through `api/project-asset.php` whether their bytes come from an overlay upload, an imported Instance Project runtime, or a release-managed project.
- Fresh project-logo replacements now use the same proxy URL contract, eliminating path behavior differences before and after migration.
- Reclassified HTML Framer packages as **project structure**. Plain Project exports now include `frames.json`, extracted frame files, and preserved `source.zip` packages.
- Project + Data exports keep HTML Framer portable without duplicating it inside the data payload.
- Project imports restore included HTML Framer structure into the destination Instance Project and report whether frame packages were restored.
- Older portable bundles remain compatible. If an older plain Project export never contained HTML Framer bytes, LOOM does not invent them; an older Project + Data or Full LOOM backup can still carry those files.
- Green Beans remains an imported Instance Project and is not bundled with LOOM.

## v0.15.36 - Unified Native Chrome & Admin Authorization

- Fixed protected LOOM-native pages incorrectly returning **Administrator access required** while the same browser was already recognized as Admin by the main Admin console.
- Added a short-lived signed Admin navigation credential, issued only after canonical Admin status succeeds and revalidated against current Admin authority on protected page requests.
- Added a shared native Admin page gate that can recover the current LOOM browser identity and establish the protected navigation session without duplicating authorization code in each page.
- Restored the Admin drawer on the main Admin console. Same-path Admin tab destinations are automatically omitted there, leaving only non-redundant destinations such as Activity, Referrals, Backup & Restore, Pegboard, and Action Registry.
- Centralized Admin link filtering/current-page suppression in `LoomShell` so native pages do not each maintain their own sidebar menu.
- Added **Import Project** beside **New Project** on LOOM Home, deep-linking directly to the Backup & Restore import panel.
- Reworked public LOOM-native pages to use the shared `LoomShell` header/footer instead of a separately coded public header/footer implementation.
- Synchronized the Instance Project runtime shell and baseline project template so new projects and imported Instance Projects start from the same LOOM-native structural contract.
- Green Beans remains unbundled and importable as a normal Instance Project.

## v0.15.35 - Projectless Admin Recovery

- Fixed the fresh-install Admin navigation regression exposed after release projects were removed: System Owners / LOOM Admins now keep the full Admin tab set even when the installation currently has zero projects.
- Project Settings and Users remain visible on an empty installation and render a deliberate projectless state instead of disappearing or firing invalid project API calls.
- Access Manager remains available without a project so LOOM-wide Admin delegation still works; only the project-scoped grant card waits for a project to exist.
- Added direct Create Project and Import Project recovery paths to empty project-scoped Admin surfaces.
- Reframed project discovery success separately from “at least one project exists,” preventing a valid empty project catalog from being mistaken for an Admin capability failure.
- Refreshed runtime/cache version anchors to 0.15.35.

## v0.15.34 - HTML Framer Page-Width Breakout

- Kept the normal project module flow capped at 1320px by default.
- Added a LOOM-native page-width presentation scope for root-mounted modules.
- HTML Framer dynamic modules now opt into that page lane, so their width slider is based on the full usable page rather than the inner project content column.
- Changed the HTML Framer default width from 95% to **100%**. Legacy frames with no stored width also inherit 100%; explicitly saved frame widths remain unchanged.
- Reworked HTML Framer chrome sizing so LOOM title bars and frame bodies share the same requested percentage without the old nested percentage effect.
- Added container-query-unit sizing with a viewport fallback to keep the breakout responsive across desktop and mobile shell gutters.
- Documented `presentation.layout.widthScope = "page"` in the module-presentation and project-shell standards.

## v0.15.33 - Green Beans Promoted to Instance Project

- Removed `projects/green-beans` from the LOOM release payload.
- Removed `templates/projects/green-beans`; the generic `templates/projects/baseline` remains the only project starter shipped by LOOM.
- Retired Green Beans release directories in deployment metadata so in-place upgrades remove the old release-managed copies without touching imported Instance Projects.
- Removed obsolete Green Beans logo-validation and meal-composition release documents from LOOM core.
- Removed Green Beans defaults from Pegboard, Action Registry, Admin Users, and shared runtime helpers.
- Fresh installs can now legitimately contain zero product projects until an Admin creates or imports one.
- Project-only exports remain the preferred migration artifact when project-owned runtime data is not required. Imported projects become Instance Projects by default.

## v0.15.32 - HTML Framer Width + Canonical Chrome + Admin Request Hardening

- HTML Framer frames now render at **95% page width by default**, centered within the normal project flow. Existing frames with no saved width inherit 95% automatically.
- Added a project-level **Default new frame width** setting (50–100%) and a per-frame **Page width** slider in HTML Framer Manager.
- Removed HTML Framer's accidental hard-coded presentation behavior. Dynamic `html.frame.*` descriptors now pass through the canonical Module Presentation policy, so title bars, collapse enablement, initial state, and per-module overrides behave exactly like native LOOM modules. If Module Chrome/title bars are disabled, an HTML frame no longer displays its own collapse/expand bar.
- HTML Framer content uses full rounded corners when unframed and joins the canonical module frame cleanly only when LOOM chrome is actually present.
- Split Access Manager's lightweight project-access status from the expensive account/guest subject catalog. The people catalog is loaded separately and cached briefly instead of being rebuilt after every access read/write.
- Access Manager now renders the selected project, current grants, and authority summary as soon as the lightweight status response arrives; if the optional grant-chooser catalog is temporarily busy, existing access remains visible instead of blanking the whole tab.
- Access subject labeling bulk-loads canonical Global Profiles once per request instead of issuing a profile lookup for every account/guest, removing an N+1 database/filesystem hotspot that could amplify shared-host 503s.
- Added Admin request coalescing and a short local cooldown for returned `503 Service Unavailable` responses. LOOM no longer multiplies a busy-host event into a request/retry storm.
- Unified Identity Manager, Database, Access Manager, Users, and normal Admin JSON calls behind the same guarded response handling.
- Project Identity and project-logo saves now return only the updated canonical project profile instead of rescanning and serializing the entire module catalog after every branding save.
- Admin-page LOOM Shell no longer performs a redundant Admin-tools status fetch/drawer mount.
- Admin status cache keys now include the active permanent `userId` in addition to the browser `clientId`, preventing stale Admin chrome after Switch User on the same device. Cache freshness is reduced to 30 seconds.
- Removed remaining API-level implicit `green-beans` project fallbacks from Admin and HTML Framer endpoints; callers must provide an explicit project.

## v0.15.31 - Admin Recovery + Backup Scope + Deployment Convergence

- Fixed the Admin console project-list regression introduced by the hardened loader: `api/projects.php` now explicitly returns `ok: true`, matching Admin's response contract. Backup & Restore had appeared healthy because its loader did not require that field.
- Project discovery is now fail-soft per project. One malformed project can be reported as a warning without taking down the entire Admin project list. Global Admin tools remain usable even if project discovery temporarily fails.
- Removed the historical hard-coded `green-beans` Admin startup fallback. Admin now selects the requested project only when it exists, otherwise the first accessible project.
- Fixed generated backup metadata so **Full LOOM State**, **All Projects**, and **All Projects + Data** are installation/global scopes and never inherit the currently selected project. Existing v0.15.30 generated backup metadata is normalized automatically when the backup list is opened.
- Hardened backup-download authorization to use the export **type**, not a stale project field. Global/all-project/full bundles therefore cannot accidentally be treated as a single-project backup.
- Backup & Restore now labels generated artifacts by clear scope such as **Entire LOOM installation**, **All projects**, or the actual project name.
- Upgraded the browser deployment guard to a proactive transaction watcher. It polls the dependency-free deployment status even before ordinary API traffic, distinguishes applying vs final verification, and performs one clean reload only after the deployment gate is truly released.
- Deployment status now reports canonical release, gate phase, transaction ID, and lease information without exposing private state.
- Designed to pair with Bridge Suite 8.5 / Deployer 5.4, which keeps production gated through a fresh post-manifest verification pass instead of reopening immediately at manifest commit.

## v0.15.30 - Portable Project Bundles + Full-State Backup / Restore

- Added a LOOM-native **Export, Import & Restore** core system with an Admin Backup & Restore workspace and canonical Action Registry entries.
- Added five export modes: **Project**, **Project + Data**, **All Projects**, **All Projects + Data**, and **Full LOOM State**.
- Project bundles preserve project runtime structure, branding, project assets/overlays, project overrides, and module/Admin settings while intentionally excluding unrelated server identities, sessions, referrals, telemetry, and secrets unless a data-bearing/full-state mode is explicitly selected.
- Project imports create **Instance Projects by default**, allowing a release-managed project to be migrated into installation-owned state without continuing to ship it in future LOOM packages.
- Full-state backup captures protected Instance Vault state plus portable LOOM database application rows when a database is available. Database connection credentials, active authentication sessions, capability/admin tokens, and guest recovery secrets are excluded.
- Database payloads are infrastructure-independent. If the destination has no configured database, LOOM stages the payload safely under protected Instance storage for later application after a database is attached.
- Import is always **upload → checksum verification → preview → explicit strategy → apply**. Strategies include create-new, merge, replace, and skip. Full-state restore requires System Owner authority plus the confirmation phrase `RESTORE LOOM`.
- Replace/full restore paths create protected rollback snapshots before mutation. Generated exports and rollback artifacts live only under the Instance Vault and remain outside normal release/Git synchronization.
- Added `docs/EXPORT-IMPORT-BACKUP-STANDARD.md`.

## v0.15.29 - Admin Identity + Project Access UX Hardening

- Fixed Identity Manager startup failures caused by running legacy Guest Identity discovery/backfill synchronously on every list request. The normal list path is lightweight again, and Admin now handles non-JSON upstream/server error responses gracefully instead of throwing `Unexpected token '<'`.
- Identity Manager and Users now lead with the current human username; guest IDs, client IDs, emails, and other internal identifiers remain visible as secondary support metadata and remain searchable.
- Simplified new delegated project access to one clear role: **Project Admin**. New Project Manager grants are retired. Existing legacy Manager grants remain preserved without automatic privilege escalation and are labeled as legacy until revoked.
- Access Manager now shows an explicit **Managing project** context and every project grant names its owning project. Changing the project selector immediately reloads project-scoped access instead of leaving stale grants from the previous project on screen.
- Fixed **Open selected project** so it resolves from the selector at click time rather than relying on an asynchronously updated stale link. Project selection is also reflected in the Admin URL for consistent context.
- Added **Switch User** to the Powered by LOOM footer navigation, using the same global Navigation Chrome definition as the header. It defaults to emoji-only in the footer and is globally configurable. Switching users continues to reload the current destination after selection.

## v0.15.28 - Unified User Controls + Identity Refresh + Denser Energy Field

- Replaced separately hand-built Home/Share/Switch/Profile header controls with one shared LOOM user-control factory. LOOM Home and project shells now use the same sizing, spacing, vertical centering, and horizontal alignment rules.
- Project headers now include **Switch User** between Share and User Profile. Share and project User Profile remain modular; their buttons occupy shared shell slots and use the same core factory when enabled.
- Explicit user switching performs a clean same-page reload after selection so project identity, profile data, permissions, referrals, and modules rebuild for the selected user instead of retaining stale UI.
- Guest chooser names resolve from the canonical current LOOM profile username. Legacy `LOOMUser-XXXXXX` labels are lazily migrated to readable maker names and synchronized back into guest-profile records. First-run helper copy now shows the modern naming format.
- Background Energy Field default density doubled from 96 to **192** particles. Special particles arrive about twice as often, with the default interval reduced from 38 to **19 seconds**. Historical copied orb-default manifests remain recognized as inherited defaults, so untouched older projects receive the new values automatically while explicit Admin overrides remain pinned.
- Navigation Chrome now also exposes Switch User emoji/text/display-mode settings.

## v0.15.27 - Profile Dock Capture Hardening

- Fixed User Profile occasionally rendering as a normal full-page module when module title bars are hidden. Profile Dock now captures either the framed profile container or the direct unframed `core.user.profile` root.
- Action Runtime now loads shell/controller modules in the structural startup phase before concurrent ordinary content, eliminating the race where User Profile could mount before Profile Dock was observing the page.
- Added a dock-readiness visibility guard so User Profile cannot flash in normal project flow during the tiny reparenting window.
- Updated Profile Dock implementations/templates and cache/version tokens to 0.15.27; release-managed core behavior means existing Instance Projects inherit the fix without mutating their persistent project trees.

## v0.15.26 - Sharing, Referrals + Deployment-Aware Runtime

- Added LOOM **Sharing & Referrals** as a first-class project-scoped core controller with registered user actions for opening Share, copying links, native sharing, and accepting referral attribution.
- Added permanent referral persistence in the Instance Vault for guests and permanent accounts. Share links use opaque `loom_ref` codes, survive normal guest onboarding, preserve the intended destination, reject self/duplicate referrals, and retain guest provenance after account registration.
- Added Share beside LOOM Home/Profile controls, compact emoji-only Share in Powered by LOOM footer navigation by default, Share buttons on LOOM Home project cards, and global Share appearance controls alongside Home/Profile navigation settings.
- Added user-facing referral statistics inside User Profile and an Admin-only Referrals dashboard with share-link, click, unique-visitor, and attributed-referral history.
- Promoted User Profile and Profile Dock into release-managed project-scoped core modules so older Instance Projects inherit the current non-blocking profile implementation instead of retaining stale copied startup behavior.
- Module watchdog timers now **pause while the transactional deployment gate is active**. A deliberate Bridge deployment 503 can no longer age into a false `core.user.profile timed out after 7000ms` failure while LOOM is intentionally paused.
- Added public About, Documentation, Privacy, and Terms entry points plus dynamic `robots.txt` and `sitemap.xml`. Public project SEO/social metadata remains live and project-aware; Admin/API/Instance/developer surfaces remain excluded from public indexing.
- Bumped critical startup/chrome assets to 0.15.26 so normal release cache busting is sufficient; manual cache clearing is not part of the expected upgrade flow.

## v0.15.25 - Module Chrome Defaults, Energy Particles, Home Loading, Release Watch

- Changed the LOOM-global module-title-bar default to hidden. Projects that inherit LOOM defaults receive the cleaner behavior automatically; explicit project/module overrides remain authoritative.
- Added project-scoped per-module custom title text alongside existing per-module title-bar, collapse-capability, and initial-state overrides. Showcase therefore no longer displays the word `Showcase` unless its title bar is explicitly enabled.
- Rebuilt ordinary LOOM Background Energy particles as small circular glowing energy points instead of elongated strands/line shapes. The field now blends project accent and primary colors while preserving the independent special LOOM-logo orb.
- Reworked LOOM Home project-list state so `No active projects` is shown only after a successful completed discovery returns zero projects. Slow discovery now renders a dedicated loading state; failures render a distinct error state.
- Hardened Live Release Reload against stale client-version/config state. The shipped bundle version is never downgraded by an older `LoomConfig.engineVersion`, stale loop-guard state is cleared after a healthy matching boot, and lower-version false-update banners are suppressed.
- Preserved automatic cleanup of `_loom_release` and `_loom_reload` after the one cache-busting convergence reload.

## v0.15.24 - Clean Release Reload URLs

- Temporary `_loom_release` and `_loom_reload` cache-busting parameters are now consumed immediately after the refreshed LOOM document boots. LOOM removes only those two reserved parameters with `history.replaceState()`, preserving the real path, unrelated query parameters, and hash without causing another network navigation.
- Fixed Live Release Reload to compare the server canonical release against the actual runtime `LoomConfig.engineVersion` instead of an older hardcoded loom-brand script release. A successfully upgraded page therefore settles normally instead of repeatedly believing a newer release is still waiting.
- Bumped direct `config.js` and `loom-brand.js` asset URLs to the current release on LOOM Home, project shells/templates, Admin, Activity, Pegboard, Registry, and setup surfaces so this cleanup cannot be defeated by an older cached engine script.
- The temporary query marker is still used for the one cache-busting reload request; it simply disappears from the visible URL once that new document is confirmed running.

## v0.15.23 - Inherited Defaults + Showcase Badge + Visible Energy Field

- Changed the LOOM-global module-collapse default to **disabled**. New projects inherit non-collapsible module chrome unless an Admin enables collapse globally, per project, or per module. Existing projects that still use `inherit` receive the new default automatically; explicit overrides remain explicit.
- Promoted **Project Logo** and **Background Orbs** to release-managed project core modules while preserving the existing action IDs. Older Instance Projects therefore receive engine fixes without rewriting their project-owned files.
- Project-logo resolution now guarantees the static LOOM logo when `assets/logo.png` is absent, including old Instance Projects that previously dropped to the tiny star/glyph fallback. New/reset project-logo sizing is healthier at 64%.
- Reworked generated Showcase badges: primary/accent colors now form a stronger procedural Gaussian-smoothed blob field, project names are solid black with a white outline, and text dynamically shrinks instead of breaking a word across lines. The badge remains runtime-generated and requires no saved server image.
- Increased inherited LOOM background-energy defaults to **96 particles, 135% drift speed, 74% glow**, larger/brighter circuitry marks, and a 54px special particle. Default glow follows the project accent color unless explicitly overridden.
- Added legacy-default detection for Background Orbs: copied values matching LOOM's former 70/100/58 defaults are treated as inherited, so old projects pick up improved LOOM defaults; genuinely changed legacy values and Admin overrides remain preserved.

## v0.15.22 - Deployment Gate + Retry-Storm Protection

- Added a server-side deployment gate driven by the Bridge's short-lived `.loom-deploying.json` lease. During an active release, normal LOOM API requests return **503 Service Unavailable** with `Retry-After` and `X-LOOM-Deploying: 1` instead of running against mixed old/new authorization code.
- Added `api/deployment-status.php`, a tiny dependency-free status probe that remains readable while the rest of LOOM is gated. Expired gate leases are treated as ready so a crashed deployer cannot leave an installation permanently offline.
- Added `engine/deployment-guard.js`. It wraps same-origin `fetch`, catches the first deployment 503, blocks further LOOM requests behind one shared pending promise, shows a clean **LOOM is updating…** overlay, polls only the status endpoint, and reloads exactly once after the gate clears.
- LOOM-owned PHP pages that load `_common.php` now show a lightweight auto-refreshing maintenance view during deployment rather than raw authorization/API failures. Static project/Home pages load the deployment guard before their normal engine scripts.
- Project shells, templates, Admin, Activity, Pegboard, Registry, and LOOM Home all load the same deployment guard. Project config cache-busting advances to 0.15.22.
- This release is designed to pair with **LOOM Bridge Suite 8.3 / Deployer 5.3**, which stages remote uploads and keeps the deployment gate active until the canonical manifest commit verifies.

## v0.15.21 - Showcase Color Simplification

- Removed Showcase's automatic hue-shift system entirely. Headline 1 now inherits the project primary color exactly by default, and Headline 2 inherits the project accent color exactly by default.
- Showcase headline colors remain independently editable: each headline can use project primary, project accent, or a custom color.
- Existing projects that previously stored `shifted-primary` / `shifted-accent` modes are treated as their direct primary/accent equivalents so upgrades do not produce surprise colors.

## v0.15.20 - Admin Drawer Edge Grip + Complete Project Tools + Startup Fast Path

- Rebuilt the Admin Tools drawer geometry so its **ADMIN TOOLS** gripper stays permanently visible on the viewport edge when closed, sits in front of the panel, and remains the outside-facing control on either left or right placement.
- Expanded LOOM Home project-admin quicklinks to the complete project-scoped set: Project Settings, Users, Access, Activity, Pegboard, and Action Registry, while preserving owner-only identity/archive actions and capability-filtering delegated Project Admin/Manager links.
- Added project role/capability metadata to the LOOM Home project feed so delegated project administrators receive only the project tools they are actually allowed to use.
- Removed several startup bottlenecks: project/global settings and delegated-access stores are request-memoized, core-module manifests are scanned once per request, repeated registry/settings responses use bounded session-cache fast paths, shell admin status is bounded/cached, and project-state layout hydration no longer blocks first paint.
- Module boot now loads structural region modules first, then prepares ordinary modules with bounded parallelism instead of serially stacking every module's import/factory/mount/activation waits. Per-stage timeouts remain fail-open.
- LOOM Home now paints a recent session-cached project list immediately and refreshes it in the background; identity, settings, privilege, shell, updates, and visitor work are no longer needlessly serialized.

## v0.15.19 - Social Fast Path + Showcase Identity + Module Chrome

- Fixed bootstrap progress so the loader names the module currently being prepared rather than the module that just finished. A slow module can no longer falsely make Social Links look guilty.
- Added hard fail-open startup timeouts for module import/factory/mount/activation and an 8-second live registry timeout with session/static fallback.
- Social Links now prefers simple usernames/handles for YouTube, Facebook, TikTok, and Instagram, accepts full matching URLs, canonicalizes locally, and never resolves social destinations during startup.
- Added LOOM-global, project-level, and per-module controls for module title bars, collapse capability, and initial expanded/collapsed state.
- Admin/Project settings cards now start collapsed, remember browser-local open state, and move recently interacted module setting cards toward the top without counting expand/collapse clicks.
- Showcase removed its visible SHOWCASE label, added optional second headline and second bio, inherits the Project Identity font, derives headline colors from project primary/accent with an adjustable 10% clockwise hue shift, and keeps paragraph text black by default.
- Showcase now generates a deterministic project badge when no uploaded image exists: circular ridged/shadowed abstract color art, project name, LOOM logo, and POWERED BY LOOM nameplate on transparent surroundings.

## v0.15.18 - Delegated Admin + Global Navigation + Static URL Framing + Social SEO

LOOM now has capability-based delegated administration with an immutable System Owner, multiple secondary LOOM Admins, and project-scoped Admin/Manager access for permanent accounts or durable Guest Identities. Admin-only navigation defaults to a shared auto-hiding side drawer, global Home/Profile buttons are centrally configurable across header/footer placements, and redundant Admin console navigation has been reduced.

HTML Framer can capture a bounded public URL as a **static snapshot package** without pretending it is a live production embed. Public project routes now server-render automatic canonical/SEO/Open Graph/Twitter metadata from live project identity, using Showcase image → project logo → LOOM logo fallbacks with content-versioned image URLs. Restricted Admin/developer pages expose only generic LOOM share metadata and remain noindex.

# LOOM Changelog

## v0.15.17 — Fast project boot + live project identity defaults

- Removed the Social Links startup dependency on a full `projects.php` request. Project social color is now injected into the module configuration by LOOM, so projects with many links no longer hold the bootstrap loader on **Preparing Social Links**.
- Reworked `projects.php` for fast project-card loading: optional single-project queries, no recursive action-tree scan for every logo, one release-version lookup per request, and request-local project-profile caching.
- Hardened `user-profile.php` against 503/timeouts by streaming telemetry logs instead of loading them into memory, eliminating the second analytics scan, and caching analytics snapshots for 60 seconds. Updated profile clients mount from a lightweight identity response first and hydrate analytics in the background.
- Blank project bios now resolve live to **`<Project Name> is powered by LOOM.`**. The generated fallback follows project renames automatically while explicitly written bios remain untouched.
- Showcase now requests only its current project and continues to render its title from the canonical live project name rather than a copied Showcase title.
- New baseline projects now receive the static LOOM logo as their actual default `assets/logo.png`. Existing projects with no logo also fall back to the static LOOM logo, contained and centered in project branding slots instead of showing the small star fallback.

# LOOM 0.15.16 - Project Theme Inheritance + Shared Admin Chrome

- Project Identity colors now seed the project page background, highlight/edge tones, text/accent/muted tokens, borders, shared surfaces, module-frame headings, header background, and project-facing footer surfaces. Per-module Admin overrides still win.
- Added release-managed `core.ui.header-bar` so existing Instance Projects inherit header fixes without package code mutating their project trees. Empty brand-text slots now collapse completely, removing phantom spacing when Header Wordmark is disabled.
- Hardened Footer/Orb presence handling: the More Tools row remains fully hidden when no captured tools exist, including fallback project copies.
- Footer shell remains full width by default; the Powered by LOOM attribution row now defaults to fit-content with independent extra width/height padding matching project-brand defaults (100px / 20px total). LOOM attribution colors remain LOOM defaults but are individually overrideable.
- Showcase images now preserve transparent PNG presentation, use centered `contain` sizing by default, and no longer receive an automatic image backdrop. Admin adds transparent, project-primary, project-accent, page, LOOM-soft, white, and custom background choices plus contain/cover and padding controls.
- Module-frame headings now inherit the project accent, so Showcase and other native module headers follow Project Identity automatically.
- Replaced generated `LOOMUser-XXXXXX` names with deterministic two-word maker names plus six hex characters, e.g. `AppWeaver475AFD`, with collision-safe pair retries. Legacy machine-style generated defaults upgrade lazily; user-selected usernames are preserved.
- `LoomShell` now owns one canonical Admin navigation map for LOOM Settings, Project Settings, Users, Identity Manager, Database, Activity, Pegboard, and Action Registry. Shell pages and project Admin toolbars render from the same structure.
- LOOM Home links in shared shell chrome now use the same 🏠 marker, and Admin deep links honor all Admin tabs rather than Project Settings only.

# LOOM 0.15.15 - Drafts + Toast Feedback

- Added browser-local autosave/recovery for the Admin Create Project form; clicking away, closing the modal, refreshing, or navigating no longer wastes changed project fields.
- Renamed visible **Theme Key** controls to **Theme Preset** and moved them into Advanced project options; the default remains `default`.
- Added the shared `LoomToast` feedback service with success, saved, info, warning, error, and required-step states plus safe-area mobile behavior and accessible live-region semantics.
- Added global `loom.toast` settings for position, duration, visible stack size, and LOOM default colors.
- Added project-scoped `core.ui.toast-theme`; project toasts inherit canonical project branding by default and can switch to LOOM or custom colors.
- Added `ctx.toast(...)` to native LOOM module contexts.
- Added shared `data-loom-tip` tooltips and `data-loom-requires` acknowledgement feedback.
- Added save/apply confirmation to major LOOM Home and Admin module/project-setting workflows while keeping inline error messages.
- Added standards for feedback/toasts and pre-project draft ownership.

# LOOM 0.15.14 - Canonical Project Branding

- Introduced one canonical Project Identity wordmark shared by LOOM Home, Loader, Footer, and project-aware modules.
- Internal slugs are no longer valid visible footer fallback text; human project names are prettified and auto-balanced instead.
- Renamed the meaning of `core.ui.load-logo-text` to **Header Wordmark** while keeping its stable action ID. Disabling it now hides header text only.
- Header Wordmark defaults to canonical Project Identity wording/colors/font and supports header-only custom overrides.
- Footer project text is now independent from Header Wordmark, with Project Identity / Custom / Hidden (logo-only) modes plus local color/font overrides.
- Promoted Project Loader to a release-managed project-scoped core module so existing Instance Projects inherit canonical brand wording even when Header Wordmark is disabled.
- Project Identity now exposes canonical wordmark Auto/Custom mode, line 1/line 2, font, main color, and accent color in one place.
- Added `docs/PROJECT-BRANDING-STANDARD.md`.

# LOOM 0.15.13 - Project Branding + Instance Delivery + Reload Warning

- Promoted **Logo Text**, **Footer Bar**, and **Orb Dock** into release-managed project-scoped core modules so existing Instance Projects receive fixes without LOOM mutating their persistent project trees.
- Every project now has Logo Text automatically. With no explicit override, LOOM balances the canonical project name across up to two lines; punctuation separators such as `Lint-Away` become `LINT` / `AWAY`.
- Project names now support up to **140 characters**.
- Project creation and identity editing now support optional **Main** and **Accent** brand colors with color pickers and hex fields. Logo Text inherits them; project-default Social Links inherit the main color.
- Fixed Admin **Open Project** URLs by returning installation-root-aware absolute app paths instead of page-relative paths.
- Added a path-preserving Instance Project virtual filesystem route (`/api/project-file/<project>/<path>`) so relative ES imports, `import.meta.url`, CSS assets, fonts, and avatar/default-image assets resolve correctly. This fixes missing profile avatars in Instance Project shells.
- Orb Dock now suppresses the entire **More Tools** footer row when no tools are captured.
- Live Release Reload now gives users a **60-second warning countdown by default**, with a `Reload now` control, instead of an almost immediate refresh. Admin can configure 5–300 seconds.
- Showcase continues to follow the canonical Project Bio by default; Admin may use the existing Showcase custom-bio override and return to Project Bio at any time.

# LOOM 0.15.12 - Session Liveness

- Fixed sessions that could become stale and permanently stop tracking while the same browser tab was still usable.
- `pagehide` no longer irreversibly destroys the in-page runtime. If the document becomes active again, LOOM reopens presence with a fresh runtime ID inside the same logical session and restarts interaction capture.
- Added forced liveness recovery on `pageshow`, visible-tab restoration, window focus, network restoration, and real user interaction.
- Replaced the fixed heartbeat interval with a self-healing heartbeat scheduler that retries failed heartbeat requests instead of silently swallowing them forever.
- Increased the default presence lease from 45 seconds to 90 seconds to tolerate mobile/browser timer throttling while preserving fast 5-second normal heartbeats.
- Server heartbeat handling now records a closed session as resumed when a different runtime legitimately reopens the same logical tab session.
- Added `docs/SESSION-LIVENESS-STANDARD.md`.

# LOOM 0.15.11 - Activity Explorer Shell Fix

- Fixed Activity Explorer startup failure: `LoomBrand is required before LoomShell`.
- Activity Explorer now loads the shared LOOM shell dependencies in the same proven order as Admin, Pegboard, Registry, and LOOM Home: `loom-brand.js` → `identity.js` → `loom-global-profile.js` → `loom-shell.js`.
- No Activity Explorer data model, filtering behavior, HTML Framer Action Reader behavior, or Instance Vault ownership rules changed in this maintenance release.

# LOOM 0.15.10 - Framed Actions + Activity Explorer

- Social Links now renders fixed platform links in the permanent order **Website → YouTube → Facebook → TikTok → Instagram**, with Custom Link afterward.
- Added HTML Framer **Action Reader v1** for backwards-compatible action telemetry from foreign HTML/CSS/JavaScript bundles.
- HTML Framer import analysis now catalogs observable UI controls and inventories named JS functions / event-listener registrations as non-invasive discovery hints.
- Framed clicks/navigation, form submits and field changes are translated into normal declared LOOM user actions without copying typed text values.
- Added global and per-frame Action Reader enable/disable controls.
- Added **Admin → Activity Explorer**, a searchable historical activity surface for users/guest clients, sessions, actions, clicks/taps, inputs, framed HTML, module/runtime events and errors.
- Activity Explorer combines durable semantic event logs with the existing privacy-scrubbed interaction-capture stream and can export the current result set as JSON.
- Added `docs/ACTIVITY-EXPLORER-STANDARD.md` and expanded `docs/HTML-FRAMER-STANDARD.md`.

# LOOM 0.15.09 - Responsive Module Visibility

- Added a generic project-scoped **Hide on mobile** checkbox to Module Control.
- Responsive suppression uses the project viewport (`max-width: 767px`), not User-Agent sniffing.
- Action Runtime now unloads hidden modules when entering mobile width and loads them again when leaving mobile width.
- Module enable state and mobile visibility state persist independently in the Instance Vault.
- Restored Background Orbs and project orb providers to enabled-by-default behavior.
- Background Orbs are the only modules that default to **Hide on mobile = ON** in this release.
- Updated fallback registries so offline/static fallback behavior matches the same orb policy.
- Added `docs/RESPONSIVE-MODULE-VISIBILITY-STANDARD.md`.

# LOOM 0.15.08 - Domain Landing Goes Live

- Added global core capability `loom.domain-landing`.
- Added persistent `instance/config/domain-routing.json` authority with LOOM Home as the safe default.
- Added a root `index.php` front controller that can render one selected release project or Instance Project at the exact installation base URL without a visible redirect.
- Added permanent `/home/` LOOM Home recovery route.
- Added `LOOM_MOUNT_CONTEXT` injection for root-mounted project shells and made project-shell identity resolve from mount context first.
- Made LOOM-owned Home buttons target the permanent `/home/` route so they remain semantically correct while a project owns `/`.
- Added Admin -> LOOM Settings -> Domain Landing manager with project selector, URL previews, save/restore controls, confirmation, and server validation.
- Disabling Domain Landing or losing/archiving the selected project safely falls back to LOOM Home without deleting the saved preference.
- Project discovery now marks the landing project and advertises its base URL as the public `app_url` while retaining `canonical_app_url`.
- Hardened LoomShell and LOOM Profile relative URL resolution to respect `document.baseURI`, allowing the shared Home shell to run correctly at `/home/`.
- Added explicit `/home/` rewrite fallback for hosting environments with inherited DirectoryIndex quirks.
- Activated and updated `docs/DOMAIN-LANDING-STANDARD.md`.
- Verified domain-root, `/home/`, release-project landing, Instance Project landing, missing-project fallback, module-disabled fallback, and subdirectory installation routing.
- Re-ran mobile viewport, PHP/JS syntax, deployment-manifest integrity and Instance Vault hot-drop checks.

# LOOM 0.15.07 - Mobile Identity Entry + Domain Landing Plan

- Rebuilt the narrow-screen first-run / Switch User layout around `100dvh` and a `minmax(0,1fr)` scrolling content region.
- Added safe-area-aware mobile padding for modern phones and notched displays.
- Made Switch User action groups sticky/reachable and stacked their buttons to full-width touch targets on phones.
- Made create-guest, first-guest and login submit buttons remain reachable inside the identity-entry scroll region.
- Added a compact mobile LOOM identity header and an extra short-viewport layout for landscape/small-height devices.
- Added dialog semantics (`role=dialog`, `aria-modal=true`) to the identity entry surface.
- Added `docs/DOMAIN-LANDING-STANDARD.md` describing the planned global project-at-domain-root architecture, `/home/` recovery route, Instance Vault configuration, server front controller, mount context, Admin controls and safety tests.
- Domain landing remains planning-only in this release; default routing behavior is unchanged.
- Re-ran mobile render, JS/PHP syntax, manifest-integrity and Instance Vault hot-drop checks.

# LOOM 0.15.06 - Identity Switching, Orb Defaults + Canonical Home Version

- Changed `core.ui.background-orbs` to default disabled in Green Beans and baseline project packages/templates.
- Changed the Green Beans project background-orb provider to default disabled as well.
- Updated module discovery/Admin catalog semantics so manifest-disabled modules remain visible and an explicit Admin Module Control enable override can force-load them.
- Updated fallback registries/runtime filtering so static fallback data also respects `enabled: false`, and scoped session registry caches by LOOM release.
- Added **New guest** and **Choose guest** directly to Switch User while a permanent account is active.
- Made the identity chooser scroll-safe so New Guest controls cannot disappear below the viewport on smaller/shared devices.
- Fixed the User Profile dock Switch User button inheriting the 34×34 close-button styling; it now has a dedicated responsive text-button layout.
- Removed stale hardcoded Home release labels and made Home display the canonical version returned by `api/version.php`.
- Added explicit anti-cache headers to version/changelog endpoints and re-check Home version on `pageshow` and when a tab becomes visible.
- Re-ran mobile render, syntax, module discovery/force-enable, manifest-integrity and Instance Vault hot-drop checks.

# LOOM 0.15.05 - Admin Routing, Showcase Frame + Mobile Hardening

- Fixed `/admin/setup/` routing by allowing `admin/.htaccess` to use `index.php index.html` as directory indexes. `/admin/` still prefers the PHP Admin console while `/admin/setup/` can load its HTML entrypoint.
- Updated Showcase core module to 1.0.1. Its body now integrates directly with LOOM's collapse/expand frame instead of rendering a second rounded top border beneath the frame header.
- Added overflow/min-width guards and mobile header sizing to LOOM collapsible module frames.
- Hardened project-shell navigation on phones by allowing the top bar/actions to wrap instead of squeezing or overflowing.
- Hardened Showcase at tablet/phone widths, including single-column media/copy layout, wrapped long titles/bios, and smaller mobile padding.
- Hardened Social Links mobile wrapping/touch targets.
- Hardened Admin narrow-screen grids, toolbars, orb rows, HTML Framer controls, tables, and Showcase manager.
- Hardened first-Administrator setup for narrow phones with stacked full-width actions and reduced spacing.
- Revalidated viewport metadata, narrow-screen CSS contracts, JS/PHP/JSON syntax, deployment manifest integrity, and clean Instance Vault hot-drop behavior.

# LOOM 0.15.04 - Social Links

- Added project-scoped core module `loom.social-links`, enabled by default but visually empty until at least one link is configured.
- Social Links injects into the standard project footer immediately below project branding and above More Tools.
- Added Admin URL fields for Facebook, Instagram, YouTube, TikTok, Website and one Custom Link.
- Added a curated Custom Link icon selector from the same Font Awesome Free family.
- Bundled transparent resolution-independent SVG path artwork locally; no CDN is required at runtime.
- All social-link icons share a single color source: Project default, LOOM black, or a manual color.
- Project Identity now includes a persistent project footer/social icon color.
- Green Beans defaults its project social color to `#279E38`; baseline/new projects default to `#000000`.
- Runtime URL normalization accepts ordinary domain-style pasted links and limits rendered links to HTTP/HTTPS.
- External links open in a new tab with `noopener noreferrer`.
- Formally retired legacy `launch.bat`; deployment metadata includes a package-priority tombstone for the stale server copy.

# LOOM 0.15.03 - Live Release Reload

- Added global `loom.release.watch` core module, enabled by default.
- Open LOOM browser sessions now poll canonical deployment state and auto-refresh after a completed deployment.
- `api/version.php` now exposes a deployment fingerprint derived from the committed deployment manifest.
- Release checks require canonical health to be `healthy` and confirm a changed fingerprint before refresh.
- Manifest-last deployment semantics prevent refreshes during partially transferred releases.
- Same-version hot patches can trigger refresh because the watcher compares deployment fingerprints, not only version numbers.
- Added a short full-screen LOOM update notice before refresh.
- Refresh preserves route/query state and adds cache-bust parameters.
- Added `loom:release-will-reload` browser event for modules that need to preserve transient draft state.
- Added a loop guard that falls back to a manual-refresh banner if stale caches repeatedly serve old client code.
- Admin can disable automatic refresh or tune polling/notice timing through the Live Release Reload global module.
- Updated stale Pegboard/Action Registry cache-bust tokens to the current release.

# LOOM 0.15.02 - First-Run Identity + Guided Admin Setup

- Added mandatory first-guest onboarding on truly new browser installations.
- First guest can choose an initial username or leave it blank for a unique `LOOMUser-XXXXXX` default.
- New guest profiles use the canonical LOOM default avatar instead of synthetic preset/avatar choices.
- Explained shared-browser guest separation and future **Switch User** guest creation before the first profile is created.
- Removed accidental implicit Admin bootstrap from account/profile status endpoints and made bootstrap opt-in by default.
- Added guided `/admin/setup/` ownership flow that clearly shows which LOOM identity will receive Administrator authority.
- Explicit Admin claim now links/promotes an already signed-in permanent account immediately; a bootstrap guest can create a permanent Admin account from the setup page.
- Fresh `/admin/` requests redirect to guided setup until the first Admin exists.
- Replaced broken/missing shell glyphs with fixed full-color emoji: 🏠 LOOM Home, 🔄 Switch User, 👤 User Profile.
- LOOM Home project-branding strips now use fit-content width inside each project card.
- Profile Dock advanced to 1.0.2.

# LOOM 0.15.01 - Showcase Core Module

- Added `loom.showcase`, a universal project-scoped core content module.
- Showcase displays a project image plus summary in the normal LOOM content flow.
- Showcase follows the project's canonical LOOM bio by default, so project-profile bio edits flow through automatically.
- Added a Showcase-only custom bio override and one-click **Use Project Bio** reset in Admin.
- Added image drag/drop and file-picker upload in Admin.
- Showcase images persist at `instance/projects/<project>/overlay/assets/showcase.png` and remain outside release ZIPs/Git.
- Showcase appears in Module Control and can be enabled/disabled independently per project.
- Clean Instance Protocol, Instance Projects, HTML Framer, Git compatibility, and explicit Admin consent remain unchanged.

# LOOM 0.15.00 - Project Studio + Module Control + Git Era

- Added first-class Instance Project creation from LOOM Home.
- User-created projects now live beneath the Instance Vault and survive release hot drops by construction.
- Added a generic package-owned Instance Project runtime shell and protected project-file gateway.
- Extended project discovery, branding, modules, archive/restore and project URLs to support both release-managed and Instance Projects.
- Added Admin Module Control Center spanning global core, project core, project-specific and HTML Framer modules.
- Added persistent per-project and global module enable/disable states.
- Added explicit first-run Admin consent; project pages no longer auto-claim bootstrap Admin.
- Restored prominent Switch User access to the soft-login guest profile chooser.
- Added Git compatibility metadata and workflow documentation.
- Green Beans maintenance version advanced to 0.5.0.

# LOOM 0.12.16 — HTML Framer

- Added `loom.html-framer`, a new LOOM-owned project core module.
- Added multi-ZIP frame management in Project Settings.
- Every published HTML frame is exposed to the runtime as a distinct dynamic LOOM module.
- Added safe ZIP inventory/extraction limits and static-file allowlisting.
- Added deterministic entrypoint resolution with explicit Admin selection for ambiguous multi-HTML packages.
- Added local relationship discovery, unique-path repair, CSS/JS dependency-root discovery, and automatic orphan-root attachment.
- Added sandboxed iframe execution without same-origin privilege.
- Added persistent Instance Vault storage for uploaded packages.
- Added runtime frame serving with dynamic HTML/CSS/JS local-reference virtualization.
- Added HTML Framer health reporting and authoring documentation.
- Green Beans maintenance version advanced to 0.4.8.

# LOOM 0.12.15 — Retired Cleanup + Green Beans Defaults

- Reasserted Hello World retirement tombstones at the new release revision.
- Added explicit retired-directory metadata for `core-modules/hello-world-autocoder-test`.
- Updated Green Beans default bio to: Green Beans is the family food app that turns "what are we doing for dinner?" into a plan, a shopping list and a table full of happy people. Say what you need, approve what you like and share the good stuff. Plan. Eat. Share.
- Added a LOOM Home icon and centered shell-button content.
- Aligned Profile Dock button sizing/centering with LOOM Home.
- Green Beans maintenance version advanced to 0.4.7.

# LOOM 0.12.14 — Polished Footer + Default UI

- Removed `core-modules/hello-world-autocoder-test` permanently and added release tombstones so deployed copies are deleted by the LOOM Deployer.
- Footer Bar advanced to 1.3.0.
- Added fit-content-only project-branding controls for total extra width and height.
- Default fit-content branding spacing is 100px total horizontal and 20px total vertical.
- Both controls allow 0–200px while compact-screen CSS constrains extremes responsively.
- Added generic Admin conditional-field visibility for settings declared with `visibleWhen`.
- Upgraded LOOM visual defaults across the design system, shared shell chrome, default project shell, Home, identity entry, and footer without changing workflows.
- Green Beans maintenance version advanced to 0.4.6.
- Clean Instance Protocol remains the storage baseline.

# LOOM 0.12.13 — Clean Instance Protocol

- Declared the Instance Vault a permanent storage protocol, not a compatibility migration.
- Removed pre-Instance filesystem rescue/import logic and its migration marker.
- Removed `project.json` fallback reads; package projects use `project.default.json` only.
- Removed the historical one-off Green Beans reset migration.
- Added fail-loud Instance Vault initialization and a runtime `instance/.loom-instance.json` protocol marker.
- Added Instance Vault health reporting.
- Kept all mutable filesystem state under `instance/**`; release archives still contain no real Instance Vault.
- Formalized hot-drop behavior and the destructive exceptions in `docs/INSTANCE-PROTOCOL.md`.
- Green Beans maintenance version advanced to 0.4.5.

# LOOM 0.12.11 — Instance Vault

- Introduced `/instance` as the single protected mutable filesystem root.
- Release packages contain **no real `/instance` directory**; only `instance.sample/`.
- Database credentials moved from legacy `data/admin/database.json` to `instance/config/database.json`.
- All filesystem fallback/runtime state now resolves beneath `instance/data/`.
- Project profile customization moved to `instance/projects/<project>/project-overrides.json`.
- Custom project logos/favicons/assets now use a persistent project overlay beneath `instance/projects/<project>/overlay/`.
- Release project metadata renamed from `project.json` to `project.default.json`, eliminating a major blind-overwrite hazard.
- Added safe asset proxying for persistent project assets while `/instance` remains web-denied.
- Project archives moved beneath the Instance Vault.
- Added non-destructive first-boot migration from legacy 0.12.xx filesystem state.
- Existing legacy state is copied, not deleted, preserving rollback options.

# LOOM 0.12.10 — Canonical Runtime Version Attribution

- Removed hard-coded `v0.12.04` from every reusable/project/template loader.
- Project loaders now resolve the displayed LOOM release from the canonical version API and log that same resolved release.
- Removed the independent static LOOM Brand release literal; brand attribution resolves through canonical release authority.
- `api/version.php` now validates the canonical manifest against engine configuration without requiring a duplicated branding constant.
- Removed ancient footer `0.11.26` fallback labels.
- Advanced active runtime cache-busting references to 0.12.10 across Home, project apps, Admin, Pegboard, and Action Registry.
- Loader module advanced to 1.3.1; Green Beans maintenance version advanced to 0.4.3.
- Production-safe, code-first release boundary from 0.12.09 remains intact.

# LOOM 0.12.09 — Production-Safe Releases & Green Beans Branding

- Release archives no longer ship installation-specific runtime/user payloads.
- Added explicit persistent-storage/release boundary documentation.
- Green Beans base logo replaced with the exact user-provided transparent PNG.
- Green Beans canonical brand colors updated to Dark Bean `#279E38` and Light Bean `#A9DF4F`.
- Default project logo assets are release-owned; custom uploaded project assets remain server-priority.
- Added protected `data/admin/database-backups/.htaccess`.
- Added missing deployment metadata headers found during the 0.12.08 audit.
- Manifest advances to v3 with clean-release metadata while preserving field-level project ownership.

- Added explicit project sandbox guardrails with safe project-path resolution and default-deny cross-project boundaries.
# LOOM 0.12.08 — Foundation Ten
- Hardened the guest chooser for project subpaths, authenticated user switching, non-scrolling profile pagination, unique soft guest names, and durable SQL mirrors.
- Migration bootstrap now performs one-time additive table creation instead of rewriting the migration ledger on every request.
- Health reporting now correctly recognizes an initialized SQL database and reports the new foundation surfaces.

This release deliberately implements only ten high-leverage foundations before broader project expansion:

1. Explicit Guest Profiles + Soft Login.
2. Identity Lineage + Payload Generations.
3. Schema Evolution Ledger.
4. Project Sandboxes + Guardrails.
5. Capability Contracts + Scoped Capabilities.
6. LOOM Design-System Foundation.
7. Health + Failure Framework.
8. Human-Readable Audit + Causal Event Foundation.
9. Session Replay Capture Foundation v1.
10. Performance + Cache Discipline.

Maintenance included: field-level deployment ownership for active project metadata, canonical 0.12.08 release authority, and deployment/listener verification compatibility. The changes are additive; existing account, guest, avatar, project-state and database records remain supported.

# LOOM 0.12.07 — Green Beans Meal Composition

- Added Green Beans project module `project.meal-creator` with persistent named meals and Shopping List ingredient composition.
- Added LOOM-native project extension contracts `green-beans.shopping-list` and `green-beans.meal-creator` for clean module-to-module cooperation.
- Shopping List now distinguishes manual groups from meal-owned groups and supports multi-meal ingredient membership.
- Added conditional **Add as Meal** UI and **Shift + Enter** quick creation when Meal Creator is discovered.
- Meal edits synchronize Shopping List groups; meal deletion dissolves grouping without deleting ingredients.
- New ingredients entered from Meal Creator are automatically added to Shopping List.

# LOOM 0.12.06 — Transactional Release Authority

- `/.loom-deployment.json` is now the canonical platform release authority.
- Added `api/version.php`; LOOM Home, Admin, shell branding, and changelog current-version output resolve the canonical release from that endpoint rather than trusting independent hard-coded badges.
- Release deployment is designed as a transaction: package/source files first, live server state preserved, release manifest committed last only after package-managed files verify.
- A server cannot legitimately claim the new LOOM release merely because one README or one module arrived.

# LOOM 0.12.05 — Stable Branding & Footer Defaults

- Whole footer default corrected to **full width**.
- Project-branding row default corrected to **fit-content + centered**.
- Removed the header wordmark's resize feedback loop that could rapidly oscillate font/logo sizing near responsive boundaries.
- Wordmark fitting now measures font metrics offscreen and responds only to stable container/viewport width changes.
- Logo host sizing now changes through CSS breakpoint rules instead of a one-time JavaScript media-query decision.

<!-- @loom-file release=0.15.38 revision=55 policy=package-priority -->
# LOOM 0.12.04 — Deployment Metadata & Authority

- Added `/.loom-deployment.json`, covering every shipped file with per-file revision, release, hash and deployment policy.
- Added explicit `package-priority`, `server-priority`, `server-only`, and `causal` authority models.
- Live server-mutated state is protected from stale package overwrite.
- Package/source files can now be compared by per-file LOOM revision before causal fallback.
- Added safe in-file `@loom-file` headers where the file format permits comments.
- Added `docs/DEPLOYMENT-METADATA-STANDARD.md` for human and AI developers.
- Deployment source-of-truth never relies on filesystem mtime.

# LOOM Changelog

## 0.12.04 — Starter Chrome + Electric Energy Field

- Changed reusable project Header Bar default width to `fit-content` with branding aligned left.
- Changed reusable project Footer Bar default width to `fit-content` while keeping project branding/logo centered.
- Increased default background particle/orb volume from 14 to 70 and expanded the Admin density range.
- Reworked LOOM's default ambient background from large glass bubbles into small glowing electric circuitry particles, sparks, and wisps.
- Added a timed extensible special-particle channel; LOOM defaults to an animated cube using randomized cube motion presets.
- Extended `core.ui.background-orbs.provider` so project modules can independently override the special particle.
- Green Beans now uses its bundled pea pod for normal ambient particles and a carrot emoji (`🥕`) as the occasional special particle.

## 0.12.02 — Universal Core Test Module + Project Management

- Added **Hello World Auto Coder Test** (`loom.core.hello-world-test`) as a LOOM-owned `scope: project` core module. It is discovered from `/core-modules`, injected into every project, appears in the Action Registry/Pegboard lifecycle, and hot-loads through the existing runtime polling without copying the module into each project.
- Preserved the v0.12.01 **Page Styling** project-core module and formalized project-core modules as the preferred pattern for LOOM-owned capabilities that must exist in every project while retaining project-safe runtime composition.
- Turned **LOOM Home → Deploy / Add Project** into a working baseline-project creator with project name, slug, tagline, description, bio, version, theme key, and optional logo upload.
- Added `/templates/projects/baseline` with the standard reusable LOOM project shell/core stack plus automatic universal project-core injection.
- Added fast project-identity management directly on LOOM Home with a dedicated Admin overlay and a deep link into the selected project's full Admin settings.
- Added project identity controls to **LOOM Admin → Project Settings**, including name, tagline, description, bio, version, theme, and canonical `assets/logo.png` upload/replacement. Existing projects receive the same controls.
- Project taglines now flow from canonical `project.json` metadata into the baked project shell and reusable Branding module unless that module intentionally provides an explicit tagline override.
- Added permanent modular-composition and project-management standards for future human/AI developers: self-contained modules, declarative Admin controls, Action Registry visibility, explicit extension contracts, and teammate-style optional composition are now documented as architectural requirements.
- Release packaging policy is now explicit: LOOM releases are delivered as **complete repackaged source trees**, not delta-only update archives.

## 0.12.01 — Project Page Styling Core Module

- Added `loom.page.styling` as a LOOM-owned core module that automatically runs inside every project.
- Introduced **project-scoped core modules**: globally supplied by LOOM, independently configurable per project.
- Added **Admin → Project Settings → Page Styling** without copying styling code into individual projects.
- Page Styling controls the page background mode and palette, text/accent/muted colors, shared surface/border palette, surface opacity, shared corner radius, font family and scale, content width, page gutter, and module spacing.
- Defaults intentionally reproduce the existing soft Green Beans/LOOM project-shell appearance, so upgrading does not restyle a project until an administrator changes its values.
- Live module discovery now merges project-scoped core modules with each project action tree and fingerprints project overrides for hot reload.
- Static Green Beans fallback registries include the new core module for degraded/offline registry operation.

## 0.11.20 — Project-First Loader + Project Update Logs + Profile Picture Reuse

- Rebalanced the project Loader so project branding is the visual centerpiece again.
- Restored the animated project-logo presentation with independently orbiting wordmark lines and floating brand particles.
- Moved the animated LOOM cube to a small `Powered by LOOM` signature at the bottom of the Loader.
- Kept global Admin-selected LOOM cube motion path/speed behavior for that restrained Loader signature.
- Installed the Green Beans Project Update Log and seeded a Green Beans-only `projects/green-beans/CHANGELOG.md`.
- Project module discovery now ignores stale legacy LOOM platform-update modules if an older deployment folder remains on disk.
- The LOOM platform changelog remains exclusively on LOOM Home.
- Renamed the project-specific User Profile header from `LOOM identity` to `Project identity` and clarified project identity details.
- Added `Pull Picture from Project` to the global LOOM profile picture controls.
- Added server-side project-avatar resolution, including project custom pictures and provider-declared project defaults.
- Fixed global LOOM profile-picture image serving through `profile-avatar.php?scope=global`.
- Extended the avatar-provider contract with `default_asset` metadata and updated the permanent plugin-authoring manual.

## 0.11.20 — LOOM Branding, Global Settings, and Update-Log Separation

- Added the canonical LOOM cube mark as a first-class platform asset and procedural animation component.
- Integrated the supplied v19 motion system with ten selectable animation paths: Hero Orbit, Comet Sweep, Tidal Arc, Prism Roll, Halo Drift, Zenith Dive, Gyro Bloom, Meteor Bank, Ribbon Spiral, and Pulse Carousel.
- Added **Admin → LOOM Settings**, separate from **Admin → Project Settings**.
- Added global core settings for Animated LOOM Mark, Loader Experience, and LOOM Home Update Log.
- Animated-mark path, speed, and enabled/static state now apply consistently wherever the animated LOOM mark is used.
- Strengthened LOOM branding on LOOM Home, Admin, Pegboard, and Action Registry while keeping in-project branding restrained.
- Project footers now use clear `Powered by LOOM` language instead of internal terms such as `Runtime active` or registry-source names.
- Rebuilt the project Loader around the animated LOOM mark, project branding, module progress, and rotating useful LOOM tips.
- Moved the **LOOM Update Log** out of projects and onto LOOM Home.
- Added a separate reusable **Project Update Log** template for projects that maintain their own `CHANGELOG.md`.
- Extended `api/changelog.php` with explicit LOOM-vs-project scope.
- Added protected global-settings persistence and optional SQL mirroring through `loom_global_settings`.
- Added extensionless HTML/PHP rewrite support plus explicit project-app `DirectoryIndex` handling.
- Added `docs/LOOM-GLOBAL-SETTINGS-STANDARD.md` and expanded the permanent plugin authoring manual.


## 0.11.02 — Project Branding
- Added reusable `core.ui.branding` module.
- Browser title defaults to project name only.
- Optional Admin tagline appends to the title only when non-empty.
- Removed `LOOM Blank Template` from the active/template browser title.
- Project favicon defaults to the current Logo module image.
- Admin can upload any browser-readable image; it is normalized to 64×64 PNG client-side and wrapped server-side into `assets/favicon.ico`.
- Admin can switch favicon back to the current project logo at any time.
- Added declarative Admin `tools` schema with reusable `favicon` tool type.
- Branding module participates in Pegboard lifecycle and module discovery.

## 0.11.01 — Reusable Bootstrap Loader

- Added reusable core LOOM `Project Loader` module.
- Added standardized `module.bootstrap.role = loader` manifest behavior (schema 1.4).
- Loader is imported and activated before normal project modules and does not consume the normal five-digit project module order; it displays as `BOOT`.
- Loader reuses the project's effective Logo and Logo Text configs instead of duplicating branding.
- Added centered full-screen loading overlay with modular orbit/swirl animation using project logo and both logo-text lines.
- Added live `loaded / total modules ready` counter and progress bar.
- Loader itself is excluded from the progress total. Module failures are counted separately.
- Added runtime module-descriptor introspection APIs for reusable bootstrap/core modules.
- Preserved automatic content-hash asset cache busting for Loader logo imagery.
- Added `docs/LOADER-STANDARD.md` and updated module-authoring guidance.
- Versioning policy adopted: remain on `0.11.xx` and increment only the third segment through `0.11.99` before advancing the minor line.

## v0.11.0 — Permanent Accounts, SQL Persistence, Admin Hardening

- Added server-wide unique usernames. Usernames cannot be cleared once set; they may be changed to another available name.
- Added optional permanent email/password accounts. Account registration is allowed only after a username exists.
- Added cross-browser sign-in foundation: a permanent user ID can bind multiple LOOM client IDs.
- Initial Admin remains first-client bootstrap. Once that Admin creates a permanent account, Admin privilege follows that signed-in user account.
- Added Admin-only visibility for LOOM Admin, Pegboard, Action Registry, archive/restore, and developer controls.
- Added server-side 403 protection for Admin, Pegboard, Action Registry, telemetry history APIs, and project mutation APIs.
- Added MySQL/MariaDB configuration, connection testing, schema initialization, and temporary-data migration from LOOM Admin.
- Until SQL is connected, profiles/accounts/settings/telemetry remain temporary local/server data. Once initialized and migrated, SQL becomes the durable persistence layer while local files remain safety/cache copies.
- Added Header Bar controls for Full Width / Fit Content and branding Left / Center / Right.
- Logo scale is normalized so Admin 50% = legacy 100%, and Admin 100% = legacy 200%.
- Logo Text font size range now reaches 144px.
- Removed the obsolete packaged `green-beans-old-1` archive and added one-time cleanup of that exact retired archive on existing installs.
- Consolidated project history into this single `CHANGELOG.md`. Future releases append here only.

---

# LOOM v0.10.0 — Admin + Identity Privileges

- Added first-client initial administrator bootstrap.
- Added `Privilege: Admin` / `Privilege: User` to LOOM identity/profile presentation.
- Administrator authorization uses a secret HttpOnly browser credential in addition to the stable Client ID.
- Added server-gated `/admin/` page.
- Added declarative `admin_settings` manifest schema (`1.3`).
- Added persistent, validated per-project module config overrides.
- Admin settings participate in module fingerprints for normal LOOM reload/discovery.
- Header Bar admin controls: height, corner radius, background color.
- Project Logo admin control: scale.
- Logo Text admin controls: top/bottom text, font size, font family, both colors.
- User Profile intentionally exposes no admin settings.
- Added reusable Header Bar, Logo, Logo Text, and User Profile module templates.
- Preserved Pegboard, archive/restore, presence resilience, optical alignment, and automatic cache busting.

---

# LOOM v0.3.1 — Explicit Asset Paths Hotfix

This release fixes the Green Beans logo resolver ambiguity discovered in v0.3.

## Fixed

- `core.ui.load-logo` now requests the explicit project-relative path `assets/logo.png`.
- `api/resolve-asset.php` no longer hunts the project root and then falls back to `/assets`.
- The resolver now walks the requested relative path only, with safe traversal protection and case-insensitive segment matching.
- Added `ctx.resolveAssetPath(path, scope)` to the LOOM module context.
- Kept the older `ctx.resolveAsset(name, scope)` only for compatibility; new modules should use explicit paths.
- Removed the logo module's filename-search configuration and direct fallback asset URL.
- Updated the static fallback registry to match the real filesystem-discovered module.

## Preloaded Green Beans logo

The supplied Green Beans logo is included at exactly:

`projects/green-beans/assets/logo.png`

Its SHA-256 is recorded during package validation.

## Resolver truth

- Module exists → Pegboard capability bulb exists.
- Module active and logo mounted → bulb stays lit for that runtime.
- Session closes/expires → lifecycle system turns the active bulb off.
- Asset location is explicit and deterministic; a root-level `logo.png` cannot override this module.

---

# LOOM v0.3 — Presence + Lifecycle

- Added lease-backed runtime presence.
- Heartbeat: 2.5 seconds; default lease: 12 seconds.
- Added graceful page-close lifecycle endpoint using sendBeacon-compatible JSON.
- Added server-side stale-session reaper.
- Expired sessions now produce `session.expired` plus inferred inactive events for every held action.
- Closed sessions produce final inactive events plus `session.end`.
- Added same-runtime heartbeat-resume handling after an inferred expiry.
- Pegboard now has a built-in Session Presence bulb with heartbeat / graceful close / expiry sub-lights.
- Pegboard current illumination is corrected from the authoritative presence snapshot.
- Session analytics now show held bulbs, lease remaining, runtime ID, and disconnect/end reason.
- Core and Logo bulbs reliably turn off on graceful close or heartbeat expiry.
- Runtime unload is distinct from capability removal (`module.unloaded` vs `module.removed`).
- Standardized Green Beans logo at `projects/green-beans/assets/logo.png`.
- Updated Hostinger/MySQL target schema with lease expiry, active actions, end reason, expired status, and inferred event support.

---

## v0.3.1 hotfix

Asset resolution is now explicit-path based. The Green Beans logo module resolves only `projects/green-beans/assets/logo.png`; same-named files elsewhere cannot override it. The user-supplied logo is preloaded there.

---

# LOOM v0.4.1

- Added self-contained Green Beans `logo text` module.
- Renders stacked `GREEN` and `BEANS` wordmark beneath the logo.
- Uses two logo-matched green palettes and fits both words to the same width.
- Module is self-contained: manifest + single action.js, no separate CSS dependency.

---

# LOOM v0.4.2

- Rebuilt the Green Beans feature stage as a vertical collision-safe module flow.
- Fixed logo / wordmark overflow and removed the old logo debug pseudo-label collision.
- GREEN and BEANS remain equal-width but now resize to the branding card instead of overflowing it.
- Logo card expands naturally to contain both the image and wordmark.
- Shopping List receives its own layout row and no longer competes for the logo module position.
- The `Waiting for feature modules…` placeholder now hides after real modules mount.
- Responsive sizing improved for desktop and mobile.

---

# LOOM v0.4 — Green Beans Shopping List Module

Adds the first simple application feature after the logo proof module.

## New module

```text
projects/green-beans/actions/shopping/list/shopping-list/
  manifest.json
  action.js
```

`action.js` is deliberately self-contained: it injects its own scoped CSS, builds its own UI, stores its own state, and emits its own LOOM telemetry.

## Shopping List behavior
- Add one line item at a time.
- Edit one existing item at a time.
- Remove one item at a time.
- Persist the list in browser `localStorage`, scoped to the Green Beans project and current LOOM user/client identity.
- Restore the list automatically in later sessions on that browser/user identity.

## Pegboard behavior
`shopping.list.load` is a stateful system capability and remains lit while its UI is mounted.

Its sub-lights are:
- Restore saved list
- Mount shopping list UI
- Add item
- Edit item
- Remove item

Add/edit/remove sub-lights flash through active → completed → idle and every mutation is timestamp-logged.

---

# LOOM v0.5.1 — Protected Module Ordering

- Added a real five-digit module/card order key to LOOM manifests.
- Lower values discover, activate, and render first.
- Reserved `00000` permanently for `core.ui.load-logo` while that module is installed.
- Normal modules cannot override or jump ahead of reserved `00000`; non-logo requests for zero are clamped behind it.
- Current Green Beans order:
  - `00000` — Green Beans Logo (protected, absolute first)
  - `00001` — Logo Text (nested branding helper)
  - `00010` — Shopping List
  - `00020` — Meals
- Live filesystem discovery, static fallback discovery, runtime activation, DOM reflow, telemetry, and the Action Registry all expose/use the same effective order.

---

# LOOM v0.5 — Meals + Grouped Shopping

- Added self-contained Green Beans Meal Builder module.
- Meals are named groups of one or more shared ingredients.
- Meal creation can reuse existing shopping ingredients or inject novel ingredients into the shared list.
- Meals can be edited to rename them and add/remove ingredients.
- Shopping List upgraded to shared food model v2 and automatically groups ingredients by meal.
- The same ingredient can appear in multiple meals without duplication in the underlying model.
- Checking an ingredient in one meal appearance checks it everywhere.
- Meal groups can be completed/uncompleted as a unit.
- Entire shopping list can be completed/uncompleted as a unit.
- Existing v1 shopping list data migrates automatically into the shared model.

---

# LOOM v0.6

- Added canonical meal course field with Breakfast, Lunch, Dinner, Snack, Drink, and À la carte.
- Upgraded Meal Builder to create/edit courses and display linked recipe counts.
- Upgraded grouped Shopping List to display and sort meal groups by course.
- Added Recipes module at module order `00030`.
- Recipe fields: name, freeform text body, optional meal link.
- Added All Recipes and Meals With Recipes views.
- Recipes automatically become unlinked rather than deleted when their meal is deleted.
- Shared food model schema bumped to v3 while preserving the existing localStorage namespace for seamless migration.

---

# LOOM v0.7 — Green Beans Shared Object Platform

This release moves Green Beans from isolated widgets to a shared stable-ID object graph.

## New foundation
- `core.data.object-hub` at module order `00005`.
- Canonical browser-local object model v5 with migration from prior Green Beans food-model/object-model keys.
- Global freeform `amount` / quantity field on every Green Beans object, defaulting to `"1"`.
- Stable IDs and cross-module references.
- Shared purchase and consumption events.
- Cross-module change event for real-time updates.

## Module order
- `00000` Logo — protected absolute-first slot
- `00001` Logo Text
- `00005` Object Hub — invisible core data service
- `00010` Shopping List
- `00020` Meals
- `00030` Recipes
- `00040` Scheduling
- `00050` Bought / History
- `00060` Consumption
- `00070` Nutrition
- `00080` Research
- `00090` Home Stock
- `00100` Preferences
- `00110` Collections
- `00120` Sessions
- `00130` Explore

## Shopping / amounts
- Shopping item amount defaults to `1`.
- Amount is freeform text: `1 bushel`, `a lot!!!`, or any other text is valid.
- Bought state remains shared across every meal appearance.
- Purchase events retain timestamps and amount/name snapshots.

## Scheduling
- Live current local date/time.
- Month calendar with Today / previous / next controls.
- Stack multiple Green Beans object references on the same day.
- Inspect/remove scheduled entries.

## Bought / History
- Current bought items grouped into configurable purchase windows.
- Default grouping window: 120 minutes (manifest setting).
- Unbuying updates the current-bought view immediately.
- Append-only purchase event history remains visible.

## Consumption
- Not Yet Consumed and Consumed views.
- Green Beans enforces bought-before-consumed eligibility.
- Ingredients, meals, and recipes can participate; meals/recipes derive purchase eligibility from linked ingredients/meals.
- Consumption events retain timestamps and snapshots.

## Nutrition
- First-class nutrition records may target Green Beans objects.
- Serving, calories, protein, carbs, fat, fiber, sugar, sodium, and notes are stored now.
- Future math can build on this structure without replacing it.

## Research
- First-class research objects.
- Target + research name + freeform research body.
- All Research view.
- Objects With Research view.

## Home Stock
- Simple on-hand-right-now list.
- Add, edit amount/name, remove.

## Preferences
- Stuff We Love.
- Stuff We Hate.

## Collections
- Custom named bundles of arbitrary Green Beans objects.
- Create, edit membership, delete, and view all collections.

## Sessions
- Completed Green Beans Session snapshots freeze the entire current Green Beans working state.
- Snapshot includes shopping ingredients/status, meals, recipes, schedules, purchase/consumption events, nutrition, research, home stock, preferences, collections, and current LOOM runtime identity/action state.
- Later live edits do not mutate an already completed session snapshot.

## Explore
- Global searchable directory of all Green Beans object data.
- Includes Object Hub metadata, domain objects, LOOM action/module descriptors, local fallback telemetry, server sessions, and recent Pegboard/LOOM events when the PHP APIs are available.

---

# LOOM v0.8.1 — Heartbeat Resilience

## Fixed

- Heartbeat lease timeout no longer means a session ended.
- Missing a heartbeat now transitions presence from `live` to **`stale`**, preserving the session ID and last-known held-action snapshot.
- Stale sessions no longer emit inferred `action.state = inactive` events.
- A later heartbeat resumes the same session with `session.resumed` instead of creating a fake historical break.
- User activity (`pointerdown`, keyboard input, form changes, touch, focus), visibility changes, and network reconnection trigger an immediate throttled heartbeat in addition to the normal interval.
- Default heartbeat timing is now 5 seconds with a 45-second freshness lease.
- Pegboard renders stale sessions as amber/resumable rather than expired/finished.
- Pegboard preserves the last-known action snapshot while heartbeat freshness is stale.
- Explicit graceful close remains authoritative for ending a session and unloading held actions.

## Standard

A heartbeat is a **freshness signal**, never proof of session termination. LOOM may mark a runtime stale when freshness lapses, but only an explicit lifecycle close (or a future separately-defined abandonment policy) may end a session.

---

# LOOM v0.8 — Pegboard Organization + User Actions

## Engine

- Added manifest schema v1.1 with standardized `user_actions` declarations.
- Added runtime user-action APIs: `ctx.userAction`, `ctx.runUserAction`, `ctx.beginUserAction`, `ctx.completeUserAction`, and `ctx.failUserAction`.
- Added automatic domain-event-to-user-action bridging through `user_actions[].events`.
- User actions use normal timestamped `action.state` telemetry with `kind=user`, owner module metadata, and session/runtime identity.
- Pending/stateful user actions can participate in active presence snapshots.

## Green Beans instrumentation

- Upgraded all 16 current module manifests to schema v1.1.
- Added declared user actions to every interactive Green Beans module.
- Shopping, Meals, Recipes, Scheduling, Consumption, Nutrition, Research, Home Stock, Preferences, Collections, Sessions, and Explore now expose standardized user-action capabilities.
- System-only/view-only modules explicitly carry an empty user-action declaration rather than inventing fake interactions.
- Consumption view switching, Scheduling calendar navigation, and manual Explore refresh use direct runtime user-action calls.

## Pegboard

- Added auto-follow of the newest live session for the selected client/user.
- Added immediate same-browser new-session detection through the project BroadcastChannel/event bus, with sessions polling as fallback.
- Manual historical selection disables auto-follow until re-enabled.
- Reorganized modules into an ordered compact grid below a clear system spine.
- Added compact nested user-action pills inside module cards.
- User-action pills support active, completed pulse, failure, execution counts, click-to-inspect, and complete history.
- Added user-action count to session analytics.
- Added module/action filtering.
- Fixed accidental blue browser text selection while clicking or panning the Pegboard canvas.

## Registry / docs

- Action Registry now exposes declared user actions next to their owning module.
- Added `docs/LOOM-ACTION-SCHEMA.md`.
- Added `docs/PEGBOARD-STANDARD.md`.
- Added `docs/MODULE-AUTHORING-CHECKLIST.md`.
- Established the rule that future schema/action/Pegboard changes must update documentation in the same push.

---

# LOOM v0.9.1 — Project Archive + True Green Beans Scratch Reset

- Added Active and Archived tabs to LOOM Home.
- Added Archive action for active projects.
- Added Restore action for archived projects.
- Added collision-safe `-old-N` naming; archive/restore never overwrites another project.
- Added server-side archive preservation for project files, logs, and presence data.
- Added browser-local project storage namespace migration during archive/restore.
- Added one-time `green-beans-reset-v1` migration so an in-place upgrade actually removes legacy Green Beans modules instead of merely overlaying a smaller ZIP.
- On first run, the existing Green Beans project is archived as `green-beans-old-1` when available, then replaced from the clean template.
- Fresh Green Beans contains only `00000` Logo and `00001` Logo Text.
- Updated the fresh project to the newly provided Green Beans logo.

---

# LOOM v0.9.2 — Layout Regions + Modular Header

- Added LOOM module manifest schema `1.2` with standardized `presentation` placement/layout rules.
- Added runtime `ctx.mount`, `ctx.resolveMountTarget`, and `ctx.applyPresentation` helpers.
- Added `core.ui.header-bar` as protected order `00000` and a reusable full-width rounded header region.
- Header exposes `brand` and `utility` slots for independent child modules.
- Green Beans Logo is now `00001` and injects into `header-bar / brand`.
- Green Beans Logo Text is now `00002`, injects into the same brand slot, and therefore renders to the logo's right.
- Removed card/background responsibility from the Logo module; the Header Bar owns the white rounded surface.
- Active Green Beans remains a scratch build with only Header Bar + Logo + Logo Text.
- Preserved the full pre-reset build as `green-beans-old-1` under LOOM Archives.
- Updated module schema, authoring docs, fallback registry, API discovery payloads, protected ordering, and project metadata.

---

# LOOM v0.9.3 — Bounded Header Branding + Automatic Cache Busting

## Green Beans header fix
- Header Bar now owns separate `brand-media`, `brand-text`, and `utility` slots.
- Logo mounts only into the bounded `brand-media` slot.
- Logo image uses intrinsic-safe sizing with `max-width:100%`, `max-height:100%`, and `object-fit:contain`.
- Logo source dimensions can no longer determine or overflow the Header Bar layout.
- Logo Text mounts into its own adjacent `brand-text` slot, keeping GREEN / BEANS directly to the right of the mascot.
- Updated the canonical Green Beans `assets/logo.png` to the latest user-provided mascot logo.

## LOOM cache-busting standard
- `resolve-asset.php` now returns content-hashed asset URLs.
- Module JavaScript and CSS continue using content fingerprints through one standardized runtime cache-busting helper.
- Engine bootstrap JS, Pegboard CSS/JS, Registry JS, and project app bootstrap files are explicitly versioned at `0.9.3`.
- Project app URLs receive a content version from `projects.php`.
- Added root `.htaccess` no-cache policy as an additional Apache safeguard.
- Added `docs/CACHE-BUSTING-STANDARD.md`.
- Bumped the Green Beans reset migration to v3 so existing installs resync the latest blank template.

---

# LOOM v0.9.4 — Persistent User Profile + Lifetime Analytics

- Added reusable `core.user.profile` module at order `00010`.
- Added `reusable-modules/user-profile/` portable module source.
- Added persistent server-side user profile records under `data/users/`, keyed by a hash of the stable browser client ID.
- Users can choose and save a persistent display username.
- The username immediately updates the active runtime identity and subsequent Pegboard/session telemetry.
- Added lifetime current-project and all-LOOM analytics: sessions, events, user actions, unique actions, active days, tracked session span, failures, projects, recent sessions, and top user actions.
- Archived LOOM logs participate in all-LOOM lifetime analytics.
- Added standard runtime module APIs: `ctx.apiUrl`, `ctx.fetchApi`, `ctx.runtimeId`, and `ctx.setUserLabel`.
- Added `docs/USER-IDENTITY-STANDARD.md`.
- Bumped cache-busting/versioned engine bootstrap to 0.9.4.

---

# LOOM v0.9.5 — Green Beans Canonical Brand Refresh

## Green Beans branding
- Replaced the active and scratch-template Green Beans logo with the newly supplied mascot artwork.
- Canonical wordmark font is now **League Spartan** from Google Fonts, all caps.
- `GREEN` uses **#39A935**.
- `BEANS` uses **#A6D94E**.
- Removed the prior gradient wordmark treatment so the supplied brand colors are used directly.
- The logo remains contained inside the Header Bar `brand-media` slot.
- The wordmark remains independently injected into the Header Bar `brand-text` slot.

## Font loading
- The Logo Text module loads League Spartan itself and waits briefly for the font before fitting both lines.
- It retains system-font fallbacks if Google Fonts is unavailable.

## Cache behavior
- Existing LOOM content-hash image cache busting and module-fingerprint cache busting remain active.
- Replacing `assets/logo.png` changes its resolved asset hash automatically.

## Preserved
- Header/region layout contract.
- Project archive/restore.
- Persistent User Profile module.
- Pegboard presence/user-action standards.

---

# LOOM v0.9.6 — Header Wordmark Fit Fix

- Fixed the stacked GREEN / BEANS wordmark being clipped at the bottom of the Header Bar.
- Logo Text now fits against both available width and available height.
- Added a bounded Header Bar `brand-text` height on desktop and mobile.
- Added a final safety scaling pass for browser font metrics, zoom, and late Google Font loading.
- Preserved League Spartan, uppercase text, GREEN `#39A935`, and BEANS `#A6D94E`.
- Preserved the latest canonical mascot logo.
- Preserved LOOM automatic module/asset cache busting.

---

# LOOM v0.9.7 — Wordmark Vertical Center + Clip Fix

- Matched the Header Bar `brand-text` slot height to the mascot `brand-media` slot.
- Vertically centers the complete GREEN / BEANS wordmark against the mascot.
- Increased wordmark internal top/bottom breathing room.
- Removed clipping from the wordmark and brand-text slot.
- Increased line-height from the overly-tight prior value to protect League Spartan glyph bounds.
- Preserved exact branding: League Spartan, GREEN #39A935, BEANS #A6D94E.
- Preserved the latest canonical mascot logo and LOOM automatic cache busting.

---

# LOOM v0.9.8 — Optical Module Alignment

- Added reusable LOOM `presentation.layout.offsetX` and `offsetY` rules.
- Offsets use CSS `translate`, so they do not overwrite module transforms/animations.
- Green Beans Logo Text now receives a `10px` downward optical offset.
- This aligns the visible League Spartan wordmark with the visible center of the mascot instead of merely aligning their CSS boxes.
- Expanded the Header Bar brand-text safety height so the optical adjustment cannot clip.
- Preserved the canonical mascot logo, League Spartan, GREEN `#39A935`, BEANS `#A6D94E`, cache busting, User Profile, Pegboard, and archive/restore.

## 0.11.03 — Legacy username account unlock fix

- Fixed permanent-account email/password controls remaining disabled for users who already had a username from an older LOOM build.
- User Profile now detects an existing non-anonymous browser username when the newer server profile has no username yet.
- The legacy username is migrated through the normal server-side username reservation path, so duplicate-name protection still applies.
- After successful migration, permanent-account controls unlock immediately without requiring the user to rename themselves.
- If the legacy username genuinely conflicts with another server identity, the UI explains that it must be re-saved/changed instead of silently stealing the name.

## 0.11.04 — Authoritative Logo Text Font Size

- Fixed Logo Text Admin font size appearing to barely change.
- Removed the previous width-first fitter that immediately shrank large Admin-selected font sizes back toward the old wordmark width.
- `fontSize` is now authoritative: 72px renders at 72px, 120px renders at 120px, and 144px renders at 144px whenever the available screen/header can physically contain it.
- LOOM only performs an emergency proportional shrink when the selected size would genuinely overflow the available browser/header width.
- Header Bar now grows vertically and horizontally around large injected wordmarks instead of clipping or constraining them to the old brand-text box.
- The narrower GREEN/BEANS line is horizontally expanded to preserve the equal-width stacked wordmark without reducing the selected font height.
- Updated the reusable Header Bar and Logo Text core modules to the same behavior.

## 0.11.05 — Durable Profile Timestamps + Interactive Logo Shine

- Restored and emphasized **Profile Since** and **Last Saved** inside Identity Details.
- Legacy profiles missing timestamp fields are automatically backfilled from existing LOOM telemetry/account/file history and persisted.
- Fixed SQL profile reads so database row timestamps are not lost when an older payload JSON is missing those fields.
- Upgraded the reusable Project Logo module with a mouse-reactive specular shine.
- The shine follows pointer position and uses the logo image alpha channel as a CSS mask, so transparent background pixels never receive the reflection.
- Added a `bind-shine` internal Pegboard step; hover movement remains presentation interaction and is intentionally not recorded as semantic user-action telemetry.
- Preserved automatic asset cache busting and all existing Logo scale/Admin behavior.

## 0.11.06 — True Fit-Content Header + Native Profile Pictures

- Fixed Header Bar `Fit Content` still appearing full-width. The Header module now reapplies dynamic sizing after LOOM presentation mounting and uses true `max-content` shrink-to-content behavior with normal padding.
- Preserved Left / Center / Right placement for both full-width and fit-content headers.
- Added native LOOM profile pictures to the reusable User Profile module.
- Added a neutral unisex LOOM default avatar silhouette.
- Added upload/change profile picture controls. Browser uploads are center-cropped to 384×384 and compressed to WebP before server storage to keep long-term media small.
- Added explicit buttons to return to LOOM Default at any time.
- Added the `core.user.profile.avatar` project extension contract. Projects can provide a default avatar override and optionally an avatar creator without modifying LOOM User Profile.
- Added the first project avatar provider: Green Beans. New/default Green Beans users see a bean silhouette unless they have explicitly selected another avatar source.
- Added a Green Beans avatar creator with exactly ten bean colors. Applying a generated avatar saves it through the same compressed LOOM profile-picture pipeline.
- Added Project Default and Create Project Avatar buttons only when the active project actually provides those capabilities.
- Added permanent-account avatar promotion: a pre-account custom avatar follows the user when the client identity is bound to a permanent account.
- Added SQL metadata table `loom_user_avatar_profiles`; compressed image binaries remain protected filesystem media to avoid database bloat. Temporary permanent-user avatar preferences are included in the normal local→SQL migration.
- Permanent-account avatar mutation now requires the matching authenticated account; temporary pre-account clients continue using the stable client identity model.
- Added runtime extension lookup helpers and manifest `extensions` schema support.

## 0.11.07 — Green Beans Supplied Avatar Artwork

- Replaced the generated Green Beans project avatar artwork with the three user-supplied canonical files.
- Green Beans project default profile picture now uses the supplied `default-avatar.png`.
- Rebuilt the Green Beans avatar creator around exactly two current traits:
  - Bean Type: `String Bean` or `Bean Pod`
  - Background Color: arbitrary color picker / hex color
- Removed the old ten-color bean-body customization.
- String Bean and Bean Pod creator choices use the supplied transparent character artwork directly.
- Generated avatars preserve the complete character, render onto the selected solid background, and then flow through LOOM's existing 384×384 compressed profile-avatar upload pipeline.
- The generic LOOM avatar system remains unchanged: LOOM Default, Upload Image, Project Default, and Project Creator continue to be independently selectable.

## 0.11.08 — Green Beans Avatar Headwear Trait

- Added a third Green Beans avatar creator trait: `Headwear`.
- Current Headwear choices are exactly:
  - `None`
  - `Ball Cap`
- Ball Cap works independently with either existing Bean Type:
  - String Bean + None
  - String Bean + Ball Cap
  - Bean Pod + None
  - Bean Pod + Ball Cap
- Added the two user-supplied canonical ball-cap artwork files rather than synthetically drawing or positioning a hat.
- Headwear preview thumbnails automatically update to the currently selected Bean Type.
- Existing Background Color selection remains unchanged.
- The generated avatar continues through LOOM's standard 384×384 compressed profile-avatar storage pipeline.

## 0.11.09 — Trait Preview Icons

- Updated the Green Beans avatar creator headwear trait picker to use dedicated preview icons instead of full-character thumbnails.
- `None` now uses the supplied universal red X icon.
- `Ball Cap` now uses the supplied close-up cap artwork.
- Avatar rendering behavior is unchanged: the final generated avatar still uses the full supplied character artwork for each body/headwear combination.
- This prepares the trait UI for future traits by allowing trait pickers to use dedicated, reusable icons.

## 0.11.10 — User Administration, IP Metadata, and Project Bans

- LOOM now captures server-observed IPv4/IPv6 addresses as network metadata for client and permanent-user identities.
- User Profile Identity Details shows the current IP and known-IP count/history.
- Added protected Admin → Users tab with account/profile details, linked clients, IP history, project activity, profile timestamps, and avatar preview.
- Administrators can change a user's username, email, or password. Each mutation requires the literal server-validated confirmation phrase `CONFIRM`.
- Password changes are replace-only; current passwords/hashes are never shown. Replacing a password revokes previous login sessions.
- Added project-scoped soft bans. Banned accounts/profiles are never deleted and retain all stored data.
- Banned identities cannot load project modules or write project telemetry.
- Banned-user data is hidden from project telemetry views by default; Admin can explicitly enable `Include banned user data` without restoring project access.
- Added SQL/local persistence for IP history, project moderation state, and Admin audit events. Existing connected databases lazily create the new tables and can also safely rerun Initialize / Update Tables.
- Added reusable moderation helpers for future project data APIs.

## 0.11.11 — Administrator Home + Structured LOOM Update Log

### LOOM Home administrator clarity
- Added an unmistakable **Administrator access is active** banner on LOOM Home when the current server-authenticated identity is Admin.
- The banner explicitly explains that protected controls are present only for Admin and that regular users cannot see or directly access them.
- Grouped project developer controls into gold **ADMIN-ONLY PROJECT TOOLS** panels rather than presenting them beside ordinary project actions.
- Deploy, archive/restore, LOOM Admin, Pegboard, and Action Registry remain hidden for normal users and retain their existing server-side authorization checks.
- Archived-project controls are visually marked as Admin-only as well.

### Branding cleanup
- Removed release-number / feature-list copy from the LOOM Home hero tagline.
- Removed release-number / feature-list copy from project app chrome.
- Project app chrome now displays only the project name plus the optional Project Branding tagline. If the Admin tagline is blank, no secondary tagline is rendered.
- Updated `core.ui.branding` so the same project-name/tagline config drives both the browser title and visible app chrome.
- LOOM Admin's header now identifies itself as the Administrator Console without using the current release notes as a slogan.

### New core LOOM Update Log module
- Added reusable `core.system.update-log` at normal module order `00020`.
- Added public read-only `api/changelog.php`, which parses the single root `CHANGELOG.md` into structured release records and semantically sorts versions newest-first.
- The Update Log renders the current LOOM version, detailed release titles, headings, paragraphs, bullet lists, and code blocks inside expandable release cards.
- The newest release is expanded by default; older releases remain available without cluttering project branding.
- Release-card expansion/collapse is registered as the semantic user action `user.update-log.toggle-release`.
- Added reusable Update Log module packaging so new LOOM projects can include the same release-history capability by default.

### Changelog maintenance
- Corrected recent release headings that had been accidentally relabeled by broad version-string replacements: profile avatars are `0.11.06`, supplied Green Beans avatar artwork is `0.11.07`, headwear is `0.11.08`, and dedicated trait previews remain `0.11.09`.
- Continued the single-root-`CHANGELOG.md` policy.

## 0.11.12 — Permanent Admin Authorization Repair + Clean IP Display

- Fixed an authorization split where the User Profile could still show `Privilege: Admin` after permanent-account creation while protected Admin/Pegboard/Registry endpoints rejected the same browser.
- Permanent Admin-account authentication is now the primary authorization path after account promotion.
- The original bootstrap browser retains its secret HttpOnly Admin credential as a recovery path, but that fallback never elevates a different permanent account actively signed into the same browser.
- Added automatic Admin identity reconciliation for older promoted accounts whose bootstrap Admin state did not finish linking to the permanent User ID.
- User Profile now displays effective current privilege rather than blindly displaying the stored account privilege.
- IP addresses are normalized before storage/display. IPv4-mapped IPv6 forms are reduced to normal IPv4, IPv6 is canonicalized, and long IPv6 addresses use a compact human-facing display while the exact full IP remains preserved in data and available as hover/title text.
- Admin user lists and network-history tables use the same compact IP presentation.

## 0.11.13 — Admin Source-of-Truth Repair + Database Integrity Doctor

- Fixed the remaining split where a signed-in permanent account could have `accountPrivilege = Admin` while effective LOOM access still resolved to User because `loom_admin_state` was stale or mismatched.
- A valid authenticated permanent account with durable `loom_users.privilege = Admin` is now authoritative for Admin authorization across LOOM Home, Admin, Pegboard, Registry, and protected APIs.
- Admin-state/client mappings automatically reconcile around the authenticated Admin account instead of allowing a stale denormalized pointer to downgrade it.
- User Profile now shows the effective authorization source and can surface a repair notice if stored/effective privilege ever disagrees.
- Added Admin → Database → **Integrity Doctor**.
- Integrity scans are read-only and inspect required tables, Admin-state references, current client↔user bindings, username ownership, client cache mappings, orphan references, and expired auth sessions.
- Added protected server-side full LOOM-table snapshots using streaming JSONL files under `data/admin/database-backups/`.
- **Safe Repair** requires `CONFIRM`, creates a snapshot first, and only reconciles known-safe identity mappings plus expired login sessions. It never deletes users, project history, events, avatars, IP metadata, bans, or settings.
- Unknown/ambiguous corruption is reported instead of guessed at.

## 0.11.14 — Foreign-Key-Safe Integrity Repair

- Fixed Integrity Doctor Safe Repair failing with MySQL error 1452 when `loom_user_clients` was repaired before the missing parent `loom_users` row.
- Safe Repair now uses strict parent-first ordering: permanent user → auth session / username registry → client link → Admin state → denormalized caches.
- If the signed-in account exists in LOOM temporary account storage but is missing from SQL, the Doctor can restore that exact user row (including its existing password hash) before any child mapping is written.
- If migration already created the exact same username + email account under a different SQL `user_id`, Safe Repair adopts that one existing SQL account instead of creating a duplicate, promotes its Admin privilege when appropriate, migrates the current auth session, and reconciles the temporary safety store.
- If only username or only email conflicts, or the records are otherwise ambiguous, repair stops and reports the conflict instead of guessing.
- Added a `current-auth-user-missing-from-sql` integrity finding so this failure mode is visible before repair.
- Prevented normal Admin reconciliation from attempting a foreign-key child link while the SQL parent account is missing.
- Safe Repair still creates a protected full database snapshot before any mutation.

## 0.11.15 — Branded LOOM Home + Faster Module Bootstrap

- LOOM Home project cards now inherit each project's actual Project Logo and Logo Text module settings.
- Home cards use the effective Admin-overridden logo asset, wordmark text, font family, font weight, and colors.
- Logo scale and Logo Text font size are intentionally ignored on LOOM Home; card branding uses a standardized LOOM Home presentation size so projects remain neat and comparable.
- Google Fonts declared by the Logo Text module are loaded on LOOM Home when applicable.
- Removed startup telemetry requests from the critical module-loading path. Module/action telemetry is now queued in order and written in the background rather than blocking each module on an HTTP round trip.
- Added modulepreload hints for discovered module entry files so the browser can fetch module code concurrently while LOOM still activates modules in deterministic order.
- Reduced the Loader's deliberate minimum visible time from 700ms to 320ms and its post-completion delay from 320ms to 80ms.
- The Loader still reports real sequential module readiness, but its progress no longer advances at network-telemetry speed.

## 0.11.16 — Ambient Background Orbs

- Added reusable LOOM core module `core.ui.background-orbs`.
- Background Orbs renders a non-interactive fixed background field behind project content, so the animation is primarily visible in empty page space rather than covering project UI.
- Orbs float forever on randomized slow zero-gravity paths using the browser Web Animations API.
- Admin controls: orb source, orb volume/count, float speed, glow level, and glow color.
- Added reusable Admin config-preset buttons. Background Orbs exposes `Use LOOM Defaults` and, when a compatible project extension exists, `Use Project Defaults`.
- Project defaults win automatically on first load. Admin changes persist as normal LOOM module overrides.
- Added `core.ui.background-orbs.provider` project extension contract. Projects supply only replacement orb artwork and an optional suggested glow color; movement/settings remain LOOM core behavior.
- Green Beans now supplies little green bean-shaped orb artwork and a green default glow.
- Switching to LOOM Defaults restores native softly glowing glass orbs and LOOM's default blue-violet glow.
- Added `admin_overrides` to runtime module descriptors so core modules can distinguish explicit Admin settings from inherited project defaults.
- Added manifest schema 1.7 support for project-derived Admin field defaults and generic config-preset tools.

## 0.11.18 — Project Identities + Full IP Display + Plugin Authoring Manual

- Split permanent account authentication from visible project identity.
- One LOOM User ID can now own an independent username and profile picture for every project.
- Added durable `loom_project_identities` SQL storage with per-project username uniqueness.
- Existing account/client username is lazily migrated into the current project identity instead of remaining a global public username.
- Existing custom profile picture is safely copied into the first project identity that consumes the legacy avatar, after which future project identities are independent.
- Email/password/User ID/privilege remain global account credentials so one login recovers all project identities.
- User Profile now shows **My Project Identities** across projects.
- LOOM Admin → Users now shows the selected project's identity plus all known identities for that permanent account/client, while email/password controls remain clearly account-level.
- Admin username edits now edit the selected project's username only.
- Updated database migration and Integrity Doctor backups for `loom_project_identities`; the table is also created lazily so existing databases can upgrade without a destructive repair.
- IP selection now prefers a public IPv4 when one is actually provided by the reverse proxy; otherwise LOOM displays the full canonical IPv6 and labels the IP version. No fabricated IPv4 addresses.
- Added permanent `docs/LOOM-PLUGIN-AUTHORING-MANUAL.md`, covering universal LOOM core/global plugins and project plugins/providers.

## 0.11.18 — Global LOOM Profiles + Green Beans Shopping List

- Split visible identity into a global LOOM profile plus optional project-specific overrides.
- Added a LOOM-wide username and LOOM-wide profile picture so a persistent user has a visible identity even outside individual projects.
- Projects can independently inherit the LOOM username or use a project-specific username.
- Projects can independently inherit the LOOM profile picture, use LOOM default, use project default, upload a project picture, or use a project avatar creator when supplied.
- Existing v0.11.17 project usernames remain explicit project overrides during migration, preserving current Green Beans identity.
- Global usernames are unique LOOM-wide; project override usernames are unique only inside that project. The same username may intentionally be used across multiple projects.
- Admin Users now exposes LOOM-wide username plus all project identity sources/overrides.
- Added generic persistent `project-state.php` service and SQL `loom_project_module_state` for portable project feature state.
- Added first standalone Green Beans feature module: Shopping List.
- Shopping List supports rapid Enter/comma-separated entry, automatic input refocus, edit/remove, completion checkboxes, multi-selection, grouping/ungrouping, whole-group completion, empty group creation, rename, delete-to-Ungrouped, and live item counts.
- Expanded the permanent LOOM Plugin Authoring Manual with project-state and global-profile/project-identity rules.
- IP presentation now prefers a genuinely observed public IPv4 from current/recent server observations when one exists; otherwise LOOM shows the full canonical IPv6 and never invents an IPv4.

## 0.11.21 — Clean `/app/` Routing + Module URL Repair

- Fixed project slug detection when a project is opened through the clean directory URL `/projects/<slug>/app/`.
- The previous resolver selected the literal path segment `projects`, causing registry requests such as `modules.php?project=projects`.
- Fixed the resulting telemetry/heartbeat `400 Invalid project` errors.
- Fixed static fallback registry module imports resolving relative to `/engine/`, which produced invalid URLs such as `/LOOM/engine/projects/<slug>/actions/...`.
- Action Runtime now resolves fallback module JavaScript, module-preload URLs, and module styles from the actual LOOM installation root.
- Project Home/Admin links now intentionally use the clean `/app/?project=<slug>` entrypoint.
- Added an explicit Apache project-app directory rewrite in addition to `DirectoryIndex`, improving compatibility with shared-host rewrite configurations.
- The app works consistently through both `/projects/<slug>/app/` and `/projects/<slug>/app/index.html`.
- Added a routing/runtime troubleshooting section to the LOOM plugin authoring manual.

## 0.11.22 — Persistent Module Collapse + Footer Bar + Orb Dock

- Added LOOM-native collapsible/expandable content modules. Collapsed modules reduce to a compact name bar.
- Collapse state persists per user account and project through LOOM project-state storage, with browser-local instant fallback.
- Protected layout ordering now reserves User Profile near the top of project content and Project Update Log as the bottom-most ordinary content module.
- Added reusable `Footer Bar` core module with project logo + wordmark in a vertical stack, configurable sizing/background/width/alignment, and larger LOOM attribution/version/copyright.
- Removed the old static `All project modules ready` footer message.
- Added reusable `Orb Dock` core module. It can capture eligible content modules out of normal project flow and expose them through responsive footer quick-access orbs.
- Project Update Log is the first required/default Orb Dock item and uses the notepad emoji by default.
- Orb icons support arbitrary Unicode emoji; when no emoji is configured LOOM generates a two-letter badge from the module name.
- Added Admin Orb Manager to choose additional content modules to orb and assign their emoji.
- Added Orb Dock controls for orb size, names, name size, section title, carousel/wrap behavior, alignment, spacing, shadow blur/offset/opacity/color.
- Added boolean Admin fields and preserved module-specific tool configuration when saving ordinary project settings.
- Added `footer-bar` and `orb-dock` reusable modules and documented their manifest/presentation contracts.

## 0.11.23 — Footer Row Polish + Unified Collapse Cards

- Rebuilt Footer Bar as three independent stacked rows: Project Branding, More Tools, and LOOM Attribution.
- Project Branding is always the top footer row; More Tools occupies its own middle row; LOOM attribution is always the bottom row.
- Each footer row now has independent Admin controls for width mode, horizontal placement, padding, corner radius, and background.
- More Tools defaults to `fit-content`, and Orb Dock now shrink-wraps the actual number of orbs instead of stretching across empty footer space.
- The More Tools row remains responsive: it expands only as needed and caps at the available width.
- Increased the default LOOM attribution sizing and spacing for a cleaner platform signature.
- Fixed the LOOM module collapse header so it visually merges with the module card below it instead of creating a double-rounded top edge.
- Expanded modules now have one continuous card silhouette; collapsed modules remain compact standalone title bars.

## 0.11.24 — Profile Dock + Project Shell Branding + Unified LOOM Chrome

- Added `core.user.profile-dock`, a reusable LOOM core module that captures User Profile out of ordinary project flow and exposes it as `👤 User Profile` in the built-in project top bar.
- Added an always-visible `LOOM Home` link to the built-in project shell. It is a normal user navigation control, not an Admin-only tool.
- The built-in project shell now shows project branding independently of the optional visible Logo module.
- Project shell mark resolution is: canonical project branding asset first, visible Logo module configuration as compatibility fallback, animated LOOM cube only as the final fallback.
- Added canonical project `branding.logo_asset` metadata so removing the visible Logo module does not erase the project's baked-in shell identity.
- Standardized LOOM-owned page chrome across Home, Admin, Pegboard, and Action Registry with a consistent LOOM header and footer.
- Shared LOOM footer now shows an animated LOOM mark, `Powered by LOOM`, version, and copyright.
- LOOM Home hero copy is now fully generic and contains no hardcoded project-specific product language.
- Reduced and padded the LOOM Home hero cube so its 3D geometry remains comfortably inside the visual container.

## 0.11.25 — LOOM Global Profile + Shared Platform Shell

- Added a reduced LOOM-wide User Profile directly to LOOM Home and all LOOM-owned shell headers.
- The global profile intentionally contains only LOOM-wide identity/account information: global username, global profile picture, permanent account, User ID, privilege, and global profile timestamps.
- Project-specific username/avatar overrides, project identity metadata, and project analytics remain available only when User Profile is opened from inside a project.
- Users can now create a permanent LOOM account directly from LOOM Home without first entering a project.
- Global LOOM Profile continues to support `Pull from Project` for copying a chosen project's effective profile picture into the global profile.
- Added `engine/loom-global-profile.js`, a reusable LOOM-owned global-profile surface.
- Added `engine/loom-shell.js`, the shared mount point for LOOM-owned page chrome.
- LOOM Home, Admin, Pegboard, and Action Registry now use the same shell header/footer component instead of independently rebuilding platform chrome.
- The shared shell exposes `LOOM Profile` to normal users while preserving page-specific Admin authorization.
- Removed hardcoded project-specific Pegboard/Registry links from the LOOM Home Admin banner; those tools remain available per project card and from Admin.

## 0.11.26 — Pegboard Shell Repair + Avatar Pull + Animated LOOM Marks

- Perfectly centered the Orb Dock section title (`More Tools`) over its orb carousel.
- Reworked Pegboard to use the shared LOOM shell as its single top header instead of stacking the shared shell above a second Pegboard header.
- Moved Pegboard controls into the shared shell header while keeping the target panels, graph, and event log inside a bounded workspace.
- Pegboard now uses a real three-row page layout: shared LOOM header, Pegboard workspace, shared LOOM footer. The footer is no longer hidden behind a full-screen fixed viewport.
- Fixed duplicate shared-footer containers in protected Admin/Registry denial pages.
- Fixed `Pull Picture from Project` when the selected project uses a project-provided default avatar larger than the browser-upload dimension envelope.
- Trusted project avatar assets now use a separate safe server-copy path; browser uploads still retain the stricter compression/dimension validation.
- Replaced the project footer's static LOOM image with the procedural animated LOOM cube.
- Expanded the shared LOOM shell logo container and reduced the cube inside it so the animated 3D geometry no longer overflows its box.
- Increased the LOOM Home hero mark container while slightly reducing the animated cube, giving every animation path safe visual breathing room.

## 0.11.27 — LOOM Home Hero Cube Safe-Fit

- Fixed the LOOM Home hero cube overflowing its rounded logo container.
- `LoomBrand.mountCube()` no longer forces every host element to become exactly the internal cube size.
- Added an explicit `sizeHost` option for shell locations that want content-sized cube containers.
- LOOM Home now keeps its own larger visual container while mounting a smaller internal procedural cube.
- Enlarged the Home hero logo box, reduced the cube's internal size, and clipped the box safely so every animation path remains contained.

## 0.11.28 — Shared Header LOOM Mark Safe-Fit

- Fixed the animated LOOM mark overflowing the shared top header bar.
- The shared shell header now keeps its own fixed 62×62 mark container instead of collapsing the host down to the internal cube size.
- Reduced the internal header cube to 20px while preserving the global animation path and speed.
- Added generous internal padding and containment so the cube's 3D perspective footprint stays inside the header.
- Applied the same containment rule to the shared LOOM footer mark for consistency.

## 0.11.29 — Green Beans Emoji Background Orbs

- Green Beans' project-specific Background Orb provider now supplies native transparent `🫛` emoji artwork instead of the previous custom SVG bean shape.
- Extended reusable LOOM Background Orbs so project providers may supply either `orbEmoji` / `getOrbEmoji()` or an image asset.
- Emoji project orbs remain true transparent text glyphs; glow is applied with `drop-shadow()` around visible emoji pixels rather than a rectangular background.
- LOOM default glass orbs remain unchanged when Admin selects LOOM Defaults.
- Other projects remain free to provide their own emoji, image asset, or no provider at all.

## 0.11.30 — Cross-Platform Green Beans Orb Glyph Fix

- Replaced the native `🫛` Background Orb glyph with a bundled transparent emoji-style pea-pod SVG.
- Fixes missing-glyph / tofu rectangles on systems whose installed emoji font does not include the Unicode pea-pod character.
- Green Beans keeps its emoji-style visual treatment without depending on Windows, macOS, browser, or system emoji-font versions.
- LOOM core still supports native `orbEmoji` providers for projects that intentionally want platform-native emoji rendering.
- For production project branding where exact appearance matters, bundled transparent project assets are now the recommended provider strategy.

## 0.11.31 — LOOM Favicons + Module Asset Resolution Repair

- Standardized the static LOOM icon as the favicon for LOOM Home, Admin, Pegboard, and Action Registry, including protected/denied page states.
- Shared `LoomShell` now enforces the static LOOM favicon automatically, reducing the chance that future LOOM-owned interfaces ship without one.
- Project application pages remain project-owned and keep their own project favicon.
- Fixed `ctx.resolveAssetPath(..., 'module')`: module-relative paths now resolve against the discovered module folder and safely pass through the existing explicit project asset resolver.
- Fixed Green Beans Background Orbs silently falling back to LOOM spheres because its bundled pea-pod SVG could not previously resolve from module scope.
- Green Beans orb provider retains a project-root compatibility fallback for older runtimes.

## 0.11.32 — Pegboard Repair + Temporary Visitor Presence

- Repaired Pegboard startup crash caused by JavaScript referencing the removed legacy `#title` element.
- Pegboard now uses the shared LOOM header as its only header; the page-specific toolbar remains embedded in that shared header.
- Rebuilt Pegboard page structure as a real three-row layout: shared header, bounded graph workspace, shared footer.
- Pegboard footer now occupies the physical bottom page row instead of appearing near the top behind/above an absolutely positioned workspace.
- Graph fit calculations now use the actual Pegboard viewport dimensions rather than the full browser window.
- Added viewport resize handling so graph fit remains correct when shell chrome dimensions change.
- Added a real root `/favicon.ico` generated from the static LOOM icon to eliminate browser fallback 404 requests.
- Added lightweight LOOM Home visitor presence. A fresh browser/device is now recorded as a temporary client identity before it creates or signs into a permanent account.
- Temporary visitors receive a client-owned LOOM global profile but no permanent account and no project identity merely from visiting LOOM Home.
- Admin → Users now labels unregistered client identities as `Temporary visitor`.
- Listing/viewing a temporary visitor in Admin no longer creates a project identity as a side effect.
- When a temporary visitor later registers or signs in, existing LOOM promotion/linking logic continues to move/link client-owned identity/state to the permanent user.

## 0.12.00 — Identity Permanence & Recovery

- Established LOOM's identity permanence invariant: unauthenticated Guest data is durable by default; account creation changes ownership/access rather than deciding whether data survives.
- Replaced the conceptual “temporary visitor” lifecycle with durable **Guest Identities** backed by stable Guest IDs, client mappings, first/last seen history, attachment lineage, recovery metadata, snapshots, and identity audit records.
- Added `api/_guest_identities.php`, `api/guest-identity.php`, and Admin-only `api/admin-identities.php`.
- Added support for multiple independent devices accumulating separate Guest Histories and attaching them one-by-one to the same permanent user during later registration/sign-in.
- Guest attachment now snapshots source identity/profile/project/avatar/network information before live uniqueness-constrained identity structures are promoted.
- Project module state attachment is non-destructive: client-owned source rows remain preserved after user-owned merged state is created/updated.
- Added generic conflict-aware project-state merge behavior: associative deep merge, ID-addressable list union/merge, unique-list union, and preserved scalar conflict records with newer value active.
- Added protected Guest snapshot and archived-asset storage under `data/identity/`.
- Added Guest recovery codes. Recovery codes are shown once; only their SHA-256 hashes are retained server-side.
- Recovery on a new device can merge that device's existing Guest History into the recovered Guest Identity without deleting the source history.
- Added fuzzy related-Guest evidence for support investigation. Shared IP/project/username signals can surface candidates, but fuzzy evidence never performs automatic attachment or authentication.
- Added Admin **Identity Manager** with Guest inspection, recovery-code issuance, attach-to-existing-user, create-account-from-Guest, Guest→Guest merge, related-candidate review, and conflict inspection.
- Guest→Guest merges now preserve the source record as `merged` with canonical-target lineage rather than erasing the source Guest ID.
- Added SQL identity tables for Guest identities, Guest/client mappings, attachment provenance, recovery hashes, merge conflicts, and identity audit events.
- Home visitor registration now establishes the durable Guest Identity before permanent account conversion.
- Added legacy-client backfill so pre-v0.12 client profiles, project state, global/project identities, network records, and SQL client records can be represented as Guest Identities instead of remaining outside the new identity graph.
- Global Profile exposes Guest recovery/protection controls for unauthenticated users while permanent users can see attached Guest History counts.
- Admin → Users now identifies unauthenticated subjects as **Guest Identity**.
- Renamed non-SQL state reporting from `temporary-local` to `durable-local` to reflect the actual persistence contract.
- Added `docs/IDENTITY-PERMANENCE-RECOVERY-STANDARD.md` and updated visitor/account/plugin documentation for attachment, recovery, merge, evidence, and retention rules.
- v0.12.00 introduces no automatic Guest deletion policy or age-based purge.
