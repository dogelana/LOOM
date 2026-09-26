# HTML Framer Standard

LOOM HTML Framer is an interoperability boundary for static HTML packages.

## Core rule

Native LOOM modules remain the primary application format. HTML Framer modules are sandboxed imported surfaces whose internal application semantics are opaque to LOOM.

## Persistence

Uploaded packages are installation-owned state beneath:

`instance/projects/<project>/html-framer/`

Release packages MUST NOT contain this data.

## Import

Admin uploads one ZIP. LOOM inventories safe static files, selects or requests an HTML entrypoint, discovers file relationships, records safe path repairs, and identifies unreferenced CSS/JS roots for automatic attachment.

## Entrypoint ambiguity

LOOM may automatically choose only a uniquely determined index or the sole HTML file. Otherwise Admin must choose.

## Runtime representation

Every published frame is emitted by `api/modules.php` as a dynamic LOOM module with action id:

`html.frame.<frame-id>`

It participates in normal ordering, presentation, collapsing, module availability, and Pegboard boundaries.


## Layout and LOOM chrome (0.15.34)

HTML Framer content defaults to 100% of the usable project page width and is horizontally centered. Admin may change the project default for newly imported frames from 50–100%, and every published frame has its own width override. Legacy frames without a stored width inherit 100%.

The percentage basis is the **page lane**, not the normal capped project-content container. Dynamic HTML-frame descriptors declare `presentation.layout.widthScope = "page"`. The project shell keeps ordinary modules in its normal content lane while allowing page-scoped root modules to break out to the shell's full usable inline width. This means 95% now means 95% of the actual usable page, even when the project's ordinary module column is capped at 1320px.

An HTML package never owns LOOM's surrounding title bar or collapse control. Dynamic `html.frame.<frame-id>` descriptors pass through the canonical Module Presentation policy. Global, project, and per-module title-bar/collapse settings therefore apply exactly as they do to native content modules. When title bars are hidden, collapse is also disabled so there is no separate HTML-Framer-only expand/collapse strip.

## Trust boundary

Frame code is not native LOOM code.

The iframe intentionally omits `allow-same-origin`. It cannot directly access the parent LOOM runtime, LOOM browser storage, or trusted module context.

Internal frame interactions are not represented as LOOM user actions unless the package is later converted to a native module.

## Relationship repair

Serving is virtualized through `api/html-framer-file.php`.

Local HTML/CSS/JS references are resolved against the uploaded virtual filesystem at request time. Exact paths win. A unique case-insensitive path or unique basename may repair a broken local reference. External URLs remain untouched.

The selected entrypoint receives discovered root CSS/JS files that were otherwise unattached.

## Limits

HTML Framer is intentionally bounded against accidental ZIP bombs. Current limits are documented in the module README.

## Action Reader v1 (0.15.10)

HTML Framer now includes a backwards-compatible **Framed Action Reader**. Imported packages do not need LOOM-specific code.

At import time LOOM scans the selected entry HTML for observable interactive controls such as buttons, links, forms, inputs, selects, textareas, and `role="button"` elements. It also inventories named JavaScript functions and `addEventListener(...)` registrations as discovery hints. Function candidates are **not automatically wrapped or monkey-patched**, because arbitrary function wrapping can change foreign application semantics.

At serve time LOOM injects a small privacy-conscious bridge into the sandboxed HTML. The bridge observes:

- meaningful clicks/taps on buttons and button-like controls;
- link navigation intent;
- form submission;
- field change events.

It never transmits typed text values. Checkbox/radio state, select index, and file-count metadata may be recorded because they describe the interaction without copying field contents.

Each import-time control becomes a declared LOOM `user_action` under the dynamic `html.frame.<frame-id>` module. Runtime-created controls fall back to declared generic dynamic actions. The sandbox posts observations to its parent frame runtime, which translates them through normal `ctx.userAction(...)` lifecycle logging. As a result Pegboard, Action Registry, session history, and Activity Explorer can treat framed actions like native LOOM user actions.

