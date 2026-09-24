<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM IP Metadata Standard — v0.11.20

LOOM records server-observed IP addresses as network metadata. It prefers a valid public IPv4 when trusted proxy headers expose one; otherwise it records the canonical IPv6 address actually observed. The UI displays the complete canonical address and labels its version instead of truncating it.

Each identity keeps first-seen, last-seen, observation count, source header, and project context. Admin can inspect the same information in LOOM Admin → Users.

IP is not an authentication credential, not an identity key, and not a ban mechanism. Shared/mobile/carrier-NAT/VPN/dynamic addresses are network observations, not people.
