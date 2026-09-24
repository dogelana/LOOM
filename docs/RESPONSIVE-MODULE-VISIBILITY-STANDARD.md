<!-- @loom-file release=0.15.09 revision=1 policy=package-priority -->
# LOOM Responsive Module Visibility Standard

Status: implemented in LOOM 0.15.09.

## Purpose

LOOM can keep a project module enabled while suppressing that module at mobile viewport widths. This is different from disabling the module: desktop/tablet-width project sessions still load and run it, while mobile-width sessions do not import, mount, activate, or expose that module's user actions.

## Admin contract

Project-scoped rows in **Admin → Project Settings → Module Control Center** expose two independent controls:

- **Enabled** — whether the module is allowed to run at all.
- **Hide on mobile** — whether the enabled module is suppressed when the project viewport is 767 CSS pixels wide or narrower.

`Hide on mobile` is project-scoped and persists in the Instance Vault alongside the module's enable state. Changing the enable switch must never erase the responsive visibility choice, and changing responsive visibility must never change the enable switch.

## Runtime contract

Responsive visibility is evaluated in the browser with `matchMedia('(max-width: 767px)')`; LOOM intentionally does not rely on User-Agent sniffing. When a viewport crosses that boundary, Action Runtime reconciles the registry again. Modules becoming hidden are cleanly deactivated/unmounted; modules becoming eligible are loaded normally.

The effective value is exposed to runtime descriptors as:

```json
{
  "presentation": {
    "responsive": {
      "hideOnMobile": true
    }
  }
}
```

A manifest value is only the default. An Admin's Instance Vault state overrides it per project.

## Background Orbs default

Background Orbs are **enabled by default** again in 0.15.09. Both the core Background Orbs module and project-specific orb providers ship with `presentation.responsive.hideOnMobile = true`, because the effect is visually useful on larger project layouts but has proven problematic on narrow mobile surfaces.

No other module ships with Hide on mobile enabled by default in 0.15.09.

## Safety and fallback

Static fallback registries carry manifest defaults, so Background Orbs stay suppressed on mobile even if the live registry endpoint is temporarily unavailable. Admin overrides require the live server registry, as expected for Instance Vault state.
