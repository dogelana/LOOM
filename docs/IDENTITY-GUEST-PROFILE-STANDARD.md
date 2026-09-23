<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Guest Profile & Generation Standard — 0.12.08

Guest profiles are explicit server-durable soft identities. A device installation can host multiple guest profiles. Each guest profile has a human-facing display name/avatar preset and an internal payload generation. Successful permanent authentication claims only the currently active generation, then atomically opens a fresh empty generation for that guest profile. Login is the claim trigger; no background timer guesses ownership. Browser storage only points to server records and is never the sole source of permanence.

## Shared-device switching

The identity entry surface is the canonical shared-device people chooser. Permanent-account sessions must explicitly sign out before another guest is activated. Guest display names are unique per installation for human clarity, while immutable profile/client/guest IDs remain internal. A claimed generation closes atomically and the same guest profile immediately receives a fresh generation.

Guest profiles are durable server records. Browser storage contains only installation/profile pointers; clearing all browser storage necessarily removes those local pointers, but does not delete server-side guest data. LOOM must never guess ownership across a fully wiped or unrelated device without a trustworthy identity event.
