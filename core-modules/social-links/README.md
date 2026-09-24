<!-- @loom-file release=0.15.04 revision=1 policy=package-priority -->
# Social Links

`loom.social-links` is a project-scoped LOOM core module. It injects a compact social-link row into the standard project footer directly after project branding and before More Tools.

All URLs are optional; a blank configuration renders no icon. Facebook, Instagram, YouTube and TikTok use their Font Awesome Free Brands vector icons. Website uses the Font Awesome Free solid globe. The optional Custom Link can choose from a curated matching icon set from Font Awesome Free.

All icons share one CSS color. Admin may choose the project's default social color, the LOOM black default, or a manual color. The project's default is part of Project Identity and persists through the Instance Vault override layer when changed.

The bundled icon data is SVG path geometry only and is rendered with `currentColor`, so icons remain transparent, crisp and resolution-independent.
