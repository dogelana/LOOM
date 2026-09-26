
## Canonical Users directory (0.15.57)

Admin → Users is one global people directory. Choose **Global LOOM** or a project scope; the same permanent accounts and Guest Identities are shown in either view, with project participation resolved from canonical identity plus proven linked-client history. Clicking a person opens one inspector for identity, avatar, network/geolocation context, continuity/referrals, project activity, access roles, and global/project moderation. Global LOOM bans and per-project bans are independent and do not delete data. Raw orphan browser clients are cleanup evidence, not first-class people.

# LOOM 0.15.57

LOOM is a modular browser application engine for independently owned Instance Projects. The engine supplies project discovery, project lifecycle management, account and guest identity, Admin tooling, Action Registry/Pegboard observability, reusable/core modules, HTML Framer, referrals, system email, and optional durable SQL persistence.

## 0.15.57 — project-specific avatar visibility in Users

Project-focused user administration now renders the avatar that LOOM actually resolves **inside that project**, instead of showing only the person’s global avatar. When Admin → Users is focused on a project, directory cards use the project-scoped avatar endpoint. The per-project identity inspector now displays the project avatar prominently beside the project username and reports the project avatar mode/source.

Canonical global identity remains separate: the main person header still represents the LOOM-wide identity, while each project inspector shows that project identity’s effective avatar. Protected project custom images, project defaults, inherited global avatars and the LOOM fallback are all resolved through the same safe avatar endpoint. Proven linked-client fallback remains available for incomplete legacy project-avatar migrations without exposing protected Instance storage paths.

## 0.15.56 — canonical Users hardening + avatar migration safety

LOOM Admin now has exactly one first-class Users directory. It opens on **Global LOOM · All users** and can be narrowed to one project without switching identity systems. Permanent accounts and unattached Guest Identities use the same canonical inspector, count, project participation model, global/project moderation controls, continuity/referral context, and account management surface.

Avatar delivery now accepts canonical permanent-user and Guest Identity targets, resolves protected global/project custom images, preserves safe reads from incomplete legacy client-avatar migrations, serves presets, and falls back to the LOOM default image instead of emitting repeated 404s for valid identities with no custom avatar. Admin avatar URLs use stable identity/version keys instead of `Date.now()` cache busting.

The obsolete hidden Project Users DOM/event path and superseded identity-list/detail handlers were removed. Users controls now stack/wrap responsively, user cards cannot overflow their column, and permanent accounts no longer depend on legacy client target shapes for click-through detail. Runtime/cache release references are synchronized to 0.15.56 while historical per-file release markers remain valid provenance for untouched files.

## 0.15.55 — orphan identity sweep + canonical Project Users

LOOM now treats pre-Guest-era raw browser/client records as explicit **legacy orphan clients** instead of pretending they are current Guest Identities. The Project Users list labels them clearly, and the System Owner cleanup suite reports how many exist.

Bulk **All Guest Identities** cleanup now also sweeps orphan unauthenticated client records that have no canonical Guest wrapper and no permanent-account owner. A dedicated **Legacy orphan client records only** target is available when you want to clean just those remnants. Cleanup removes the raw durable profile file plus selected project identity/state, network/continuity, referral/activity/media data and matching SQL rows while protecting clients still linked to surviving permanent accounts and protecting the System Owner outside the explicit factory wipe.

This closes the old gap where deleting every Guest Identity could leave `Visitor XXXXX` / old client-profile cards behind in project moderation because those rows came from a separate legacy client-profile store.

## 0.15.53 — unified Users, strict household-network ambiguity + readable network context

LOOM now treats **Users** as one global people/identity workspace. Permanent accounts and Guest Identities appear in the same directory; guest/permanent is a status rather than a reason to split administration across separate top-level tabs. Permanent deletion, Guest provenance, project moderation and recovery tools remain available as sections of the same Users surface. Legacy `?tab=identities` links continue to open Users.

Guest naming is also cleanly separated from storage identity. A human-facing `displayName` no longer has to be globally unique, while LOOM can retain an invisible unique internal handle for indexing. New Guests therefore do not acquire presentation names such as `MichaelYebba 2` merely because another internal profile already used `MichaelYebba`. Existing legacy auto-suffixes are normalized for presentation when LOOM can identify them as old collision artifacts.

Anonymous access is stricter around shared environments. LOOM records the current request network before evaluating Guest policy, treats an exact shared public IPv4 address as overlap, and treats public IPv6 addresses within the same `/64` household network as overlap even when individual devices use rotating privacy addresses. Existing continuity/network history is backfilled so older Guest roots that already share supported evidence can receive the same durable **Permanent account required** protection. Overlap removes anonymous privilege; it never authenticates, identifies or merges people.

Admin → Users now includes optional **Network Context · Display Only** enrichment with readable city/region/country, ISP, ASN, network-family and network-prefix information. Approximate geolocation is cached and can be disabled/cleared. **City, region, country, ISP and geolocation are never identity-overlap triggers** because they are too broad; two unrelated people in the same city must not be forced together.

## 0.15.53 — Showcase balance + global UX polish

Generated Showcase badges now place the floating **Powered by LOOM** plate at a 10% bottom inset by default. Shared LOOM chrome, controls, focus states, mobile navigation, Admin inputs, buttons, and interaction feedback also receive a restrained consistency pass. This is presentation-only: routes, identity, permissions, module behavior, project data, and deployment semantics are unchanged.