This is an interoperability reader, not a promise that LOOM can infer every internal JavaScript function's semantic meaning. Observable UI behavior receives first-class action tracking; internal functions remain hints unless the foreign package exposes them through UI or is later adapted explicitly.


## URL Snapshot Import (0.15.18)

HTML Framer can also start from an `http://` or `https://` URL. **Capture Static URL** downloads a bounded snapshot of the public page and same-origin assets that can be discovered safely, converts that capture into a normal HTML Framer package, and then publishes it through the same sandboxed static-package runtime.

This is deliberately **not** a live remote website iframe and there is no automatic production sync. It works best for server-rendered or mostly static pages. Sites that depend heavily on authenticated APIs, client-side routing, WebSockets, service workers, protected assets, anti-bot systems, or runtime-generated resources may not reproduce exactly from a static capture.

The iframe used by HTML Framer is an isolation boundary around LOOM's local/static imported package. It does not mean the source production website is being displayed live.

A live production connection can be implemented in several different ways depending on the target system: a remote iframe when the target site's CSP / `frame-ancestors` / `X-Frame-Options` permits it, a native/API integration, or a deliberately engineered server-side proxy/adapter. A remote iframe is therefore one possible live integration, not the only one.

URL capture rejects localhost, private/reserved network targets, and cross-origin asset crawling, and applies bounded download/file limits before feeding content into the existing HTML Framer validation pipeline.

## Portable project ownership

HTML Framer packages belong to the project, even though their working files live in the Instance Vault. LOOM 0.15.37 therefore includes them in ordinary Project exports and restores them during Project imports. `frames.json`, extracted package files, and each preserved `source.zip` travel together. Temporary capture/build directories and backup remnants are excluded.


## Responsive height contract (0.15.38)

HTML Framer uses content-driven height by default. Every served framed HTML document receives a small LOOM layout bridge that reports its current rendered document height through `postMessage`. The parent runtime accepts layout messages only from its own iframe window and matching frame ID. In `auto` mode the iframe disables inner scrolling and grows with delivered content.

Desktop/tablet and mobile (760px and below) store independent height mode, fixed-height value, and page-width percentage. `fixed` mode is the explicit opt-in for a bounded iframe viewport and browser scrolling. Legacy frame records that predate these fields default to `auto` while retaining their historical numeric height as the future fixed-height value.


## Full-screen / full-page takeover contract (0.15.39)

An HTML Framer module may expose a LOOM-owned **Full screen** control. This control does not grant the sandbox more privilege and does not convert the foreign package into a native LOOM page. It changes only the parent presentation boundary.

When entered, LOOM portals the frame runtime surface above the normal project shell, fills the current browser viewport, locks background LOOM scrolling, and preserves a placeholder at the module's original DOM position. Exiting by the control, parent `Escape`, or an `Escape` key observed inside the sandbox restores the frame to that exact position and restores the document scroll state. Only one HTML frame may own this takeover state at a time.

The height contract remains authoritative while full screen. In `auto` mode the iframe itself remains non-scrollable and expands to at least the viewport height; content taller than the viewport flows as a full-page surface. In `fixed` mode the iframe fills the viewport and its explicitly enabled internal scrolling remains available. Desktop/mobile profiles continue to switch normally during orientation and viewport changes.

The project-level `defaultFullscreenEnabled` setting controls the default for newly imported frames. Each frame persists its own `fullscreenEnabled` value in `frames.json`, so the preference travels with normal Project export/import bundles. Legacy frames with no stored value default to enabled.


## True auto-fit / no-inner-scroll contract (0.15.40)

The default HTML Framer presentation is **auto** on desktop and mobile. LOOM boots auto frames at a real viewport height so `100vh` foreign layouts cannot lock themselves into an artificially short iframe, then uses layout bridge v2 to continuously measure delivered content. In auto mode the bridge also expands vertically constrained nested scroll/clipping containers when their content exceeds their client height. This makes the outer LOOM page, not the iframe, own normal page scrolling.

