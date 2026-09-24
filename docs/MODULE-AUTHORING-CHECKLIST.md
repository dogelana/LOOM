<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# LOOM Module Authoring Checklist

Every new LOOM module should ship with these checks completed.

1. Give the module a stable system action ID and five-digit order.
2. Use `schema_version: "1.1"` for new modules.
3. Declare every meaningful semantic user capability in `user_actions`.
4. Map existing domain log events through `user_actions[].events` whenever possible.
5. Use `ctx.userAction` / `ctx.runUserAction` for semantic UI actions that do not produce a domain event.
6. Do not instrument every keystroke, hover, pointer move, or raw DOM event.
7. Keep user-action IDs stable across UI redesigns.
8. Give actions human-readable names and descriptions so the Pegboard and Action Registry remain self-documenting.
9. Test that the module's stateful bulb unloads on graceful close and heartbeat expiry.
10. Test that user-action pills pulse live and appear in historical session events.
11. Update the project fallback registry if the deployment supports static fallback mode.
12. Update release documentation/changelog whenever action semantics or schema behavior changes.

## Presentation / placement (v0.9.2)
- Declare `presentation.mount.region` / `slot` when a module belongs inside a shared layout region.
- Prefer `ctx.mount(node, fallbackSelector)` over directly appending to global DOM hosts.
- A layout/container module should declare `presentation.role = "region"` and expose stable `data-loom-slot` names for children.
- Keep module-specific visual styling inside the module; use LOOM presentation metadata for portable placement, sizing intent, alignment, and order.


### Cache safety
- Resolve project assets with `ctx.resolveAssetPath()`; do not hard-code `assets/...` URLs into DOM nodes.
- Let LOOM supply content-hash/fingerprint query versions.
- For manually constructed resources, use `ctx.cacheBustUrl(url, version)`.

## User/profile-aware modules (v0.9.4+)
- Read current identity from `ctx.identity`.
- Use `ctx.fetchApi()` rather than hard-coding the LOOM API base path.
- If a module intentionally changes the display identity, use `ctx.setUserLabel()` so the runtime, Event Bus, heartbeat, logs, and Pegboard converge immediately.
- A `clientId` identifies one browser/site profile; do not represent it as authenticated cross-device identity.


## v0.11.0 persistence/admin checklist
- Keep project-domain defaults in the project manifest; keep reusable module code generic.
- Expose administrator-adjustable configuration through `admin_settings`; do not build project-specific Admin forms.
- Do not trust local privilege labels for authorization; server-side Admin APIs/pages must call LOOM authorization helpers.
- Persist user-facing durable data through the LOOM persistence layer when available; local/browser-only storage is temporary mode.
- Add release notes only to the single root `CHANGELOG.md`.


## Bootstrap Loader
If a project uses a Loader, install the reusable `loader` module and declare `module.bootstrap.role = loader`. Do not manually recreate loader overlays in project app shells. Loader branding should resolve the standard Logo and Logo Text module configs.


## Transparent-image interaction
For interactive effects on transparent project art, prefer alpha-mask composition rather than rectangular overlays. The reusable Logo module demonstrates a pointer-reactive shine whose CSS mask is the resolved logo asset itself.

## v0.12.02 composition checks

- [ ] If the capability must exist in every project, consider `/core-modules` + `module.scope = "project"` instead of copying it into every project.
- [ ] If it is project-specific, keep it under that project's `/actions` tree.
- [ ] Expose meaningful configurability through declarative Admin settings rather than hidden constants.
- [ ] Give every meaningful capability a stable Action Registry / Pegboard identity and lifecycle.
- [ ] Optional collaboration with another module uses an explicit extension/provider contract and has a safe fallback when that teammate is absent.
- [ ] Never shadow a LOOM-owned project-core action ID.
- [ ] Document persistence ownership and project/global scope clearly enough that another human or AI developer can safely extend it.

