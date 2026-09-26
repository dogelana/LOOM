# LOOM Blueprint — 0.15.72

## Permanent-account identity-collapse invariants

- Promoting a Guest to a permanent account must collapse the visible person atomically: the claimed Guest History remains provenance beneath the permanent user and must not remain a second Admin-directory person.
- A fresh post-claim Guest generation is a `standby` payload until the user explicitly signs out or selects Guest mode. Standby payloads are lineage infrastructure, not people, and are excluded from Admin Users and standalone Guest export.
- Account promotion must preserve global/project presentation, avatars, project identities/state, browser/client linkage, activity/history, moderation/access, and System Owner binding on the permanent user.
- Legacy empty post-claim generations may be repaired to standby only when they have not accumulated client-owned project identity/state. Real Guest activity always wins over automatic cleanup.
- Activating Guest mode transitions the standby generation to `active` exactly once and records activation provenance; subsequent reads must not demote it again.

# LOOM Blueprint — 0.15.71

## Shell/navigation and project-mutation invariants

- A loader orbit token exists only when its corresponding project wordmark line is non-empty; decorative empty pills are invalid UI.
- LOOM Home project mutations must transmit the active client identity before authorization so System Owner/Admin capability resolution is identical for JSON mutations and query-based requests.
- Selected tab state must remain legible under the shared production theme; active state may not depend on white text over variable accent colors.
- Native file inputs must inherit the shared LOOM control language rather than exposing an unstyled browser-default selector.
- The permanent animated LOOM brand in the global shell is a canonical Home affordance and must resolve the same dynamic Home URL as the normal Home navigation control.

# LOOM Blueprint — 0.15.70

## Clean-core baseline invariants

- A LOOM release contains engine/runtime code, generic templates, reusable modules, documentation, and explicit upgrade tombstones only. It contains no concrete product project, project-specific application payload, user/guest record, installation avatar, account state, or live `instance/**` data.
- Concrete applications are created/imported as Instance Projects and remain installation-owned. Core APIs may provide generic mechanisms such as project state, leaderboards, telemetry, import/export, and migrations, but may not ship a named product's implementation as a default.
- Product documentation and source comments use neutral examples. Personal usernames, organization domains, project brands, client IDs, Guest IDs, and installation-specific identifiers do not belong in the canonical release tree.
- Historical deployment tombstones may retain exact obsolete paths solely when required to delete old release-owned artifacts during an in-place upgrade. Tombstones are metadata, never application content, and must never target `instance/**` or `.git/**`.
- `.loom-deployment.json` is rebuilt from the exact sanitized release tree and remains the canonical release authority.

# LOOM Blueprint — 0.15.68

## 0.15.68 Portable-person and owner-migration invariants

- A canonical person may be either a permanent User or a standalone Guest. Both are first-class portable identity aggregates.
- A Guest already attached to a permanent User must travel inside that permanent User bundle and must not be exported independently.
- Standalone Guest portability follows the same ownership graph used by cleanup/user portability: Guest History aliases, clients, Guest Profiles/generations/installations, presentation, project identities/state, media, grants, moderation, continuity/referrals, network/history metadata, streams, and attributable database rows.
- Stable Guest/client/profile IDs are preserved on a clean restore. Ordinary import is create/merge-only and must never steal an identity key from a different person.
- `wasSystemOwner` is provenance, not authority. Ordinary portable import never transfers System Owner.
- Only a truly blank installation may bootstrap from **Import Previous System Owner**, and only from a verified User/Guest bundle marked as the source System Owner. The destination always creates a fresh Admin credential.
- Project + Data and person bundles intentionally overlap only at attributable project state: Project + Data is the complete project vertical slice; a portable User/Guest is the complete person horizontal slice across projects. Neither substitutes for the other in a clean migration.
- Preferred clean rebuild order is: verify person/project exports → blank release → restore System Owner → restore remaining people → restore Project + Data bundles → integrity verification.
- Release ZIPs remain clean-instance artifacts; no live `instance/**` tree may be shipped to accomplish portability.

# LOOM Blueprint — 0.15.67

## Runtime-liveness and identity-resolution invariants

- Identity conflict validation must be non-mutating. A global-profile write may inspect project identity rows, but that inspection must never call back into global-profile ensure for the same owner.
- Every cross-subsystem identity repair path must have a recursion fuse. Cyclic resolver mistakes fail safely to stored state instead of consuming PHP workers.
- `.loom-deploying.json` is leased transient state. Current v2 markers without a valid positive lease are malformed and self-clear; legacy unleased markers receive only a short compatibility grace period.
- Deployment-status probing is advisory UX and must never block ordinary browser bootstrap. Server endpoints remain the authority for enforcing an active deployment gate.
- Startup network timeouts are bounded failure detectors, not permanent UI states. Core Home discovery may retry conservatively after a transient failure without generating a retry storm.

