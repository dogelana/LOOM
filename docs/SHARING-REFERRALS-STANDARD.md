# LOOM Sharing & Referral Standard

LOOM sharing is a core, identity-aware capability. A Share action creates a canonical destination URL with one opaque `loom_ref` code. The code identifies a durable server-side referral record; it never contains an email address, password, account identifier, or project permission.

## Lifecycle

1. The referrer selects **Share** from a project, LOOM Home project card, or LOOM-owned page.
2. LOOM resolves a canonical same-installation destination and creates/reuses an attributed share record.
3. The recipient opens `?loom_ref=<opaque-code>`. The browser captures the code into session state and immediately removes it from the visible URL with `history.replaceState()`.
4. Normal LOOM identity onboarding continues. The destination does not change.
5. After a durable guest or permanent-account-backed identity exists, LOOM records one referral attribution. Self-referrals and duplicate attribution are rejected.
6. Later account registration does not erase guest provenance: referral statistics aggregate guest identities that became attached to the permanent account.

## Persistence

Referral state belongs to the Instance Vault under `instance/referrals/` and is excluded from release replacement. Mutations use an exclusive file lock to prevent lost counts during concurrent clicks/acceptance. Administrative events are also written through LOOM Audit.

## Action Registry

The project-scoped core controller `loom.share.referrals` declares `user.share.open`, `user.share.copy`, `user.share.native`, and `user.referral.accept`. This keeps project sharing inspectable by Action Registry/Pegboard while Home/core-page sharing uses the same referral backend.

## Search/social previews

Referral query parameters never change canonical metadata. Public project pages continue to server-render current project name, effective bio, canonical URL, and Showcase/project/LOOM image fallback before JavaScript executes. Private Admin/developer surfaces remain `noindex,nofollow`. Public crawler discovery is exposed through dynamic `robots.txt` and `sitemap.xml`; private surfaces and the Instance Vault are excluded.
