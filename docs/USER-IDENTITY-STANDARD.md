<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM User Identity Standard — v0.11.0

## Identity layers
LOOM separates:
1. `clientId` — persistent browser-profile identity.
2. `sessionId` — one project/tab runtime session.
3. `username` — server-unique display identity; once set, it cannot be removed, only changed.
4. `userId` — permanent account identity created when email/password registration succeeds.
5. `privilege` — `Admin` or `User`, authorized by the server.

A permanent user account can own multiple client IDs. Signing in from a new browser binds that browser to the same `userId`.

## Temporary versus permanent
Before SQL is connected, client profiles/accounts are kept in protected local/server files. This allows development immediately but is treated as temporary persistence.

After MySQL/MariaDB is connected, initialized, and migrated, LOOM stores supported identity/account data in SQL and continues using local files only as cache/safety copies.

## Username rules
- Username must be non-empty.
- Usernames are unique server-wide, case-insensitively.
- Once a username exists it may be changed, but not removed.
- Creating a permanent email/password account requires an existing username.

## Password/account rules
- Valid email required.
- Password minimum: 8 characters.
- Passwords are stored only through PHP `password_hash()`.
- Login sessions use random HttpOnly cookies.
- Raw passwords are never stored.

## Privacy
Raw IP addresses are not used as LOOM identity.


## Durable profile timestamps (v0.11.05)
Every LOOM profile must expose `createdAt` (Profile Since) and `updatedAt` (Last Saved). Legacy profiles missing either value are backfilled from existing telemetry/account/file history and persisted so the values survive refreshes and SQL migration.

## v0.11.20 global/profile split
A permanent LOOM account has a LOOM-wide profile (username + profile picture). Each project identity independently chooses to inherit each global value or use a project-specific override. Account email/password/User ID/privilege remain global.

## Global profile surface (0.11.25+)

LOOM Home exposes the global profile independently of projects. A user may establish a LOOM username/profile picture and create a permanent account before joining any project.

The Home/global surface never displays project-specific identity overrides. Project-specific identity remains a separate layer presented only from inside that project.

## Project-to-LOOM avatar copying (0.11.26+)

Browser-uploaded profile pictures and trusted project-provided avatar assets use different validation paths.

Browser uploads remain compressed and constrained to the normal LOOM avatar upload dimensions. A trusted project default may retain larger source dimensions, provided it is a valid WebP/PNG/JPEG, remains under the stored-byte limit, and is within a conservative server-side maximum dimension.

This allows `Pull Picture from Project` to copy a project's packaged default artwork into the user's global LOOM Profile without weakening browser-upload validation.
