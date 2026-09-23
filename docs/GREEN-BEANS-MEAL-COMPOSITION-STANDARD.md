<!-- @loom-file release=0.12.08 revision=1 policy=package-priority -->
# Green Beans Meal Composition Standard

LOOM 0.12.07 introduces a project-specific teammate relationship between Shopping List and Meal Creator.

## Ownership

`project.shopping-list` owns ingredient items, completion state, manual groups, and the projected meal-group membership attached to ingredients.

`project.meal-creator` owns canonical meal records: meal ID, meal name, and Shopping List ingredient IDs.

Neither module reaches into the other's DOM. Runtime cooperation occurs through LOOM extension providers:

- `green-beans.shopping-list`
- `green-beans.meal-creator`

## Meal projection

A meal is projected into Shopping List as a group with `kind: "meal"` and the meal ID. Shopping items keep a separate `mealIds[]` membership collection, so meal grouping does not destroy manual group assignment and the same ingredient can participate in multiple meals.

Deleting a meal removes its group and its ID from ingredient `mealIds[]`; ingredient items remain intact. Editing a meal reprojects its name and ingredient membership.

## Conditional composition UI

Shopping List discovers `project.meal-creator` through the runtime registry. Only when that module is present does Shopping List expose **Add as Meal** and the **Shift + Enter** quick-create shortcut. The button resolves the Meal Creator runtime extension dynamically, so Shopping List still functions independently when Meal Creator is absent or disabled.

## Persistence

Both modules use LOOM `api/project-state.php`, inheriting normal project/user ownership, permanence migration, moderation, and database behavior. Meal Creator's state key is `green-beans.meal-creator`; Shopping List remains `green-beans.shopping-list` with state schema version 2.
