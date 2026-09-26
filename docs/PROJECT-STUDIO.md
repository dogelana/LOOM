# Project Studio

LOOM Home is now a project factory, not only a project launcher. An authorized Admin can create a baseline project directly from Home.

Created projects are Instance Projects stored beneath `instance/projects/<slug>/project/`. The package-owned generic shell at `projects/_instance/app/` runs them without exposing the Instance Vault directly.

This is the key hot-drop invariant: a release archive can replace the application tree without containing or overwriting an installation-created project's runtime files.
