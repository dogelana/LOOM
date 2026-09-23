<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Health & Failure Standard — 0.12.08

Core health is machine-readable through `api/health.php`. Components report healthy/attention status and a graceful-degradation mode. Missing optional database connectivity does not stop LOOM; missing canonical release/identity/project foundations does. Modules should prefer read-only, queued, cached, or fallback behavior over blank failure.
