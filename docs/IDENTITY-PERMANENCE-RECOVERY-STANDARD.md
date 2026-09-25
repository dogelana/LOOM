<!-- @loom-file release=0.15.53 revision=3 policy=package-priority -->
# LOOM Identity Permanence & Recovery Standard

Version: 0.12.00

## Core invariant

LOOM treats user-created data as durable regardless of authentication state.

A person does not need an email/password account for their work to deserve persistence. A browser/install begins with a server-backed **Guest Identity**. Creating or signing into a permanent account changes ownership, accessibility, and authentication; it does not decide whether the underlying history continues to exist.

There is no age-based Guest Identity purge in this release. Guest data is retained until an explicit administrative/legal/privacy deletion mechanism is intentionally invoked. Authentication-session expiry does not delete application data.

## Identity layers

LOOM separates four concepts:

1. **Client** — a browser/install identifier (`clientId`). This identifies a software context, not a human.
2. **Guest Identity** — the durable unauthenticated history/provenance record associated with one or more clients.
3. **Permanent User** — a password-backed LOOM account (`userId`).
4. **Project Identity** — optional project-scoped username/avatar overrides attached to either a Guest/Client owner or a permanent User owner.

IP addresses, approximate location, user-agent details, and similar observations are never authentication proof and never cause automatic attachment. Under the strict guest policy, an exact shared public IPv4 address or the same public IPv6 `/64` may conservatively remove anonymous Guest privilege and require permanent authentication; this is a safety gate, not a claim that the people are the same. Approximate city/region/country, ISP and ASN enrichment is display-only and is never an overlap trigger.

## Guest permanence

A Guest Identity records stable provenance including its Guest ID, client history, first/last seen times, recovery status, source snapshots, attachments, and merge lineage. Project module state remains stored under source client ownership even after attachment. Before structures that must move because of uniqueness constraints are promoted to a permanent user, LOOM creates a protected snapshot of the Guest Identity and archives applicable avatar assets.

A Guest Identity therefore remains inspectable after attachment. It is not converted into a user and erased; it becomes a preserved source history linked to a user.

## Legacy-data backfill

When Admin → Users identity management or SQL migration runs, LOOM scans known legacy client-owned records and creates Guest Identity wrappers for clients that predate v0.12.00. Existing data is not rewritten merely to perform this backfill; the Guest layer establishes durable provenance around it.

## Multi-device attachment

Independent devices may accumulate independent Guest Histories. When a device later signs into or creates a permanent account, LOOM attaches that device's eligible Guest Identity to the account.

Example:

```text
Guest A / phone    -> creates permanent User U
Guest B / laptop   -> later signs into User U -> attach Guest B
Guest C / tablet   -> later signs into User U -> attach Guest C
```

User U then has multiple attached Guest Histories. The original Guest IDs, source client rows, timestamps, snapshots, and attachment records remain available for provenance.

A browser whose existing Guest Identity is already attached to a different permanent user is never silently imported into the newly signed-in account. LOOM starts a fresh Guest attachment context for that account instead of leaking the prior user's history.

## Conflict-aware project-state merge

`loom_project_module_state` uses owner type `client|user`. Attaching a Guest History merges client-owned state into user-owned state without deleting the client source row.

The generic v0.12.00 merge rules are:

- associative objects: recursively merge keys;
- lists of objects containing stable `id` fields: union by ID and recursively merge matching objects;
- other lists: preserve the union of unique values;
- conflicting scalar values: keep the newer value active while retaining a merge-conflict record containing both values.

This behavior allows independently-created Shopping List items, groups, and similar ID-addressable objects to survive multi-device attachment rather than using a destructive newest-whole-blob winner.

Modules with domain-specific semantics may receive more specialized merge contracts in later releases. A module must never silently discard source Guest History during attachment.

## Attach Guest History

The preferred product term is **Attach Guest History**. “Claim” is avoided because the source Guest Identity continues to exist for provenance.

An attachment creates an immutable attachment record containing Guest ID, User ID, source client, time, actor, mode, and merge summary. Attachment is auditable.

## Guest recovery code

An unattached Guest Identity may generate a one-time-visible recovery code such as:

```text
LOOM-ABCD-EFGH-JKLM-NPQR
```

Only a cryptographic hash of the code is persisted by the server. The visible code must be saved by the user if they want deterministic recovery without a permanent account.

Entering the recovery code on another device attaches that new client to the recovered Guest Identity. If the new device already accumulated Guest History, LOOM snapshots and merges that history into the recovered Guest Identity rather than deleting it.

Once a Guest Identity is attached to a permanent account, normal account sign-in is the recovery method; Guest recovery-code use is refused for that attached identity.

## Support-assisted recovery

If a person loses a device before making an account and did not retain a recovery code, an Administrator may investigate preserved Guest Identities.

Admin → Users can surface candidate evidence such as:

- approximate activity dates;
- project overlap;
- recorded actions and object history;
- usernames used;
- known clients;
- observed network addresses;
- merge/attachment lineage.

Fuzzy evidence is for human review only. In particular, IP overlap is low-confidence evidence because shared networks, carrier NAT, VPNs, dynamic addresses, and public Wi-Fi make IP unsuitable as identity proof.

LOOM may rank or surface possible related Guest Identities, but it does not automatically attach them based on fuzzy evidence.

## Administrator Users / identity management

LOOM Admin includes permanent ownership/recovery operations inside the unified **Users** workspace. Since v0.15.53, permanent accounts and Guest Identities are presented in one directory instead of separate top-level Users and Identity Manager tabs. Supported identity operations include:

- inspect Guest Identity provenance/activity;
- inspect permanent users;
- issue or rotate a Guest recovery code after support verification;
- attach a Guest History to an existing permanent user;
- create a new permanent account from a Guest Identity;
- merge one unattached Guest Identity into another;
- inspect possible related Guest candidates and evidence signals;
- inspect preserved merge conflicts.

Destructive-looking identity operations require the explicit `CONFIRM` phrase. Attachment/merge operations snapshot source history first and write identity audit events.

## Guest-to-Guest merge

Guest-to-Guest merge is used for recovery or Administrator reconciliation of unattached histories. The source Guest Identity is not deleted. It is marked `merged`, records `mergedIntoGuestId`, and remains directly inspectable. Client mappings move to the canonical target Guest Identity, while source provenance remains retained.

## Storage modes

LOOM can persist identity/state in protected local JSON/JSONL storage or in SQL. In v0.12.00 the non-SQL mode is described as **durable-local**, not temporary storage. SQL remains the recommended durable production backend, but lack of SQL does not imply that Guest data should be automatically discarded.

## Deletion policy

v0.12.00 deliberately introduces no automatic Guest Identity deletion job and no age-based retention expiry. Any future deletion feature must be explicit, permissioned, auditable, and aware of attachments, snapshots, legal/privacy requests, and project-owned data dependencies.