## 0.15.51 — release coherence, clean Guest names and identity cleanup

LOOM now keeps its client runtime/cache identity synchronized with the canonical deployed release, so a completed deployment no longer triggers a stale-version manual-refresh loop. Guest presentation names are also independent from globally unique internal handles, eliminating visible suffixes such as `Name 2` when the human-facing name is simply `Name`.

System Owners receive an advanced **Identity Cleanup & Data Purge** panel in Admin → Users. Every destructive operation starts with a preview and requires an exact confirmation phrase. You can delete one permanent user, one Guest Identity, all non-owner permanent users, all Guest Identities, all non-owner identities, or intentionally perform a separately protected full identity/owner wipe. Storage can be scoped to local Instance state, SQL persistence, or both, with independent switches for project data, activity, referrals, continuity, media, audit history, and attached Guest Histories.

Permanent-account deletion is conservative by default: attached Guest Histories survive and are detached unless **Delete attached guests too** is explicitly selected. The System Owner is protected from ordinary account deletion and bulk cleanup.

## 0.15.50 — friendly project routes, Instance asset proxy and Showcase polish

Projects now use short public routes such as `/my-project/`, new project slugs auto-iterate around duplicates/reserved LOOM paths, Instance Project module assets are proxied safely out of the protected vault, and generated Showcase badges use a floating Powered by LOOM plaque.

## 0.15.49 — strict guest ambiguity + generic starter branding

This release removes the last product-specific starter branding assumptions from LOOM core and strengthens anonymous identity safety.

### Generic project branding

- A one-word project name remains one word. `Dogelana`, for example, renders as `DOGELANA` with no invented second word.
- Header wordmark defaults are now generic: `PROJECT` plus an empty second line.
- Wordmark colors use the generic `primaryColor` and `accentColor` contract.
- Core/header/logo CSS uses LOOM-owned class names rather than product-derived names.
- The generic baseline template contains no concrete product wording, colors, project slug, mascot, or filler copy.
- Concrete products belong in Instance Projects imported or created after LOOM is installed.

Release retirement metadata may still name paths retired by old versions. Those entries are tombstones only: they exist so Deployer can delete stale release-owned files from upgraded installations and are never starter/default content.

### Strict guest ambiguity policy

Anonymous guest use is allowed only while LOOM has no evidence that the environment overlaps another guest identity. Once any supported overlap signal appears, LOOM treats the situation as ambiguous rather than guessing which person is present.

Affected guest identities are durably marked **Permanent account required**. On their next use they must either:

1. sign into an existing permanent account, which claims the current guest payload when eligible; or
2. create a permanent account in-place, preserving the current guest profile and project data.

The gate can explain the non-sensitive reason for ambiguity, such as browser installation, device characteristics, device/display profile, or network connection. It never displays raw fingerprints or IP addresses.

An overlap is not authentication and is never used to grant account authority. If several guest histories are actually one person, signing the same permanent account into each affected guest context safely consolidates those histories through the existing claim/promotion pipeline.

### Backward compatibility

Existing guests, permanent users, project identities, avatars, referrals, email settings, project data, and Instance Projects remain installation-owned and are not shipped in the release ZIP. Old guests are not destructively merged during upgrade. The strict gate takes effect as those identities are seen again and as overlap evidence is observed.

## System email and password recovery

LOOM includes provider-neutral system email configuration under **Admin → Email**. SMTP and PHP `mail()` transports are supported, with setup diagnostics, connection testing, test delivery, global/category switches, and protected credentials under `instance/`.

Starter notifications include welcome, password reset, password changed, email changed, referral attributed, and optional new-sign-in email. Password reset uses a one-time random token, a short expiry, request throttling, non-enumerating responses, and session revocation after password replacement.

## Instance boundary

Production release ZIPs contain release-owned application code only. They intentionally contain no `instance/**` payload. Runtime users, guest records, continuity policy state, email credentials, uploads, projects, sessions, database configuration, logs, and other installation state remain server-owned.

The generic project template lives at `templates/projects/baseline`. Runtime projects belong under the Instance Project protocol. Core surfaces must never assume a particular product project when project context is absent.

## Deployment

LOOM uses `.loom-deployment.json` as the canonical release contract. Package-priority files are staged and verified before the manifest is committed last. Server-priority/server-only state is preserved. Explicit retired paths are cleanup instructions for obsolete release-owned material only.

Use LOOM Bridge Suite for transactional FTP/SFTP deployment and optional Git/GitHub checkpointing. A successful Git checkpoint is independent of application authentication and never includes `instance/**`.

## Development rules

- Keep LOOM core product-agnostic.
- Prefer reusable/core modules plus project extension contracts over product forks.
- Never store permanent identity or credentials in release-managed paths.
- Recognition/continuity signals may protect anonymous guest mode; only authentication grants account authority.
- Project export/import must preserve project-owned structure and portable HTML Framer packages.
- Admin/developer surfaces must remain server-authorized, not merely hidden in the client.

See `BLUEPRINT.md`, `docs/`, and `CHANGELOG.md` for architecture, standards, and release history.

<!-- @loom-file release=0.15.56 revision=74 policy=package-priority -->
