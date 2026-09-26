<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Guest / Visitor Identity Standard

Version: 0.12.00

A browser/device receives a stable LOOM `clientId` from local storage. The client identifier represents a browser/install context, not a human being.

## Guest Identity

Visiting LOOM Home registers the client with the server and establishes a durable **Guest Identity** when one does not already exist.

A Guest Identity may have:

- a Guest ID;
- one or more client IDs;
- a client-owned LOOM global profile;
- generated or user-selected LOOM username;
- LOOM/global avatar state;
- first seen and last seen timestamps;
- server-observed network metadata;
- project identities after entering projects;
- project module state and activity history;
- an optional recovery-code hash;
- snapshots, attachment history, and merge lineage.

A Guest Identity has no email/password permanent account until it is attached to one.

Admin surfaces these identities as **Guest Identity**, not “temporary data.” The unauthenticated status may be temporary; the stored history is not.

## Permanent account attachment

Registration or sign-in can attach the current device's Guest History to a permanent user. LOOM preserves source history and records provenance rather than treating attachment as a destructive migration.

Additional devices can attach their own independent Guest Histories to the same permanent account later.

## Recovery

An unattached Guest Identity can create a recovery code. A valid recovery code can reconnect a future client to that history. Support/Admin may also investigate Guest Identities and manually attach/merge histories after verification.

## Privacy / identity rules

A new browser/device or cleared browser storage normally receives a new client ID until the person signs in or recovers an existing Guest Identity.

IP addresses are metadata only. LOOM never uses an IP address, approximate location, browser characteristics, or fuzzy similarity as automatic authentication proof.

See `IDENTITY-PERMANENCE-RECOVERY-STANDARD.md` for the full attachment, merge, recovery, and retention contract.
