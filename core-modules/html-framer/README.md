# LOOM HTML Framer

HTML Framer is a LOOM interoperability module for static front-end packages.

It is intentionally **not** a replacement for native LOOM modules. Native modules remain the preferred format because LOOM can understand their declared actions, capabilities, state, permissions, lifecycle, and internal events. HTML Framer exists for the useful middle ground: a finished HTML/CSS/JS mini-app can be dropped into a project without being rewritten first.

## What you upload

Upload one ZIP at a time from:

**Admin → Project Settings → HTML Framer**

A ZIP may contain:

- `.html` / `.htm`
- `.css`
- `.js` / `.mjs` / `.cjs`
- common static assets such as images, fonts, JSON, media, manifests, text, and XML

Server-side executable files are rejected. The package is stored beneath the project's **Instance Vault**, never in the release tree.

## Entrypoint selection

HTML Framer resolves the page in this order:

1. a root `index.html`
2. a root `index.htm`
3. one uniquely named `index.html` / `index.htm` anywhere in the ZIP
4. the only HTML file, when exactly one exists
5. if multiple HTML files remain ambiguous, Admin is asked to choose the entrypoint before publishing

Other HTML files are preserved and can still be linked from the selected entrypoint.

## Relationship discovery

LOOM keeps existing valid relationships and repairs what it safely can.

### HTML

Existing local `src` / `href` references are resolved against the entrypoint directory.

If a referenced path does not exist but the referenced basename uniquely matches one uploaded file, HTML Framer repairs that relationship at serve time.

### CSS

`@import` relationships are discovered. CSS files that are already imported are treated as dependencies.

Unreferenced root CSS files are automatically linked into the selected entrypoint.

### JavaScript

Static `import`, `export ... from`, side-effect `import`, dynamic `import()`, and common `new URL(..., import.meta.url)` relationships are discovered.

JavaScript files referenced by another JavaScript module are treated as dependencies. Unreferenced root JavaScript files are automatically attached to the selected entrypoint. Files that appear to use ES module syntax are attached with `type="module"`; otherwise they are attached as classic scripts.

This means all of these are valid:

- one self-contained HTML file
- HTML + CSS, with the `<link>` already present
- HTML + JS, with the `<script>` already present
- HTML + CSS + JS with no relationship tags at all
- inline CSS + external JS
- external CSS + inline JS
- a small ES-module tree
- additional images/fonts/data files

## Multiple HTML files

Multiple HTML files are not guessed blindly.

If there is an unambiguous `index.html`, LOOM uses it. Otherwise the upload stays in the analysis stage and Admin chooses the entrypoint.

## Every frame is a LOOM module

The manager itself is `loom.html-framer`.

Each published frame is dynamically exposed to the LOOM registry as its own module:

`html.frame.<frame-id>`

That gives every frame normal LOOM module placement, ordering, collapse behavior, runtime availability, Pegboard visibility, and lifecycle boundary events.

LOOM does **not** pretend it understands internal button clicks or application state inside the imported page. Tracking stops at the frame boundary unless the HTML app is later converted into a native LOOM module.

## Security boundary

Imported code runs in an iframe with a restrictive sandbox:

- scripts are allowed
- forms are allowed
- modal dialogs are allowed
- downloads are allowed
- **same-origin privilege is not allowed**
- top-level navigation is not allowed
- direct access to LOOM parent JavaScript is not allowed

The served static files also receive a dedicated Content Security Policy and `no-referrer` policy.

This deliberately favors compatibility while keeping imported code outside LOOM's trusted module runtime.

## Persistent storage

Frame packages live under:

`instance/projects/<project>/html-framer/`

They are installation state.

They are not present in LOOM deployment ZIPs, are not synchronized by the Bridge, and survive normal hot-drop application updates.

## Import limits

LOOM accepts ZIPs through PHP `ZipArchive` when available and falls back to PHP `PharData` on hosts where the Zip extension is not enabled.

