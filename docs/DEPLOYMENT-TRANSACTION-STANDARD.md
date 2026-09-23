<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Deployment Transaction Standard

LOOM release: **0.12.06**

`/.loom-deployment.json` is the single canonical platform release authority. A deployment is not complete until the server copy of that manifest is committed.

## Transaction order

1. Read the local release manifest and the current remote manifest.
2. Preserve/pull `server-priority` and `server-only` live state.
3. Apply and verify release-managed `package-priority` files using per-file revisions and hashes.
4. Resolve `causal` files independently; unresolved conflicts never permit a false release commit.
5. Verify every package-priority file required by the local manifest and every package tombstone.
6. Upload `/.loom-deployment.json` **last**.
7. Read it back and verify its content hash. Only then may the server report the new canonical LOOM release.

## Mixed-release state

If source files have begun updating but the manifest has not been committed, the server remains on its previous canonical release. This is intentional. `api/version.php` exposes release health so the UI can distinguish a completed release from a mixed/partial deployment.

## Runtime authority

LOOM Home, Admin, shell/footer branding, and release-history current-version labels must use the canonical release endpoint rather than independently maintained version strings. Hard-coded strings are permitted only as offline/failure fallbacks and cache-bust tokens.
