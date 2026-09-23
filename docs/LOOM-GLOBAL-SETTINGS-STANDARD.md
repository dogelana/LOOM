<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Global Settings Standard

LOOM distinguishes **global core settings** from **project module settings**.

- Global settings are declared by manifests under `core-modules/` and are edited under **Admin → LOOM Settings**.
- Project settings are declared by project module manifests and are edited under **Admin → Project Settings** after choosing a project.
- Global settings are stored in protected `data/admin/global-settings.json` and mirrored to `loom_global_settings` when SQL is initialized.
- Public LOOM surfaces may read effective non-secret global settings from `api/global-settings.php`.
- Projects must not directly own or overwrite global LOOM configuration.

Current global modules include the Animated LOOM Mark, Loader Experience, and LOOM Home Update Log.
