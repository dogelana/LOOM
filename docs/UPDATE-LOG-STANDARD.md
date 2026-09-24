<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM / Project Update Log Standard

## LOOM platform history
The single root `CHANGELOG.md` belongs to LOOM itself and is rendered on **LOOM Home**. It must never be mounted as project content.

## Project history
A project may maintain `projects/<slug>/CHANGELOG.md`. A project may install the reusable `project.system.update-log` module to render that file inside the project.

Project update logs must request `api/changelog.php?scope=project&project=<slug>`. LOOM Home requests `scope=loom`.

## Legacy protection
LOOM module discovery ignores the retired `core.system.update-log` / legacy LOOM-update-log action IDs if old deployment folders remain on disk. This prevents stale platform release-history modules from reappearing inside projects after an overwrite-style deployment.
