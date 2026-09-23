<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM User Moderation Standard — v0.11.10

LOOM moderation is project-scoped and identity-based.

- Permanent users are moderated by `userId`.
- Pre-account profiles are moderated by stable `clientId`.
- When a client profile becomes a permanent user, its moderation state is promoted to the user identity.
- IP addresses are **never** used as authentication proof, identity matching, or the ban key.

## Ban semantics
A ban is a retained state, not deletion. The account/profile and all stored history remain intact. A banned identity cannot load project modules or write project telemetry.

`includeData` defaults to false for a banned identity. When false, retained events/sessions are omitted from normal project telemetry views. An administrator may explicitly turn it on to make retained historical data visible again without unbanning the user.

Future project data APIs must call `loom_enforce_project_access($project,$clientId)` for writes/access and `loom_project_record_visible($project,$clientId,$userId)` when composing project-visible datasets.

## Administrator mutations
Username, email, password, ban/unban, and banned-data visibility mutations require the literal confirmation phrase `CONFIRM` at the API layer. Passwords are replace-only: current password hashes are never exposed. Replacing a password revokes prior login sessions.

## Audit
Administrator mutations are appended to the protected moderation audit store and, when SQL is active, `loom_admin_audit`.
