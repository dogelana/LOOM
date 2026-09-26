<!-- @loom-file release=0.15.68 revision=2 policy=package-priority -->
# First-Run Identity and Administrator Setup

## New browser installation

When no Guest Profile exists for the browser installation, LOOM creates or resolves a local Guest shell before normal identity work. New guest profiles receive a generated human-facing LOOM name and a selectable LOOM default preset avatar; internal `GuestHandle-*` lineage identifiers are not presentation usernames.

Additional people on the same browser create separate Guest Profiles through **Switch User**. Guest identities can later be attached to permanent email/password LOOM accounts without losing their history.

## Fresh Administrator bootstrap

Administrator bootstrap never occurs from a passive status/read call. The first Admin is created only by an explicit first-run action for the currently selected LOOM client identity.

The guided path is `/admin/setup/`. On an installation with no Admin, `/admin/` redirects there. The normal claim path requires acknowledgement before the current identity becomes System Owner.

## Import Previous System Owner

LOOM 0.15.68 adds a migration-safe alternative for a blank destination: **Import Previous System Owner**.

Before resetting a source installation, export its System Owner from **Admin → Backup & Restore → Users**. The System Owner may be either a permanent User or a standalone Guest; the exported portable bundle is marked as prior System Owner without containing the source Admin credential.

On the blank destination, choose that bundle from Home first-run setup or `/admin/setup/`. LOOM verifies the bundle, restores the complete person, attaches the new browser to the restored identity, and creates a fresh Admin credential for the destination installation.

The special owner-import endpoint is available only while the destination has no Admin identity. Once an Admin exists, all later User/Guest imports use ordinary Backup & Restore and cannot transfer System Owner authority.

## Recommended clean-migration order

1. On the source installation, export the System Owner portable person bundle.
2. Export every other permanent User and standalone Guest that must survive.
3. Export desired projects as **Project + Data**.
4. Verify/download every bundle before destructive work.
5. Install a blank LOOM release.
6. Use **Import Previous System Owner** during first-run setup.
7. Import the remaining people from Backup & Restore → Users.
8. Import Project + Data bundles from Backup & Restore → Projects.
9. Verify project participation, identity/avatar state, history, and project-specific runtime data before calling the migration complete.

Restoring people before projects is preferred because the identity namespace is already present when project state arrives. Project-linked records can remain dormant if restored before project code, but a Project + Data bundle is not a substitute for a complete person bundle.