A framed package may mark a deliberate internal vertical scroller with `data-loom-preserve-scroll`. Admin fixed-height mode is the explicit alternative: the iframe becomes bounded and scrolling is allowed.


## Stable auto-height rule (0.15.41)

Auto height must never create a parent/child resize feedback loop. The framed bridge ignores iframe-height-only resize events caused by the parent, MutationObserver does not watch the bridge's own style-attribute writes, and parent height application uses hysteresis: meaningful growth may apply immediately, while shrinkage is confirmed before changing the project layout. Module DOM order remains untouched by height measurement.

A project may designate zero or one installed HTML frame for automatic full-screen takeover at project load. The persisted selector is `autoFullscreenFrameId` in the project-owned HTML Framer registry. The default is empty/off. Runtime activation is conditional on the selected frame being present, enabled, and full-screen-enabled.


## Viewport-safe auto-fit rule (0.15.42)

Auto-fit must preserve the imported application's layout ownership. Layout bridge v4 classifies each desktop/mobile profile as either **document** or **viewport**.

- **Document** pages report natural document height. LOOM keeps iframe scrolling disabled and resizes the outer frame after the reported height settles.
- **Viewport** applications are intentionally built around the viewport itself, typically with `html/body` or an app shell at 100% height plus body overflow locking and deliberate internal panels. LOOM gives these packages a stable viewport-height iframe and does not attempt to expand their internal scrollers.

The bridge must not rewrite arbitrary foreign `overflow`, `height`, `min-height`, or `max-height` rules. Foreign application state/HUD text changes are not shell-layout events. Height-only iframe resizes from the parent are ignored as intrinsic remeasurement triggers, while width/profile changes, fonts, transitions, forms, and explicit parent requests may remeasure safely.

At serve time LOOM removes historical `data-loom-framed-layout` and `data-loom-framed-action-reader` script copies before injecting the current implementation. Exactly one LOOM bridge owns each served framed document.


## Page-scroll lane and interaction lock (0.15.43)

The default desktop and mobile frame width is 80% of the usable project page lane. This is intentional: outer gutters remain LOOM-owned scroll targets. Width remains configurable from 50–100% per device profile and per frame.

A frame may set `interactionLockEnabled=true`. This setting is off by default and is persisted in `frames.json`. While locked, the iframe does not receive pointer interaction and a transparent parent-owned shield lets wheel/touch gestures operate on the LOOM page. A centered Unlock control restores iframe interaction. The user can subsequently lock it again from the runtime dock beside Full screen. Entering full screen always unlocks interaction.

## Frozen viewport contract (0.15.46)

Layout bridge v5 makes **viewport** classification terminal for the current desktop/mobile profile. A viewport app's outer iframe height is owned by LOOM and frozen to one stable layout-viewport slot. Internal ResizeObserver callbacks, animation/HUD updates, DOM churn, body overflow rules, iframe height changes, and mobile visual-viewport/browser-chrome height changes MUST NOT renegotiate the surrounding LOOM layout. A new slot may be chosen only after a meaningful outer page-width or desktop/mobile profile change.

The parent Framer wrapper, surface, stage, iframe, and containing module frame opt out of browser scroll anchoring. This is a shell-stability rule: a framed game may resize its own canvas internally without causing Showcase or other neighboring modules to appear to swap position or jump the viewport.

The runtime Lock/Unlock control is available on every frame. `interactionLockEnabled` remains an off-by-default **Start locked** preference, not a requirement for the runtime control to exist. Locked mode disables iframe pointer interaction while leaving the parent overlay available for normal LOOM wheel/touch scrolling; full-screen entry unlocks the frame.

Legacy `loom-html-framer/v1` registries migrate to presentation schema v2. Missing responsive/fullscreen/lock fields receive current safe defaults. The old untouched 100% width shape is normalized to 80% only when the record clearly predates the presentation fields; explicit modern presentation values remain project-owned.
