# LOOM Clean Instance Protocol

Starting with LOOM 0.12.13, this is a hard architectural invariant.

## Release-owned
Application code, schemas, templates, default project definitions, default static assets, and `instance.sample/`.

## Installation-owned
Everything under `instance/`.

The real `instance/` directory is never included in a LOOM release ZIP and is never synchronized by the LOOM Bridge. Its absence from a package never means deletion.

## Storage rule
Any new mutable filesystem datum must be stored beneath `instance/`. If a feature uses SQL, filesystem fallback/durability still belongs beneath `instance/`.

## Project rule
`projects/<slug>/project.default.json` contains package defaults.
Installation-specific project metadata belongs in `instance/projects/<slug>/project-overrides.json`.
Installation-specific project assets belong in `instance/projects/<slug>/overlay/`.

## Hot-drop rule
A standard in-place archive extraction that overwrites matching release files is safe for Instance Vault data because the archive contains no `instance/**` members.

A deployment mechanism that deletes the destination directory first, mirror-deletes files absent from the archive, or manually removes `instance/` is destructive and is not a LOOM hot drop.

## Fail-loud rule
If `instance/` cannot be created or written, LOOM fails explicitly. It must never silently fall back to writing mutable state into release-managed paths.
