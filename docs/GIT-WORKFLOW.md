# LOOM Git Workflow

LOOM 0.15.07 is Git-compatible by design. Git owns the replaceable release tree. The Instance Vault remains outside source control.

## Repository boundary

Commit the LOOM application tree, including `core-modules`, `engine`, `api`, release-managed `projects`, templates, docs, and `.loom-deployment.json`.

Never commit real `instance/**`. That directory contains installation identities, settings, database configuration, uploaded HTML Framer packages, user-created projects, custom assets, and other mutable installation state. `instance.sample/**` is safe to commit.

## Recommended deployment modes

1. GitHub as source control + LOOM Bridge as deployment transport. This works on ordinary FTP/SFTP hosting and keeps LOOM's release-policy protections.
2. Host-managed Git deployment when the host can deploy a repository directly. The host must preserve `instance/**`; do not use a destructive clean/mirror rule that deletes untracked server state.

## User-created projects

Projects created from LOOM Home are Instance Projects. They live under `instance/projects/<slug>/project/` and are therefore intentionally absent from Git release commits and future LOOM ZIPs. Their runtime is served through LOOM's project gateway.

Use the Bridge Vault Backup feature to pull a non-destructive local backup of Instance Projects when desired.
