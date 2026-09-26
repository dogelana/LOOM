<!-- @loom-file release=0.15.01 revision=1 policy=package-priority -->
# Showcase

`loom.showcase` is a LOOM-owned project-scoped core module. It presents a project image and summary card in the normal LOOM content flow.

By default, Showcase reads the project's effective canonical `bio` at runtime, so changing the project bio changes Showcase automatically. Admin may switch Showcase to a custom bio override and can return to the canonical project bio with one button.

Showcase images are uploaded from Admin by file picker or drag/drop. They are normalized to PNG in the browser and stored as `instance/projects/<project>/overlay/assets/showcase.png`; release ZIPs and Git never own that file.
