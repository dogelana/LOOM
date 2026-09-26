<!-- @loom-file release=0.15.68 revision=2 policy=package-priority -->
# LOOM Person Portability Standard — 0.15.68

LOOM treats a person as a portable identity aggregate, not merely one account row or one browser record. A portable person can be either a permanent User or a standalone Guest.

## Two complementary migration axes

LOOM deliberately separates **person-owned** data from **project-owned** data.

- **Portable User / Guest** is the horizontal person slice: that person's identity and attributable state across every project.
- **Project + Data** is the vertical project slice: that project's application plus runtime/state for every participant, but not each participant's complete LOOM-wide identity.

For a clean rebuild, export the people you intend to preserve and export each desired project with data. Restore people before projects when practical. Project-linked person state may remain dormant under its original project slug until the project is installed.

## Permanent User graph

Single-user export begins with the permanent User ID and resolves every provably attached identity/data surface: linked browser clients, attached Guest Histories, Guest Profiles/generations/installations, global profile, project identities, project module state, avatars/media, permissions, moderation, continuity/referrals, activity/replay/audit streams, and database rows whose ownership or subject/actor references point to that identity graph.

A Guest History already attached to a permanent User is intentionally not exported separately. It travels inside that permanent User bundle so the same person cannot be split into competing portable identities.

## Standalone Guest graph

A standalone Guest export begins with the canonical Guest ID and follows the complete proven Guest lineage: merged historical Guest IDs, browser/client identities, Guest Profiles/generations, installation aliases, LOOM-wide profile state, project identities and project module state, avatars/media, project grants, moderation, continuity/referrals, network/history metadata, activity/replay/audit streams, and attributable database rows.

The original stable Guest/client/profile IDs are preserved on a clean restore. Re-importing the same bundle resolves as an exact merge rather than creating a duplicate. A browser/client identifier already owned by a different person is a hard conflict during ordinary import.

## System Owner portability

Ordinary person import **never transfers System Owner authority**. A portable User or Guest bundle exported from the source installation's System Owner is only marked `wasSystemOwner=true`.

On a truly blank LOOM installation, first-run Administrator setup exposes **Import Previous System Owner**. That special bootstrap flow:

1. verifies the portable bundle and every checksum;
2. requires the bundle to be marked as the previous System Owner;
3. restores the complete User or Guest person first;
4. attaches the current fresh browser to that restored person;
5. creates a brand-new Admin token/credential for the destination installation.

Old Admin cookies, Admin tokens, server secrets, or source-installation ownership credentials are never transferred.

## Canonical ownership graph

Person portability reuses the same canonical ownership relationships used by identity cleanup. Features that persist ordinary `user_id`, `owner_type`/`owner_id`, `client_id`, `guest_id`, `guest_profile_id`, or `installation_id` database ownership participate automatically when their rows are attributable to the selected person.

## Security boundary

A permanent User bundle includes the account password hash so the same password can continue to work after an exact restore. It never includes plaintext passwords.

The following are installation/security state and are never portable with one person:

- active authentication sessions;
- password-reset tokens;
- Guest recovery secrets;
- database credentials;
- server secrets;
- the source installation's live System Owner binding or Admin credential.

A permanent user may retain their ordinary account privilege and explicit LOOM/project grants. A Guest retains its ordinary project grants and attributable state.

## Restore semantics

On a clean destination LOOM preserves original stable identity IDs whenever they are unclaimed. If matching identity/account data already exists, import previews a merge. Identity keys already owned by an unrelated person are treated as conflicts instead of being stolen.

Person import is merge/create-only. It does not destructively replace another person and does not delete unrelated destination data.

A person bundle does not import or overwrite project code. Project-referenced person state can be restored before its project exists and remains keyed to the original project slug until that project is installed.

## Portable bundle contract

The bundle manifest identifies the person type, source LOOM version, source stable IDs, prior-System-Owner marker, project references, graph counts, payload path, data classes, checksums, and included assets. Every ZIP member is covered by the normal LOOM portable-bundle checksum manifest and is verified before preview or mutation.
