# LOOM Instance Vault template

`instance.sample/` is documentation and safe example material only.

The real installation creates a sibling `instance/` directory at runtime.
**Release ZIPs never contain the real `instance/` directory.**

The real Instance Vault is the single filesystem boundary for mutable installation state:

- database connection configuration
- guest profiles, generations, and guest identity state
- permanent-account filesystem fallback state
- global/project identity and profile state
- Admin/global/module settings
- project module state
- custom avatars
- custom project assets and branding overrides
- logs, audit, replay, presence, moderation, migration ledgers
- database-integrity backups
- project archives

Fresh-install rule: LOOM does not import or interpret any pre-Instance filesystem layout.
If `instance/` is missing, LOOM creates a new empty vault. If it is present, LOOM uses it in place.