# LOOM Blueprint — 0.15.62

## 0.15.62 identity-media, avatar, discovery, and time invariants

- `loom-default-preset-01` … `loom-default-preset-10` are the only built-in selectable LOOM avatar modes. The generic `loom-default` SVG is an emergency renderer fallback and must never be persisted by current UI/API writes. Legacy generic/preset values normalize forward without changing a legitimate preset choice.
- Identity-media cleanup cannot depend solely on discovering currently surviving owner IDs. Factory identity reset owns and resets the whole identity-media namespace; ordinary cleanup uses conservative live-reference reconciliation. No new identity feature should create avatar/media storage outside the canonical avatar / Guest-media roots without registering it with cleanup and user portability.
- Project discovery presentation is deterministic: search changes only filtering, each result page contains at most ten projects, and unfiltered ordering remains the canonical project ordering. Pagination is UI state inside LOOM Home, never a separate server route.
- Project-scoped Admin deep links are authoritative initial UI state. `tab=users&project=<slug>` must select that project in both the global project context and Users scope when it exists.
- Human-visible LOOM timestamps are formatted in the current viewer's browser timezone. Relative time is paired with an absolute viewer-local value; server timestamps without an explicit timezone are interpreted as UTC rather than browser-local input. Stored timestamps remain canonical server values.

# LOOM Blueprint — 0.15.61

## 0.15.61 User-portability invariants

- A permanent user is one portable identity aggregate: permanent account + proven linked clients + attached Guest Histories/Profiles + global/project presentation + project state + attributable history, access, moderation, continuity/referrals, media, and database rows.
- The canonical identity/cleanup relationship graph is the ownership authority for portability. Export must not invent a parallel notion of who owns a browser, Guest History, or project record.
- Exact clean-install restore preserves stable identity IDs and the password hash. Existing-ID/email destinations merge only after preview; an identity already owned by a different person is a hard conflict.
- User portability never transfers active sessions, reset/recovery secrets, infrastructure credentials, or the installation-global System Owner binding.
- User import is non-destructive to other users and projects. Project-linked data may be restored dormant by project slug when project code is absent; importing a person never silently imports project code.
- Backup & Restore presents Global LOOM, Projects, and Users as separate scopes so operators cannot confuse installation replacement with project or person portability.


## 0.15.60 Default-avatar and module-order invariants

- The ten designed `preset-01` … `preset-10` avatars are normal LOOM defaults. The old generic avatar is an emergency renderer fallback, not a selectable identity state. New Guest Profiles receive a random preset; legacy generic/missing custom states repair safely on read/write.
- A current human-facing display name is presentation authority. Internal unique handles and historical browser labels remain lineage/storage identifiers and must not silently override the current display name.
- Project-specific module positioning is project configuration, not a mutation of reusable/core manifests. Explicit positive indexes occupy requested slots; collisions advance monotonically to the next free slot; unindexed modules fill remaining slots using the preexisting stable manifest/action order. The bootstrap loader is never displaced.
- Package-owned migrations into Instance Projects must be narrow, hash-gated, backed up, idempotent, and must never justify shipping `instance/**` inside a clean LOOM release.


## 0.15.59 Project-specific telemetry + component-aware imports

- Application-specific UI modules belong to the owning Instance Project under `instance/projects/<slug>/project/actions/**`; they do not become LOOM core modules merely because they use reusable platform APIs.
- Project gameplay/business metrics should be emitted explicitly by the application and persisted against canonical LOOM user/Guest ownership. Generic frame-open duration is not a substitute for application-active time when the framed app has its own paused/title states.
- Portable import preview must inspect the actual ZIP payload and identify whether each project contains structure, project data, or both. Import applies only the operator-selected layers.
- Replacing an existing project snapshots it first. Structure replacement may remove old incoming-owned structure; data replacement removes only incoming data paths and must not erase unrelated project storage. Project-state Merge preserves unrelated state keys.

## 0.15.58 System Owner and identity invariants

