# LOOM Toast & Feedback

`loom.toast` defines global presentation defaults for the shared `window.LoomToast` service. The service is available to LOOM-owned surfaces and project runtimes. Modules should prefer `ctx.toast(...)` or dispatch the `loom:toast` event instead of inventing one-off save banners.
