<!-- @loom-file release=0.15.32 revision=4 policy=package-priority -->
# LOOM Deployment Transaction Standard

LOOM release: **0.15.32**

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
## Live deployment gate (0.15.22+)

`/.loom-deploying.json` is an **ephemeral transport lease**, not release authority. Bridge/Deployer may create it before the first production mutation and must refresh its expiry while work continues. LOOM ignores an expired lease.

While an unexpired gate exists:

1. API requests fail with `503 Service Unavailable`, `Retry-After`, and `X-LOOM-Deploying: 1`.
2. `api/deployment-status.php` remains available as the one dependency-free readiness probe.
3. Browser runtime traffic pauses after the first deployment 503 rather than starting independent retry loops.
4. LOOM-owned PHP pages may render a minimal auto-refreshing maintenance surface.
5. The deployment transport should stage individual remote files beside their destination and server-rename them into place so clients never read a file while its body is still uploading.

Bridge Suite 8.5 / Deployer 5.4 extends that boundary: the gate is **not** removed immediately when the canonical manifest is committed. After manifest-last commit, the marker enters a `verifying` phase and remains active for one fresh post-commit inventory pass. The gate is removed only when that pass confirms no release-contract path remains unresolved.

The browser deployment guard proactively polls `api/deployment-status.php` even before another application API request occurs. Once it has observed a deployment transaction, it remains in maintenance mode until the status probe reports ready, then performs one clean reload into the canonical release.

If Deployer disappears, the refreshed lease expires automatically and returns LOOM to service; the stale marker alone is never sufficient to keep LOOM offline.

