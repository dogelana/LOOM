# Module Control Center

Admin Project Settings exposes every discovered module in a unified enable/disable list. Global LOOM core modules, project-scoped core modules, project modules and HTML Framer modules are represented.

Manifest enablement remains the package default. Admin choices are persisted as installation state. `api/modules.php` applies project module state before emitting the runtime registry.
