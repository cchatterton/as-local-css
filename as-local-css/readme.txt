=== AS Local CSS ===
Contributors:
Tags: CSS, JS, javascript, custom CSS, custom JS, custom code, local css
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 0.9.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

AS Local CSS is an AlphaSys-maintained compatible release of Simple Custom CSS and JS.

== Description ==

Add custom CSS, JavaScript, and HTML snippets to a WordPress site without editing theme or plugin files.

This release preserves the original plugin's operational surface so existing sites can remove the old plugin, install AS Local CSS, and keep using the same custom code entries, options, capabilities, upload directory, and admin screens.

The interface is maintained in English only.

== Original Project Attribution ==

AS Local CSS is based on Simple Custom CSS and JS, originally authored by Diana Burduja and published by SilkyPress.

Original plugin: https://wordpress.org/plugins/custom-css-js/

Thank you to the original author and maintainers for the plugin this AlphaSys-controlled release continues from.

== Changelog ==

= 0.9.0 =
* Added a native hierarchical Code Domains taxonomy to Local CSS records, including its editor panel, list column, quick-edit support, and compact submenu.
* Seeded Theme, Block, Page, CPT, Plugin, Utility, Component, and Shortcode once, preserving later administrator changes.

= 0.8.2 =
* Removed the Related Content editor panel, relationship search and save behaviour, and related-content list column while retaining previously stored relationship metadata.
* Tightened the CSS Errors header into a compact action row with a single-line scan summary.
* Refresh CSS findings automatically whenever the CSS Errors page is opened.

= 0.8.1 =
* Removed the redundant Linking type, Cascade position, and disabled Apply network wide controls from CSS records while preserving stored cascade positions internally.

= 0.8.0 =
* Added an optional revisioned Target scope to every CSS record, wrapping Base and each populated breakpoint section independently with the required visibility declaration.
* Allowed top-level ampersand child selectors when a target supplies their generated parent while retaining the existing scanner warning for unscoped records.
* Preserved existing generated CSS exactly when Target scope is empty and kept scoped inline and compiled output identical.

= 0.7.4 =
* Widened the CSS record column and kept desktop record titles on one line, with full-title hover text and normal mobile wrapping.

= 0.7.3 =
* Shortened repetitive CSS finding descriptions and moved detailed explanations, severity guidance, and suggested fixes into the native WordPress Help panel.

= 0.7.2 =
* Refined the CSS Errors table with content-sized utility columns, a separate ID column, wider two-line descriptions, and compact Edit actions.

= 0.7.1 =
* Added a non-blocking Information finding when a CSS record's Base section contains a media query, with its first matching line and a direct link to the Base accordion.

= 0.7.0 =
* Added a backward-compatible Base CSS accordion and independent responsive CSS accordions for every configured min-width breakpoint.
* Added live per-section character counts and automatic media-query wrapping while omitting empty breakpoint sections.
* Stored breakpoint CSS in revision-enabled metadata keyed by stable row IDs so breakpoint labels and values can change without disconnecting authored CSS.
* Unified inline and generated-file output, including breakpoint-token replacement, through one CSS section assembler, and regenerate all snippet files when breakpoint settings change.
* Scan each CSS section independently and identify its section in the CSS Errors table and direct edit link.

= 0.6.13 =

* Group Add and Save together and correct the Add button alignment and sizing.

= 0.6.12 =

* Remove excess breakpoint copy and header chrome, then place concise Add and Save actions together in the card footer.

= 0.6.11 =

* Refine CSS Breakpoints with a narrower premium card, compact controls, code-styled tokens, subtle icon actions, and a clearer action hierarchy.

= 0.6.10 =

* Present CSS Breakpoints in a compact premium settings card and include the `%XL%` at `80rem` default.

= 0.6.9 =

* Present the CSS Breakpoints repeater as a responsive native WordPress post list.

= 0.6.8 =

* Complete Update 2a with default `%SM%` at `48rem`, `%MD%` at `68rem`, and `%LG%` at `75rem` breakpoint rows.

= 0.6.7 =

* Add the Update 2a CSS Breakpoints submenu and resolve repeatable token-to-length mappings in generated CSS without changing authored records or inline output.

= 0.6.6 =

* Validate the exact active CSS files used by the compiler and fall back to inline delivery when an unclosed structure could capture later records.

= 0.6.5 =

* Print generated CSS at the legacy inline-CSS hook position so the cascade remains unchanged.

= 0.6.4 =
* Add selectable top-to-bottom CSS priority ordering to both delivery modes.
* Scan published CSS records on saves and bundle refreshes without modifying authored CSS.
* Show findings in a native CSS Errors table with a submenu counter and direct edit links.

