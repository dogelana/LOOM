<!-- @loom-file release=0.15.15 revision=1 policy=package-priority -->
# LOOM Toast & Feedback Standard

Status: implemented in LOOM 0.15.15.

## One feedback service

LOOM-owned pages and native project modules use one shared `window.LoomToast` service for short-lived success, saved, warning, error, requirement, and informational feedback. Modules receive the same service through `ctx.toast(message, options)` and may also dispatch a `loom:toast` browser event.

The service deliberately complements, rather than replaces, persistent inline validation. Important errors remain next to the affected control; toasts confirm that a server-side operation actually applied or draw attention to a required step.

## Global vs project presentation

`loom.toast` owns global defaults such as position, duration, stack size, surface, text, and accent colors. `core.ui.toast-theme` is a project-scoped presentation provider. A project may inherit its canonical brand colors, use the LOOM default, or define custom toast colors. Project theming does not fork the toast implementation.

## Requirements and tooltips

Controls may declare `data-loom-requires` to identify a checkbox that must be checked before the action runs. LOOM blocks the action, highlights the requirement, focuses it, and displays a warning toast. `data-loom-tip` provides a shared hover/focus tooltip surface.

## Accessibility

Status toasts use polite live regions; error toasts use alert semantics. Toasts remain keyboard dismissible, mobile-safe, safe-area aware, and respect reduced-motion preferences.
