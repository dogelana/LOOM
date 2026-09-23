<!-- @loom-file release=0.15.22 revision=2 policy=package-priority -->
# LOOM Health & Failure Standard — 0.15.22

Core health is machine-readable through `api/health.php`. Components report healthy/attention status and a graceful-degradation mode. Missing optional database connectivity does not stop LOOM; missing canonical release/identity/project foundations does. Modules should prefer read-only, queued, cached, or fallback behavior over blank failure.

During an intentional release deployment, `503 Service Unavailable` with `X-LOOM-Deploying: 1` is a controlled health state, not an authorization failure. Clients must collapse normal retries into the dedicated deployment-status probe and resume by clean reload after the deployment lease clears.
