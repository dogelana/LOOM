<!-- @loom-file release=0.15.05 revision=2 policy=package-priority -->
# LOOM Responsive UI Standard

## Baseline

All primary LOOM HTML surfaces must include `meta name="viewport" content="width=device-width,initial-scale=1"`. Runtime/module containers must use `min-width:0`, remain at or below the viewport width, and allow long labels/content to wrap or truncate rather than force horizontal page overflow.

## Project shells

At phone widths the project identity and shell actions may wrap onto separate rows. Public and Admin controls must remain reachable without horizontal scrolling. Main module stage/footer padding contracts at narrow widths.

## Collapsible modules

The LOOM frame owns the top edge while expanded. The frame body and first module content node are width-constrained. A module with its own bordered card should remove its own top border/radius while inside an expanded frame so it becomes one visual object.

## Touch targets

Frequently used mobile controls should target roughly 40–44 CSS px in at least one dimension where practical. Small desktop-only decorative controls should not cause horizontal overflow.

## Validation

Release validation checks viewport declarations and audits the narrow-screen CSS contracts for LOOM Home, Admin setup, Admin, project shells, module frames, Showcase, and footer modules. Representative surfaces must remain width-constrained and must not introduce known fixed minimum widths that force document-level horizontal overflow.
