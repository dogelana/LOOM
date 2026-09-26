<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM Profile Avatar Extension Standard — v0.11.09

LOOM User Profile owns the universal avatar system: the native default silhouette, upload UI, browser-side resize/compression, server persistence, and permanent-account migration.

Projects may optionally provide `core.user.profile.avatar` through a module instance `extensions` object. A provider may expose:

- `label` — project name used in profile controls.
- `getDefaultAvatarUrl()` — project default avatar. When the user has never made an explicit avatar choice, this overrides the native LOOM default inside that project.
- `openCreator({host,onApply,onCancel})` — optional project avatar creation UI. `onApply(blob)` hands the generated image back to LOOM, which persists it exactly like an upload.
- `userActionId` — optional semantic action owned by the project extension for creator telemetry.

The native controls always remain available. A user can switch to LOOM Default, upload an image, select Project Default when available, or use the project creator when available.

## Storage

User-selected uploads are center-cropped to 384×384 and encoded as WebP at approximately 78% quality in the browser (PNG fallback where needed). The compressed image is stored as a protected server file. SQL stores permanent-account avatar preference metadata rather than binary image blobs, avoiding database bloat. Pre-account client avatars are promoted to the permanent user identity when an account is created or linked.

## v0.11.20 global/profile split
A permanent LOOM account has a LOOM-wide profile (username + profile picture). Each project identity independently chooses to inherit each global value or use a project-specific override. Account email/password/User ID/privilege remain global.

## v0.11.20 — server-resolvable defaults and LOOM-profile reuse
Project avatar providers may declare `extensions.core.user.profile.avatar.default_asset` as a path relative to the provider module folder. This lets LOOM resolve a project's default profile picture outside the live project UI, including the **Pull Picture from Project** flow in the global LOOM profile.

A user's project picture can be copied into the global LOOM profile without changing the project identity. The resulting global image remains independently editable afterward.
