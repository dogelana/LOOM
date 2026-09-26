# LOOM 0.15.72 — Permanent Account Identity Collapse

- Permanent-account promotion no longer surfaces the pre-created future Guest generation as a second visible person. Future Guest generations are reserved as `standby` until an explicit sign-out or Guest selection activates them.
- Admin Users and portable-person export hide standby Guest shells while preserving the claimed Guest History, aliases, browser lineage, avatars, project participation, project state, and activity under the permanent account.
- Existing 0.15.68–0.15.71 split records are repaired idempotently when their empty post-claim generation has never accumulated project payload.
- Explicit sign-out / Switch User activates the reserved Guest generation on demand, preserving the intended fresh-Guest workflow without duplicating the person at promotion time.

# LOOM 0.15.71 — Loader, Admin UX & Navigation Reliability

- Project loaders no longer render an empty secondary flying pill when the project wordmark has only one meaningful line.
- LOOM Home project mutations carry the active client identity through authorization, and `project-manager.php` reads JSON identity context before access checks, eliminating false 403s for authorized System Owner/Admin project identity saves.
- Active tabs use high-contrast light selected surfaces with dark text across Home, Admin, and Backup & Restore.
- Native file pickers receive shared LOOM production styling instead of browser-default chrome.
- Backup & Restore removes redundant Home navigation and gives its Admin control an explicit settings icon.
- The permanent animated LOOM brand at the top-left of the global shell is now a keyboard-accessible Home link using the same canonical Home URL resolver as the normal Home control.

# LOOM 0.15.70 — Clean Core Baseline

- Removed the final bundled concrete-project migration payload from LOOM core. Release archives now contain engine code, generic project infrastructure, templates, and upgrade tombstones only.
- Genericized leaderboard defaults and project-branding examples so core APIs/docs no longer imply a specific product project.
- Removed installation-specific usernames and project names from permanent product documentation/comments.
- Preserved only deployment tombstones required to remove obsolete release-owned paths during safe in-place upgrades; tombstones never target `instance/**`.
- Rebuilt the deployment manifest from the sanitized tree and re-audited the release for user/project-specific identifiers and assets.

# LOOM 0.15.70 — Canonical Release Watch + Production UI

- Release Watch derives the running client release from canonical boot evidence and only announces a strictly newer semantic release.
- Same-version fingerprint churn no longer creates false update banners or reload loops.
- LOOM-owned Home, Admin, Backup & Restore, Activity, Referrals, Setup, Registry, project-shell, Header, Showcase, and Footer surfaces share a production-grade UI system while project-owned branding remains independent.

# LOOM 0.15.68 — Portable Guests + System Owner migration bootstrap

- Standalone Guests became first-class portable people alongside permanent accounts.
- Blank-install bootstrap can restore a verified prior System Owner portable bundle while issuing fresh destination Administrator credentials.
- Project + Data exports remain project-owned vertical slices; person bundles remain identity-owned horizontal slices across projects.

# LOOM 0.15.67 — Runtime liveness and identity recursion recovery

- Removed cyclic global-profile/project-identity resolution that could exhaust PHP workers.
- Added recursion fuses, deployment-marker cleanup, bounded startup retries, and non-blocking deployment-status UX.

# LOOM 0.15.66 — Home and identity runtime recovery

- Restored Home bootstrap helpers and hardened visible-name resolution so internal allocation handles cannot become presentation names.
- Hardened storage access in sandboxed frames and made Admin Users avatar resolution scope-relative.

# LOOM 0.15.65 — Dynamic project identity inheritance

- Project identities explicitly distinguish live global inheritance from intentional project overrides.
- Project-specific presentation can no longer contaminate the global identity cache.

# LOOM 0.15.64 — Authoritative module positioning

- Project module position indexes became authoritative at runtime with deterministic collision handling and soft default composition positions.

# LOOM 0.15.63 — Visible positioning controls + canonical Admin identity

- Project Settings exposed module position indexes directly and Admin chrome was aligned with the canonical visible identity.

# LOOM 0.15.62 — Identity/media hygiene + project discovery

- Factory identity wipe gained exhaustive media cleanup and orphan-media reconciliation.
- Built-in avatar presets were normalized, project search/pagination was added, deep-linked project user scopes were fixed, and human-facing timestamps became viewer-local.

# LOOM 0.15.61 — Portable Users + scoped Backup & Restore

- Backup & Restore was split into Global LOOM, Projects, and Users.
- Single-user portable bundles capture the canonical identity ownership graph and restore create/merge-only without stealing unrelated identities.

# Earlier core history

Earlier 0.15.x and 0.12.x work established clean-instance releases, Instance Projects, project import/export, identity continuity, permissions, Admin tooling, HTML Framer, module discovery/composition, deployment gating, backup/restore, project branding, and persistence adapters. Concrete product-project development history is intentionally not retained in the clean core changelog. Deployment tombstones in `.loom-deployment.json` remain the sole source of obsolete release-path cleanup semantics required for upgrades.
