<!-- @loom-file release=0.15.74 revision=3 policy=package-priority -->
# LOOM Loader Standard — v0.11.01

## Purpose
The Loader is a reusable core LOOM project module. A project opts in by installing a manifest with:

```json
"module": {
  "bootstrap": {"role":"loader","priority":0,"countSelf":false}
}
```

A bootstrap Loader is discovered through the same module registry as every other LOOM capability, but the runtime imports and activates it before ordinary project modules.

## Counting
Loader itself is not included in its progress total. `total` is the number of ordinary enabled project modules discovered for the current project. A module increments `loaded` after its module file imports, its factory is created, its mount hook completes, and autostart activation completes. Import/mount/activation errors increment `failed` instead of `loaded`.

## Branding
The standard reusable Loader reads the effective configs of the standard LOOM project Logo (`core.ui.load-logo`) and Logo Text (`core.ui.load-logo-text`) modules. Because the registry already contains administrator config overrides, Loader automatically reflects current project branding without duplicating it.

Logo assets must be resolved with `ctx.resolveAssetPath`, preserving LOOM content-hash cache busting.

## Lifecycle
1. Discover manifests.
2. Import + activate the bootstrap Loader.
3. Loader displays `0 / N modules ready`.
4. Import/mount/activate ordinary modules sequentially in effective module order.
5. Update Loader after each module.
6. Loader shows the final count, respects its minimum-visible interval, fades out, and becomes inactive.

The Loader remains a Pegboard capability with normal action steps/history even though its visual overlay is transient during bootstrap.

## Ordering
Bootstrap modules exist outside the normal five-digit module card order and display `BOOT`. They do not displace the protected Header Bar `00000` project-layout slot.
## Project-shell completion placeholder

The static project-shell `Loading project modules…` placeholder is a shell fallback, not a module. Runtime readiness is authoritative: after the initial registry load, module mount/activation pass, and bootstrap Loader completion finish successfully, Action Runtime dismisses that placeholder directly. This remains compatible with older Instance Project shells whose CSS may otherwise keep the placeholder visible after the `modules-loaded` class is applied. A runtime-start failure leaves the placeholder available for the shell error message.

