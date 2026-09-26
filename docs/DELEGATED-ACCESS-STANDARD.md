# Delegated Access Standard

LOOM 0.15.18 separates installation ownership from delegated administration.

## Authority hierarchy

1. **System Owner** — the original/bootstrap LOOM authority. This authority is immutable: delegated admins cannot demote, revoke, replace, or lock it out.
2. **LOOM Admin** — a permanent account delegated by the System Owner. Multiple LOOM Admins are allowed. They can administer LOOM and projects but cannot create/revoke LOOM Admins or acquire System Owner authority.
3. **Project Admin** — a permanent account or Guest Identity delegated to a specific project. It can manage that project's settings, modules, content, users, and Project Manager delegation.
4. **Project Manager** — a permanent account or Guest Identity delegated to a specific project for project settings/modules/content without LOOM-global authority.
5. **Member / Guest** — normal project use without delegated administration.

## Capability-first authorization

Roles are presets. Server authorization uses effective capabilities such as `project.settings`, `project.modules`, `project.users`, `project.access`, `html-framer.manage`, `loom.settings`, and `loom.database`. UI visibility is secondary; hiding a button is never the authorization boundary.

## Scope isolation

Project delegates receive no authority over unrelated projects. Global developer surfaces (LOOM Settings, Database, Identities, Activity, Pegboard, Action Registry) remain unavailable unless the identity also has LOOM-level authority. The shared Admin Tools drawer filters itself from the same effective permission state.

## Guest delegation

Project grants may target durable Guest Identities. LOOM resolves the active browser/client generation back to its Guest Identity before evaluating the project grant, allowing a guest to manage a project without first converting to a permanent account.

## Storage and audit

Delegation state is installation-owned data stored beneath the protected Instance Vault and is never shipped in release packages. Grants and revocations are audit-recorded. Existing bootstrap ownership and accounts are preserved during upgrade.


## Project visibility and private access (0.15.75)

Project visibility is installation state owned by LOOM Access Control, not project source. `public` is the default. Only the System Owner may switch a project between `public` and `private` or grant/revoke ordinary private-project viewing access.

Unauthorized viewers must never receive a private project in project discovery. Direct private project entry must be rejected before project metadata or shell HTML is emitted. Project-owned assets for private projects must use signed project/path URLs and reject unsigned probing. Authorized LOOM Admins and project-scoped Admins retain access consistent with their administrative authority; ordinary users/Guests require an explicit private-project view grant.

Grant selectors are a people view, not a raw identity-lineage view. Permanent accounts are labeled with their canonical visible LOOM display name. Only active standalone Guest Profiles may appear as Guests; attached/claimed browser lineage and standby future Guest generations remain internal history.

When a Guest becomes permanent, project Admin grants and private-project view grants migrate to the permanent user subject so access does not disappear merely because authentication status changed.
