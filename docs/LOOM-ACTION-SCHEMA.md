<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Action Schema Standard — v1.1

## Purpose

Every LOOM module exposes two related layers:

- **module/system action** — the capability itself and its runtime lifecycle;
- **semantic user actions** — meaningful things a person can intentionally do through that module.

This split keeps the Pegboard readable while preserving deep per-action telemetry.

## Manifest shape

```json
{
  "schema_version": "1.1",
  "action": {
    "id": "shopping.list.load",
    "name": "Shopping List",
    "kind": "system",
    "behavior": "stateful",
    "parent": "core.load"
  },
  "user_actions": [
    {
      "id": "shopping.item.add",
      "name": "Add Shopping Item",
      "description": "Add a new shopping ingredient.",
      "behavior": "transient",
      "events": ["shopping.item.added"]
    }
  ],
  "module": {
    "entry": "action.js",
    "version": "4.0.1",
    "order": "00010"
  }
}
```

## IDs

User-action IDs must be stable, globally unique inside a project, lowercase, and dot-delimited where useful. Prefer a semantic object/verb form such as:

`shopping.item.add`

`recipes.recipe.edit`

`consumption.item.toggle`

Do not encode UI implementation details such as button numbers or DOM selectors into IDs.

## Behaviors

- `transient` — one discrete operation; emits `active` then `completed` or `failed`.
- `pending` — stays active while a user workflow is waiting for completion/cancellation.
- `stateful` — remains held until explicitly released; use sparingly for user-owned states.

## Standard event envelope

User actions use the same `action.state` event family as system actions and add:

- `kind: "user"`
- `actor: "user"`
- `moduleActionId`
- `parent`
- `behavior`
- `runtimeId`, `clientId`, `sessionId`, timestamps and normal LOOM identity fields
- optional `domainEvent` when bridged from a module domain log

This lets user and system actions share session history, analytics, error handling, and replay infrastructure.

## Two emission methods

### 1. Domain-event bridge

If an existing module already logs a semantic domain event, declare it in `user_actions[].events`.

```json
{
  "id": "meals.meal.create",
  "name": "Create Meal",
  "events": ["meal.created"]
}
```

When the module calls:

```js
await ctx.log('meal.created', { mealId });
```

LOOM automatically emits the matching user-action `active` and `completed` events around that domain log.

### 2. Direct user action

Use this for meaningful user interactions without a domain mutation:

```js
await ctx.userAction('scheduling.calendar.navigate', {
  direction: 'next'
});
```

For asynchronous work:

```js
await ctx.runUserAction('explore.data.refresh', async () => {
  await refreshExternalData();
});
```

Pending workflows may use:

```js
await ctx.beginUserAction(id, detail);
await ctx.completeUserAction(id, detail);
await ctx.failUserAction(id, message, detail);
```

## Privacy / noise rule

LOOM tracks **meaningful semantic actions**, not raw keystrokes, pointer movement, hover streams, or every character typed. Raw interaction telemetry is noisy, invasive, and not useful for the intended action graph.
