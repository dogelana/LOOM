<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Deployment Metadata Standard

LOOM release: **0.12.04**

LOOM deployments are self-describing. The authoritative machine-readable file is:

`/.loom-deployment.json`

Every shipped file is represented there with:

- `release` — the LOOM release that last changed the shipped copy.
- `revision` — monotonically increasing per-file revision.
- `sha256` — hash of the shipped bytes (except the manifest itself, which is self-referential).
- `policy` — deployment authority model.
- `embedded_header` — whether the source file also carries an in-file LOOM metadata note.

## Deployment policies

### `package-priority`

Release-managed application/source files. A higher LOOM per-file revision wins. Equal-revision content differences fall back to causal history rather than timestamps.

### `server-priority`

Live state whose server copy may contain user/runtime changes. If the server copy exists, the server wins and is pulled locally even when a newer package contains a default/stale copy. A local package copy may seed the server only when the server has never had the file.

### `server-only`

Runtime/ephemeral state. Never push or delete the server copy because of package contents. Remote state may be mirrored locally.

### `causal`

Neither side is permanently authoritative. Last-known-equal hashes and the causal divergence journal determine direction. Per-file revisions are informative, but do not destroy independent edits.

## Decision order

1. Deployment policy.
2. Per-file release revision when the policy allows version authority.
3. Last-known-equal causal state.
4. Content hash comparison / safe three-way merge.
5. Manual conflict when ambiguity remains.

Filesystem modification time is never source-of-truth.

## Runtime state

The manifest contains ordered path rules so remote-only live files are also classified even if they were never present in the release archive. This is how LOOM protects identities, users, logs, presence, project state, uploaded content, and similar live data.

## File headers

Where the syntax safely permits comments, LOOM also embeds a compact header such as:

`@loom-file release=0.12.04 revision=7 policy=package-priority`

Formats where comments are unsafe (notably JSON) use only the authoritative deployment manifest. Files are never renamed merely to carry version information because doing so would break imports, asset paths, registries, and APIs.

## Release tooling

Future LOOM packagers must preserve per-file revisions for unchanged files and increment only files whose shipped bytes change. New files start at revision 1. Removed package-managed files should be recorded as tombstones when deletion is intentional.

This standard is part of LOOM's modular contract and must be considered by Admin tooling, Action Registry tooling, project templates, core modules, deployment tools, and future AI/human development.
