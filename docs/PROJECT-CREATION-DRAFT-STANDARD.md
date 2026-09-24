<!-- @loom-file release=0.15.15 revision=1 policy=package-priority -->
# Project Creation Draft Standard

Status: implemented in LOOM 0.15.15.

A project does not exist in the Instance Vault until Admin presses **Create Project** and the server confirms creation. Before that point, the Create Project form is transient browser state.

LOOM therefore stores unfinished creation forms in `localStorage` under a versioned per-browser/client key. Changes are debounce-saved after roughly 420 ms and synchronously saved on close/pagehide. Returning to Create Project restores the draft and clearly marks it as recovered.

The draft includes project name, slug, tagline, description, bio, project version, Theme Preset, primary/accent colors, and whether the slug was manually edited. Browser file inputs are intentionally not persisted; Admin must re-select a logo file after restoring a draft.

The draft is deleted only after successful project creation or explicit **Start Fresh**. No pre-project draft is written into `instance/**`, because no project ownership boundary exists yet.
