# LOOM Core · Footer Bar

`core.ui.footer-bar` is release-managed and project-scoped. Existing Instance Projects inherit footer fixes without LOOM writing into their persistent project directories. The More Tools row starts hidden and is only exposed when Orb Dock confirms that at least one captured tool exists.

## Project branding inheritance (0.15.14)

Footer text no longer reads from the Header Wordmark module and never falls back to a raw project slug. By default it renders the canonical Project Identity wordmark, colors, and font. Admin may independently set Footer project text to Project Identity, Custom footer wording, or Hidden (logo only). Colors and font can also inherit or be overridden locally.

