<!-- @loom-file release=0.15.08 revision=1 policy=package-priority -->
# Domain Landing

`loom.domain-landing` is the global Admin control for LOOM's server-side installation-base router.

- Default: the base URL opens LOOM Home.
- Optional: an Admin may select one active release project or Instance Project as the base landing target.
- LOOM Home is always available at `/home/` relative to the installation base.
- Mutable routing state is stored at `instance/config/domain-routing.json`.
- Disabling this global module safely makes the base URL resolve to LOOM Home without deleting the saved routing preference.
- Missing or archived target projects also fall back to LOOM Home.