= 0.6.3 =
* Add a CSS Delivery submenu with inline legacy and generated-file choices.
* Switch CSS delivery immediately without changing JavaScript or HTML output.
* Continue maintaining generated bundles while inline mode is selected.

= 0.6.2 =
* Build CSS bundles from the same generated per-snippet files used by inline delivery.
* Refresh all bundles on plugin activation and update without a migration-version flag.
* Recover obsolete bundles automatically on the first request after upgrading.

= 0.6.1 =
* Emit combined CSS from priority 10 through priority 1 so priority 1 is last and wins the cascade.
* Preserve each snippet's author ID in generated start and end comments.
* Render generated AS Local CSS bundle link attributes with double quotes.
* Invalidate and rebuild 0.6.0 bundles whose saved order is no longer valid.

= 0.6.0 =
* Combine active CSS snippets into one content-addressed stylesheet per frontend, admin, or login context.
* Enqueue the same frontend CSS bundle in the site and block editor instead of printing inline style blocks.
* Rebuild bundles atomically after snippet changes and retain the last working bundle or inline fallback if generation fails.
* Preserve legacy header-before-footer and snippet-priority ordering inside each generated bundle.

= 0.5.0 =
* Require contextual edit capability checks before either legacy AJAX action can change a custom code item.
* Upgrade the bundled CodeMirror editor from 5.28.0 to 5.65.20.
* Make CSS and JavaScript output inline-only and migrate existing external-file snippets safely.
* Remove the content-side Related Custom Code panel and its Create CSS/Create JS actions while retaining relationships on custom code items.

= 0.4.10 =
* Enabled complete revisions for custom code, including snippet execution options.
* Rebuild generated CSS and JavaScript files plus the active snippet search tree after restoring a revision.

= 0.4.9 =
* Fixed internal HTML snippets not rendering on WordPress 7.0.
* Resolve HTML search-tree filenames to validated published snippet post IDs.

= 0.4.8 =
* Add a rendered-table fallback so the Related Content column appears on saved custom-code column layouts.

= 0.4.7 =
* Ensure the Related Content list-table column remains available when column-layout tools filter the custom code columns.

= 0.4.6 =
* Added a Related Content column to the custom code list table with same-tab editor links to each related content item.

= 0.4.5 =
* Load related-content metabox registration from the top-level admin bootstrap so it is not skipped by legacy class guards.
* Register the custom code relationship metabox on the post-type-specific metabox hook and move relationship boxes higher in the sidebar.
* Keep the Related Content metabox in the original custom code editor's allowed side metabox list.

= 0.4.4 =
* Added bidirectional related content and custom code meta boxes.
* Added searchable repeaters for linking custom code to editable content from supported post types.
* Added new-tab edit links for related content and related custom code.
* Added content-side Create CSS and Create JS actions that create draft custom code and preconfigure the relationship.

= 0.4.3 =
* Move release discovery to AS Update Controller with local Install/Activate/Check actions.
* Align AlphaSys author metadata, PHP 7.4 / WordPress 7.0 requirements and distribution licensing.
* Preserve existing snippets, generated files, editor behaviour and settings.

= 0.4.2 =
* Promoted Local CSS after Gutenberg's native block styles inside the editor canvas.
* Preserved authored selectors and declarations without adding !important or rewriting CSS.

= 0.4.1 =
* Added a repository update manifest so update checks do not depend on GitHub API quota.
* Removed the unreliable HTTP HEAD probe from the public-release fallback.

= 0.4.0 =
* Added front-end enqueued stylesheets to the block editor iframe before Local CSS.
* Preserved front-end CSS variables and dependencies without translating them into theme.json.

= 0.3.2 =
* Preserved Local CSS selectors and custom properties unchanged inside the block editor canvas.
* Fixed the manual GitHub update check so its refreshed result is persisted in WordPress.
* Added clear update-available, up-to-date, and failed-check notices on the Plugins screen.

= 0.3.1 =
* Fixed CodeMirror initialisation on Local CSS, JavaScript, and HTML editing screens.
* Stopped unregistering unrelated plugins' admin scripts on code editing screens.
* Made Local CSS loading in the block editor deterministic across editor configurations.

= 0.3.0 =
* Added front-end WordPress body classes to the block editor content canvas.
* Preserved editor classes and reapplied front-end classes after iframe reloads.

= 0.2.0 =
* Added active front-end CSS to the block editor content iframe.
* Preserved CSS location and priority ordering in the editor.
* Aligned GitHub update delivery with the current Codex WordPress plugin standards.

= 0.1.0 =
* Created the AlphaSys-controlled compatible release.
* Preserved the original plugin behavior and saved-data compatibility.
* Added GitHub release update support from cchatterton/as-local-css.
* Removed bundled translations so the maintained interface is English only.
