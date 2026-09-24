# SEO & Social Metadata Standard

LOOM 0.15.18 renders public metadata on the server so search crawlers and link-unfurl services do not have to execute the project JavaScript runtime.

## Public project defaults

Each public project automatically receives:

- the current canonical project name as the title;
- the current project bio as the description, including the live generated fallback when no custom bio exists;
- a canonical project URL;
- Open Graph metadata;
- Twitter/X card metadata;
- configurable robots behavior;
- an automatic preview image chain: Showcase image → project logo → LOOM logo.

Project identity changes therefore flow into future page responses without copying values into the SEO module.

## Preview image freshness

Project asset URLs include a content-derived cache version. Changing a Showcase image or project logo therefore creates a new preview-image URL from LOOM's perspective. External services such as Messenger may retain their own cached unfurl until their cache expires or the URL is re-scraped; LOOM cannot invalidate a third-party cache directly.

## LOOM pages

Public LOOM pages use LOOM title/description/logo metadata. Restricted administrator/developer pages use generic LOOM metadata and `noindex,nofollow`; private project or administrator data is never placed into share metadata merely because a restricted URL was shared.

## Module ownership

`core.seo.social` is a project-scoped core module. Its defaults are automatic, while Admin can choose project/custom title and description sources, image source preference, and robots behavior.