- The System Owner is an installation authority anchored by the durable owner pointer. It may be backed by a permanent `user` or, on older/bootstrap installs, by the protected owner browser client.
- A browser-backed System Owner must always have a visible canonical person in Users. If its Guest wrapper was removed by an older cleanup path, LOOM may recreate that Guest only around the exact durable owner client.
- A permanent owner account may be reconciled only from a unique, server-resolvable, proven account relationship to the owner client. Display-name similarity is never ownership proof.
- Ordinary identity cleanup must not delete the System Owner permanent account, owner Guest wrapper, or owner browser lineage. Only the explicit factory identity wipe may intentionally remove root ownership.
- Global display-name edits must mutate the canonical identity presentation layer consumed by the UI. Browser/client labels remain lineage history and do not override the current canonical name.
- Project/Admin role shown while inspecting a person must be derived from the target identity, not from the viewer's current authorization session.
- Historical browser aliases may be displayed for provenance when their relationship is proven, but they are never promoted into separate current people by name alone.

## 1. Purpose

LOOM is a product-agnostic application engine. The engine owns mechanisms: project lifecycle, module/action discovery, presentation composition, identity, permissions, Admin tooling, telemetry, referrals, email, backup/import/export, HTML Framer, and persistence adapters. Concrete applications are Instance Projects and must not be embedded as engine defaults.

## 2. Release vs Instance ownership

Release-managed code is replaceable and described by `.loom-deployment.json`. Persistent installation state lives under `instance/` and is never shipped in release archives.

Examples of installation-owned state include permanent accounts, guest identities, continuity decisions/locks, sessions, project uploads, project data, email credentials, reset tokens, database settings, logs, and imported Instance Projects.

Historical `removed_files` and `retired_directories` entries in the deployment manifest are tombstones. They exist only to remove obsolete release-owned paths during upgrades. A tombstone must never target `instance/**` or `.git/**`.

## 3. Project architecture

Fresh LOOM installations contain the engine and the generic baseline template, not a concrete product project. Projects are discovered or imported explicitly. Developer surfaces require explicit project context and may not silently substitute a named project.

The baseline project branding contract is generic:

- wordmark line 1: project-derived or `PROJECT` fallback;
- wordmark line 2: empty unless the project actually supplies a second line;
- colors: `primaryColor` and `accentColor`;
- logo: project asset when supplied, otherwise LOOM default;
- presentation class names: LOOM-owned names only.

## 4. Accounts, guests, and continuity

Canonical people are permanent users or durable Guest Identities. A raw browser/client record is implementation provenance, not automatically a person. Legacy pre-Guest client records without either a permanent-account link or Guest wrapper are classified as **orphan clients** and must never be presented as canonical Guest Identities. Bulk Guest cleanup includes these orphan remnants so an identity wipe cannot leave phantom Project Users behind.

A permanent LOOM account is the durable authentication owner. Project-visible username/avatar may remain project-scoped while authentication ownership stays global.

Guest identity has two distinct naming layers: a globally unique internal handle may be used for storage/indexing, while `displayName` is the human-facing presentation value. Internal collision suffixes must never be surfaced as presentation unless a human explicitly chose them. Legacy continuity aliases may therefore normalize presentation while preserving immutable internal IDs/handles.

Identity continuity may recognize or associate anonymous contexts, but it is not authentication. Permanent authentication remains the only source of account authority.

### Identity cleanup and permanent deletion

Destructive identity administration is restricted to the immutable System Owner and follows preview → exact confirmation → transaction. Supported targets are one permanent account, one Guest Identity, all non-owner permanent accounts, all Guest Identities, all non-owner identities, and a separately protected factory-level owner wipe. Cleanup can be scoped to local Instance storage, SQL persistence, or both, with explicit data classes for project data, activity, referrals, continuity, media, and audit history.

Deleting a permanent account must preserve and detach its associated Guest Histories unless the operator explicitly enables attached-guest deletion. The System Owner is excluded from ordinary single and bulk account deletion. A factory-level owner wipe requires a stronger confirmation phrase and, when SQL persistence is connected, both storage layers so authority cannot survive in a second backend.

Guest identities are convenient anonymous payloads, not security principals. A browser/client ID can identify a known guest context, but device/network similarity never proves a human identity.

### Strict ambiguity rule

Guest mode is allowed only while the current environment is uniquely attributable enough that LOOM has no supported overlap with another guest identity. The first observed overlap creates a durable ambiguity lock for all guest identities involved.

Supported ambiguity evidence may include:

- the same browser installation containing another guest identity;
- matching coarse device characteristics;
- matching coarse device/display profile;
- an exact shared public IPv4 address;
- a public IPv6 address within the same `/64` household network prefix as another guest client.

These signals are intentionally used to **remove anonymous privilege**, never to grant privilege. Current request network evidence is recorded before policy evaluation, and historical continuity/network observations may be backfilled so an already-ambiguous Guest root does not stay soft merely because the evidence predates the strict gate. The UI reports human-readable categories rather than raw fingerprints.

