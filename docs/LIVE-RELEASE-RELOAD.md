<!-- @loom-file release=0.15.03 revision=1 policy=package-priority -->
# Live Release Reload

## Purpose

LOOM can be deployed while people still have Home, Admin, developer surfaces or projects open. Browsers do not normally reload merely because files on the server changed. LOOM 0.15.03 adds a global release watcher so open sessions converge onto the completed release automatically.

## Commit marker

The watcher requests `api/version.php` with `cache: no-store`. The endpoint exposes the canonical release plus `deploymentFingerprint`, a short SHA-256 fingerprint of `/.loom-deployment.json`. Compatible LOOM deployers commit that manifest last, after package-owned files verify remotely. Therefore the watcher treats a changed, healthy manifest as the completed-deployment signal.

It does not react to arbitrary file mtimes or individual module changes.

## Safety and timing

The default global module configuration is:

- automatic reload: on
- poll interval: 8 seconds
- notice duration: 1200 ms

A candidate deployment is rechecked after 1.8 seconds and must still expose the same fingerprint with `health.status = healthy`. If the server canonical version is temporarily older than the client file, LOOM assumes a manifest-last deployment is still in progress and waits rather than refreshing backward.

Before navigation LOOM emits `loom:release-will-reload` with `{version, fingerprint}`. Modules with unsaved transient state may listen for that event and persist a draft. The browser then uses `location.replace()` with cache-bust parameters while retaining the current route and existing query parameters.

A sessionStorage loop guard prevents repeated automatic refreshes against the same fingerprint within 90 seconds. If stale upstream caching somehow serves old application code again, LOOM shows a manual-refresh banner instead of looping.

## Configuration

Admin → global modules → **Live Release Reload** (`loom.release.watch`) can disable automatic refresh or tune the poll interval and notice duration. Disabling the module itself also disables the watcher.

## First deployment caveat

A page that was already open on a release older than 0.15.03 has no release-watcher code and cannot be remotely forced to execute code it never loaded. The 0.15.03 deployment establishes the capability. After that page is manually refreshed once (or reopened), future deployments can refresh it automatically.
