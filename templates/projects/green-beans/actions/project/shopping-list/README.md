<!-- @loom-file release=0.12.08 revision=3 policy=package-priority -->
# Green Beans Shopping List

Standalone Green Beans project module built on LOOM.

Capabilities: rapid single/comma-separated ingredient entry, Enter-to-add, edit/remove, completion, manual named groups, multi-select grouping, and first-class Meal Creator integration.

When `project.meal-creator` is installed, Shopping List automatically exposes **Add as Meal** and the **Shift + Enter** shortcut. Meal-backed groups are separate from manual groups: they can overlap, one ingredient can belong to multiple meals, editing a meal immediately changes its Shopping List grouping, and deleting a meal removes only the meal grouping while preserving the ingredient items.

The module exposes the `green-beans.shopping-list` extension contract to compatible project modules. Persistence uses LOOM `api/project-state.php` and remains per project / per LOOM user identity.
