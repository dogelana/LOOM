<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# Green Beans — LOOM Project Vision Notes

- Green Beans is the first project deployed on LOOM.
- The Global Green Beans Dictionary is the foundational source-of-truth graph and a long-term core product goal.
- The current base store set is intended to remain fixed for the foreseeable future; do not casually expand it.
- User-entered uncatalogued food data should be called **manual entry**, not raw.
- Green Beans-specific playful names are branding aliases only. Formal engineering concepts remain generic so development stays precise.
- LOOM's Action Dictionary should eventually describe every meaningful system/user capability needed to build Green Beans, one modular action at a time.

- All meaningful Green Beans data should converge on stable shareable object IDs so Scheduling, Research, Collections, Nutrition, Explore, Sessions and future social features can point to the same objects.
- Amount / quantity is a generic cross-object property. Its display value is deliberately freeform text and defaults to `1`.
- Buying and consuming are separate lifecycle concepts. Consumption requires purchase/on-hand eligibility.
- Completed Green Beans Sessions are immutable snapshots of the full working state, not merely references to live objects.
- Explore is intended to become the human-facing global directory over all Green Beans data, including LOOM/Pegboard telemetry when available.
