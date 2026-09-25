<!-- @loom-file release=0.15.49 revision=4 policy=package-priority -->
# LOOM Module Presentation Standard (v0.9.2)

LOOM separates **what a module renders** from **where that module belongs**.

A module owns its own markup and styling. LOOM reads the optional `presentation` manifest object to resolve placement into named regions/slots and to apply normalized layout hints.

## Manifest shape

```json
{
  "presentation": {
    "role": "content",
    "mount": { "region": "header-bar", "slot": "brand" },
    "layout": {
      "width": "content",
      "align": "center",
      "position": "flow",
      "order": 10
    }
  }
}
```

Region modules use `role: "region"` and expose a stable `region` name. The runtime marks their mounted node with `data-loom-region`. Region modules may expose any number of named child slots using `data-loom-slot`.

Content modules target a region and optional slot. `ctx.mount(node, fallbackSelector)` resolves that target automatically, applies presentation metadata, mounts the node, and preserves declared intra-region order.

## Standard helpers available to modules

- `ctx.presentation`
- `ctx.resolveMountTarget(fallbackSelector)`
- `ctx.applyPresentation(node)`
- `ctx.mount(node, fallbackSelector)`

## Layout keys

`width`: `auto`, `content`, or `full`  
`widthScope`: `container` (default) or `page`; `page` is meaningful for root-mounted modules in shells that expose a page-width breakout lane  
`align`: `start`, `center`, `end`, or `stretch`  
`position`: `flow`, `relative`, `absolute`, `sticky`, or `fixed`  
`order`: integer ordering inside the resolved host/slot  
`layer`: z-index hint  
`maxWidth`, `minHeight`, `margin`, `padding`: CSS-compatible strings  
`className`: optional class(es) LOOM adds to the module root

## Generic project example

`core.ui.header-bar` owns region `header-bar` and slots `brand` and `utility`.

`core.ui.load-logo` and `core.ui.load-logo-text` both target `header-bar / brand`. They do not know the header's internal CSS or need to query one another. This lets later modules target `header-bar / utility` without modifying branding modules.


## Bounded media slots
Layout regions should expose bounded media slots when arbitrary images are injected. Child media modules must size against the slot, not against intrinsic source dimensions. The baseline Header Bar uses `brand-media` and `brand-text` sibling slots as the reference pattern.


## Optical offsets

LOOM presentation layout supports optional visual alignment offsets:

```json
"presentation": {
  "layout": {
    "offsetX": "0px",
    "offsetY": "10px"
  }
}
```

These are applied with the CSS `translate` property after normal layout placement. Use them for deliberate optical alignment where the visible artwork or font glyphs do not visually center inside their mathematical boxes. Offsets are presentation metadata, not content logic.

## Page-width breakout scope (v0.15.34)

`presentation.layout.widthScope = "page"` lets a root-mounted module request the usable project-page width without forcing every ordinary project module to become wide. The project shell remains responsible for defining that page lane. This is intended for full-canvas surfaces such as HTML Framer; ordinary content should continue to inherit the default `container` scope.
