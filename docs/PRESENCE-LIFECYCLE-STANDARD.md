<!-- @loom-file release=0.15.49 revision=3 policy=package-priority -->
# LOOM Presence + Lifecycle Standard — v0.8.1

## Core rule

**Heartbeat freshness is not session lifecycle.**

A missed heartbeat can tell LOOM only that the runtime has not checked in recently. It cannot prove that the tab closed, the user left, or the mounted actions were unloaded.

LOOM therefore uses these presence states:

- `live` — a recent heartbeat exists;
- `stale` — heartbeat freshness lapsed, but the session is retained and resumable;
- `closed` — an explicit lifecycle close was received;
- `historical` — a non-live historical record;
- `expired` — legacy v0.8 compatibility only; new v0.8.1 runtimes never create this state from heartbeat timeout.

## Heartbeats

The runtime sends a periodic heartbeat containing the last-known held stateful/pending action snapshot. v0.8.1 also sends a throttled immediate heartbeat when meaningful browser activity indicates the user/runtime is alive:

- pointer down;
- keyboard input;
- form input/change;
- touch start;
- focus;
- visibility transition;
- network `online` event.

These activity heartbeats are transport/liveness signals. They are **not** semantic user-action telemetry and must not create Pegboard user-action pills.

Default timing:

- heartbeat interval: 5 seconds;
- freshness lease: 45 seconds;
- activity-heartbeat throttle: 1 second.

## Stale transition

When the freshness lease lapses, the server emits one `session.stale` event and changes presence to `stale`.

It MUST NOT:

- emit `session.end`;
- emit inferred `action.state = inactive` events;
- clear the last-known held-action snapshot;
- convert the session into history;
- create a new session ID merely because a heartbeat was missed.

The Pegboard renders stale state separately (amber) and labels it resumable.

## Resume

The next valid heartbeat for a stale session changes presence back to live and emits `session.resumed`. The same session ID and history continue.

A stale → live transition is normal and is not an error.

## Real session termination

A session ends only through an explicit lifecycle close in the current standard. The normal browser fast-path is `pagehide` with `navigator.sendBeacon()` where available. Explicit runtime shutdown uses the same lifecycle endpoint.

On real close LOOM may:

1. emit final `action.state = inactive` events for held stateful/pending actions;
2. emit `session.end`;
3. clear the held-action snapshot;
4. mark presence `closed`.

Future abandonment policies, if added, must be separately named/configured and must not reuse heartbeat freshness as termination proof.

## Pegboard requirements

Pegboard must visually distinguish `live`, `stale`, and `closed`.

For a stale session:

- keep the selected session selectable and retained;
- preserve the last-known held-action snapshot;
- render held state as stale/uncertain, not inactive;
- show heartbeat freshness separately from session-end reason;
- immediately return to live when a heartbeat resumes.

This standard applies to every LOOM project.
