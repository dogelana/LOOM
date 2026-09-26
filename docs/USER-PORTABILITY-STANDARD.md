# LOOM User Portability Standard — 0.15.61

A permanent LOOM user is a portable identity aggregate, not merely one row in `loom_users`.

## Canonical portability graph

Single-user export begins with the permanent User ID and resolves every provably attached identity/data surface: linked browser clients, attached Guest Histories, Guest Profiles/generations/installations, global profile, project identities, project module state, avatars/media, permissions, moderation, continuity/referrals, activity/replay/audit streams, and database rows whose ownership columns or subject/actor references point to that identity graph.

The portability graph reuses the same canonical ownership relationships used by identity cleanup. Features that persist ordinary `user_id`, `owner_type`/`owner_id`, `client_id`, `guest_id`, `guest_profile_id`, or `installation_id` database ownership automatically participate in database-side user export without requiring a per-feature exporter.

## Security boundary

A portable user bundle includes the account password hash so the same password can continue to work after an exact restore. It never includes plaintext passwords.

The following are installation/security state and are never portable with one user:

- active authentication sessions;
- password-reset tokens;
- Guest recovery secrets;
- database credentials;
- server secrets;
- the source installation's System Owner binding.

A user may retain their ordinary account privilege and explicit LOOM/project grants. System Owner remains controlled by the destination installation.

## Restore semantics

On a clean destination, LOOM preserves the original permanent User ID and attached identity IDs so history remains internally continuous. If the same User ID/email already exists, import previews a merge instead of silently creating a duplicate. A browser client already attached to an unrelated user is a hard conflict.

User import is merge/create-only. It does not implement destructive replace. Existing unrelated users, projects, and installation state are never erased to make room for one user.

Project-referenced user state may be restored even when the project code is not installed yet. The data remains dormant under its original project slug and becomes active when that project exists again. A user bundle does not silently import or overwrite project code.

## Portable bundle contract

The bundle manifest identifies the user, source LOOM version, project references, graph counts, payload path, data classes, checksums, and included assets. The payload contains normalized account data plus the local/database/history slices attributable to the canonical graph.

Every file stored in the ZIP is covered by the normal LOOM portable-bundle checksum manifest. Import always verifies the ZIP before preview or mutation.
