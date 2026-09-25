# LOOM Blueprint — 0.15.51

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
- an exact observed network connection shared with another guest client.

These signals are intentionally used to **remove anonymous privilege**, never to grant privilege. The UI reports only human-readable categories and never exposes raw fingerprints or IP addresses.

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

<!-- @loom-file release=0.15.51 revision=65 policy=package-priority -->
