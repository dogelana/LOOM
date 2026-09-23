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