Approximate IP geolocation (city/region/country, ISP, ASN and similar enrichment) is **display-only Admin context**. It must never be used as an overlap, merge or authentication signal: geographic labels and provider networks are too coarse to distinguish people safely.

Once locked, the guest cannot resume anonymous application use. The identity entry surface must offer only:

- **Sign in** to an existing permanent account, claiming the current eligible guest payload; or
- **Create permanent account**, preserving/claiming the current guest payload.

If several ambiguous guest histories belong to one human, that human can authenticate the same permanent account from each old context. Existing guest-claim/provenance machinery consolidates the data without pretending the overlap itself proved identity.

Locks are durable. Changing network/browser characteristics later does not silently restore anonymous access.

## 5. Continuity vs authentication

Continuity is evidence, not authority. LOOM may retain continuity observations and audit decisions, but account permissions, private data, Admin rights, password changes, purchases, and other sensitive actions require authenticated account authority.

Version 0.15.49 disables cross-browser anonymous auto-resume when ambiguity exists. The safety principle is: **when LOOM is unsure which guest is present, stop guessing and require a permanent identity.**

## 6. Referrals

Referral codes are reusable and represent attribution, not a consumable login token. Each visit is recorded independently. A self-open can be recorded/excluded from credit without disabling the referral for later people.

Referral attribution can begin anonymously, then become durable when an eligible guest payload is claimed by a permanent account. Referral identity must not be inferred solely from device/network overlap.

## 7. System email

System email is provider-neutral. Admin configuration supports SMTP or PHP `mail()`, connection diagnostics, test email, sender/reply-to configuration, public-base-URL configuration, and global/category switches.

Starter event types include welcome, password reset, password changed, email changed, referral attributed, and optional new-sign-in notifications. SMTP secrets and email runtime state belong under `instance/`.

Password recovery uses one-time hashed tokens, expiry, throttling, non-enumerating request responses, explicit public URL generation, and authentication-session revocation after reset.

## 8. Presentation and modules

Modules declare Action identity, lifecycle, dependencies, presentation role/mount slot, configuration, and Admin settings. Layout/container modules expose named regions/slots; child modules mount through those contracts rather than hard-coded product structure.

Header branding is split into project logo and wordmark modules. The wordmark renderer must preserve an intentionally empty second line and must never invent filler wording.

HTML Framer distinguishes normal document content from viewport-style apps/games. Viewport apps receive a stable outer slot rather than continuous content-height feedback. Frame interaction locking remains an explicit preference and must not grant/alter authentication state.

## 9. Admin and developer surfaces

**Users is the single global people/identity administration surface.** Permanent accounts and Guest Identities share one directory; permanent/guest is a status, not a separate top-level Admin product. Guest provenance/recovery, permanent-user inspection, project moderation, network context, and identity cleanup are sections of the same Users workspace. Legacy Identity Manager URLs may map into Users for backward compatibility.

Admin authentication is global and server-enforced. Admin, Pegboard, Action Registry, activity, backups, referrals, email, database tooling, and related endpoints must reject unauthorized direct requests, not simply hide navigation.

## 10. Portability

Project export/import carries project-owned structure, metadata, assets, and HTML Framer package structure. Project + Data adds appropriate project-owned runtime state. Full backups preserve installation concerns according to their own scope. Engine releases never overwrite persistent Instance state.

## 11. Deployment safety

A LOOM release advances the server canonical version only after release-managed files verify and `.loom-deployment.json` is committed last. A deployment gate may pause browser/API traffic during application and final verification. Git/GitHub checkpointing is secondary to website deployment health and must not rewrite protected runtime state.

## 12. Non-negotiable invariants

1. No concrete product project is a LOOM core default.
2. No release archive ships `instance/**`.
3. No ambiguity signal authenticates a person.
4. Once guest ambiguity is established, anonymous access for affected contexts remains disabled until permanent authentication is used.
5. Guest/account merges retain provenance and must be reversible/auditable rather than destructive guesses.
6. A one-word project remains one word unless the project explicitly configures otherwise.

## 12. Native UI presentation defaults

Shared LOOM chrome and controls should favor consistent touch targets, visible keyboard focus, restrained elevation, responsive action rows, and stable spacing without changing feature semantics. Generated Showcase fallback art keeps its Powered by LOOM plate as a floating overlay with a default 10% bottom inset. Product/project-specific styling remains project-owned.

<!-- @loom-file release=0.15.59 revision=73 policy=package-priority -->
