<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# User Action Module Pattern

User-action modules are discovered exactly like system-action modules, but `kind` is `user` and `autostart` is normally false.

Loading the module does **not** light its Pegboard bulb. The bulb exists because the capability exists. The module's UI calls `ctx.run(...)` for a transient action, or `ctx.begin()/ctx.complete()` for a pending action. This keeps "code is installed" separate from "the user is doing the action".
