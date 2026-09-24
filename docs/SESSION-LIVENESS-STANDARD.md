<!-- @loom-file release=0.15.12 revision=1 policy=package-priority -->
# LOOM Session Liveness Standard

Status: implemented in LOOM 0.15.12.

## Goal

A LOOM session must not permanently stop reporting activity merely because a browser emits a transient lifecycle event, throttles timers, restores a history entry, or briefly loses network connectivity.

## Heartbeats

Active project runtimes maintain a server lease using self-scheduling heartbeats. A successful heartbeat schedules the next normal heartbeat. A failed heartbeat schedules a shorter retry. Visibility restoration, window focus, network restoration, user interaction, and `pageshow` all force a liveness check.

The default heartbeat interval remains 5 seconds. The presence lease is 90 seconds, providing substantially more tolerance for mobile-browser timer throttling without making truly abandoned sessions permanent.

## Page lifecycle

`pagehide` is not treated as proof that the JavaScript document can never be used again. LOOM may send a graceful server close for a non-BFCache pagehide, but the local runtime is not destroyed solely because that event fired.

If the same document becomes usable again, LOOM generates a new runtime ID inside the same logical session, restarts interaction capture, and immediately re-establishes presence. The server records this as `session.resumed` with reason `runtime-reopened` when appropriate.

Explicit runtime shutdown still performs a full teardown.

## Tracking continuity

Interaction capture and native user-action tracking are restarted after lifecycle recovery so Pegboard and Activity Explorer do not silently stop receiving events from an otherwise usable tab.

## Failure behavior

Heartbeat transport failures do not stop the runtime. They are retried. A stale server lease remains recoverable; a later heartbeat returns the logical session to active state.
