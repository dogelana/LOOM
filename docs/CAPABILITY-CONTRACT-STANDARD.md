<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# LOOM Capability Contract Standard — 0.12.08

Modules may declare `capabilities.provides`, `capabilities.requires`, and `capabilities.permissions`. Required capabilities are checked during discovery. Scoped capability tokens are short-lived and can only be issued for permissions declared by that module. Legacy modules without contracts continue to run unchanged. Projects remain isolated by default; cross-project sharing must be an explicit capability.
