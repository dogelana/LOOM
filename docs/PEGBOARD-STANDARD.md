<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Pegboard Layout Standard

Version: 0.11.32

Pegboard is a LOOM-owned full-screen tool composed as:

```text
Shared LOOM Header
Bounded Pegboard Workspace
Shared LOOM Footer
```

The graph viewport, target panel, detail panel, runtime badge, legend, and event history panel must all be positioned relative to the bounded workspace.

Pegboard must not use `position: fixed; inset: 0` for its application workspace because that would cover or detach the shared header/footer.

Page-specific Pegboard controls belong in the shared shell header toolbar.

The graph fit operation must use `viewport.clientWidth` and `viewport.clientHeight`, not raw browser `innerWidth`/`innerHeight`.