The initial implementation guards against accidental ZIP bombs:

- maximum ZIP upload: 25 MB
- maximum files: 800
- maximum total uncompressed content: 100 MB
- maximum individual file: 12 MB

These limits are deliberately conservative for a lightweight static-frame feature.

## Action Reader v1

LOOM 0.15.10 automatically catalogs observable interactive controls in framed HTML and injects a sandbox-local bridge that reports clicks/navigation, submits and field changes to the parent LOOM runtime. Those interactions become declared dynamic-frame user actions and therefore participate in Action Registry, Pegboard/session history and Activity Explorer.

Named JavaScript functions/listener registrations are surfaced as analysis hints only; LOOM does not wrap arbitrary foreign functions because doing so could alter application behavior.

## Frame layout and LOOM chrome

New frames default to **100% of the usable project page width**, centered. Admin can change the project default for future imports and can set each frame independently from 50–100% width. Existing frames without an explicit width inherit 100%.

HTML Framer uses LOOM's **page-width breakout lane** rather than the normal project content lane. Ordinary project modules remain inside the comfortable capped content column; HTML frames can expand against the whole usable page width without changing the rest of the project's layout.

HTML Framer does not own a separate title/collapse bar. Each dynamic frame module inherits LOOM's canonical Module Presentation policy, so global, project, and per-module title-bar/collapse settings apply normally.


## Full-screen frame takeover

Each frame may expose a LOOM-owned **Full screen** button. It temporarily lifts the framed module above the project shell and uses the whole browser viewport, then restores the frame to the same module position on exit. This is a parent-shell presentation feature; it does not add sandbox permissions or let framed code access LOOM internals.

- Auto-fit frames keep iframe scrolling disabled. Long content behaves like a full page and the takeover surface itself can flow vertically.
- Fixed-height frames keep their explicit internal scrolling behavior and fill the viewport while full screen.
- `Escape` exits full screen from either the parent page or the framed document.
- Admin can disable the control per frame, and can set the default for newly imported frames.
- The persisted `fullscreenEnabled` setting travels with Project export/import because it is stored in the frame registry.


## True auto-fit contract (0.15.40)

Auto height boots from a useful viewport-sized iframe and uses a sandbox-local layout bridge to report intrinsic framed layout to LOOM. Historical 0.15.40 behavior attempted to expand nested overflow containers; 0.15.42 supersedes that behavior with the viewport-safe contract below. Fixed-height mode remains the Admin-controlled path for a bounded iframe with iframe scrolling.


## Stable auto-fit and project launch full screen (0.15.41)

Layout bridge v3 prevents parent iframe height changes from immediately retriggering another auto-height cycle. Height growth is applied immediately; shrinkage requires confirmation, and small rounding/font deltas are ignored. This removes the feedback loop that could make surrounding project modules appear to jump up/down while a framed app settled.

Each project may also persist one optional `autoFullscreenFrameId` in its HTML Framer registry. The setting is off by default, is exported/imported with `frames.json`, and only activates when that frame is live and permits full screen. Missing, disabled, or deleted selections safely fall back to the normal LOOM project surface.


## Viewport-safe smart auto-fit (0.15.42)

Layout bridge v4 does not mutate foreign application layout. It classifies the framed package as ordinary **document flow** or a **viewport application**. Documents grow the outer iframe to their natural height after measurements settle. Viewport apps/games keep their intentional 100%-height/body-overflow/internal-panel behavior and receive one stable viewport-height iframe.

This is especially important for animation/game packages that continuously update HUD text. Text churn no longer causes layout measurements, and parent height-only resizes are ignored as intrinsic content changes. The detected layout family is locked per desktop/mobile profile so surrounding LOOM modules cannot pulse around the frame.

Serving also removes any legacy LOOM bridge script already present in an older imported/exported HTML file before injecting the current bridge, so there is never more than one active LOOM layout/action instrumentation layer.
