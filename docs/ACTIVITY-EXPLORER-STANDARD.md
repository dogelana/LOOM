<!-- @loom-file release=0.15.10 revision=1 policy=package-priority -->
# LOOM Activity Explorer Standard

Status: implemented in LOOM 0.15.10.

## Purpose

Pegboard is LOOM's live operational/debug view. **Admin → Activity Explorer** is the durable historical investigation surface.

The explorer combines two existing durable telemetry streams:

1. semantic project/runtime events stored by `log.php`, including native and HTML Framer user actions;
2. privacy-scrubbed interaction capture stored by `replay.php`, including pointer/tap targets, scroll, navigation keys and non-sensitive field interactions.

## Filters

Administrators can filter by project, permanent user or guest/client identity, session, date range, activity category, arbitrary search text and result limit.

Categories include user actions, interaction capture, framed HTML, sessions, module/runtime events and errors.

Registered users include activity from client IDs currently linked to that permanent account. Client/guest subjects can also be inspected directly.

## Privacy

Activity Explorer is server-gated to Administrators.

Sensitive input capture remains governed by the existing replay scrubber and interaction-capture classifier. Passwords, tokens, authentication material and elements marked private/sensitive are masked or omitted. Framed Action Reader does not transmit typed text values.

## Storage

Activity Explorer is a read-only view over existing Instance Vault / SQL-backed observability. It does not create a parallel activity database and does not change retention semantics.
