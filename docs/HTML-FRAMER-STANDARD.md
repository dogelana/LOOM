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

