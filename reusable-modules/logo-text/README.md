<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# LOOM reusable module: logo-text

This module follows the v0.11.04 presentation contract. Logo Text treats Admin-selected font size as authoritative and only shrinks as an emergency overflow safeguard. Header Bar grows naturally around its injected branding modules.

## Stable responsive fitting (0.12.05)

Wordmark fitting uses the stable containing block instead of the header's own changing width. The module observes only containing-width changes, preventing resize feedback loops and rapid flashing.
