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


## Layout and LOOM chrome (0.15.32)

HTML Framer content defaults to 95% of the available project page width and is horizontally centered. Admin may change the project default for newly imported frames from 50–100%, and every published frame has its own width override. Legacy frames without a stored width inherit 95%.

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
