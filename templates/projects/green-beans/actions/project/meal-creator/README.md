<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# Green Beans Meal Creator

Project-specific Green Beans LOOM module for composing named meals from Shopping List ingredients.

## Behavior

- Choose existing Shopping List ingredients with checkboxes.
- Type additional ingredients separated by commas or line breaks; missing ingredients are automatically created in Shopping List.
- Every meal creates a synchronized **meal group** in Shopping List.
- Renaming or changing a meal immediately renames/changes its Shopping List group.
- Removing a meal removes only the meal grouping; ingredient items remain on Shopping List.
- One Shopping List ingredient can belong to more than one meal.
- Shopping List detects this module and adds **Add as Meal** plus the **Shift + Enter** quick-create shortcut.
- Removing an ingredient from Shopping List removes its reference from all meals.

Meal Creator owns meal records in `green-beans.meal-creator` project state. Shopping List owns ingredient records and meal-group projection in `green-beans.shopping-list`. The modules communicate through LOOM runtime extensions (`green-beans.meal-creator` and `green-beans.shopping-list`) rather than hard-coded DOM coupling.
