<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Schema Evolution Ledger — 0.12.08

Structural changes receive stable migration IDs, version, description, applied/verified timestamps and an additive/rollback posture. The 0.12.08 ledger is intentionally additive and idempotent so existing databases and durable-local stores remain valid. Future destructive migrations must declare verification and rollback before execution.
