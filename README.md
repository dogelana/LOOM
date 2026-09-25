# LOOM 0.15.50

LOOM is a modular browser application engine for independently owned Instance Projects. The engine supplies project discovery, project lifecycle management, account and guest identity, Admin tooling, Action Registry/Pegboard observability, reusable/core modules, HTML Framer, referrals, system email, and optional durable SQL persistence.

## 0.15.50 — friendly project routes, Instance asset proxy and Showcase polish

Projects now use short public routes such as `/green-beans/`, new project slugs auto-iterate around duplicates/reserved LOOM paths, Instance Project module assets are proxied safely out of the protected vault, and generated Showcase badges use a floating Powered by LOOM plaque.

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

<!-- @loom-file release=0.15.49 revision=67 policy=package-priority -->
