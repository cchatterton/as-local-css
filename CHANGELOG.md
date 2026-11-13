# Changelog

All notable changes to AS Local CSS are recorded here.

## 0.6.4 - 2026-11-13

- Added selectable top-to-bottom CSS priority ordering for both inline and generated-file delivery.
- Scanned all published CSS records whenever snippets are saved or bundles are refreshed by activation, update, file-mode selection, or manual request.
- Added a native CSS Errors table with severity, line, description, direct record edit links, and a submenu counter.
- Recognised project breakpoint tokens such as `%MD%` without false-positive scan findings and never modified CSS automatically.
- Invalidated older bundle manifests so upgrading from 0.6.3 performs a first-request rebuild and scan.

## 0.6.3 - 2026-11-13

- Added a CSS Delivery submenu with inline legacy and generated-file delivery choices.
- Restored the original per-record inline rendering path when inline mode is selected.
- Kept JavaScript and HTML delivery unchanged and continued rebuilding bundles so file mode remains ready to select.

## 0.6.2 - 2026-11-13

- Compiled bundles from the generated per-snippet CSS files used by inline delivery instead of potentially stale database content.
- Rebuilt every CSS bundle unconditionally when the plugin is activated or updated.
- Removed the one-time CSS bundle migration-version flag and added schema-based first-request recovery for upgrades from older releases.
- Added regression coverage proving corrected generated-file CSS overrides a divergent database copy and reaches refreshed bundles.

## 0.6.1 - 2026-11-13

- Corrected combined stylesheet ordering so priority 10 is emitted first and priority 1 is emitted last, allowing the documented highest priority to win the cascade.
- Preserved each snippet's author ID in generated start and end boundary comments.
- Rendered AS Local CSS bundle link attributes with double quotes without changing other WordPress stylesheet tags.
- Applied the same priority order to inline and block-editor fallback delivery.
- Invalidated 0.6.0 bundle manifests and scheduled a one-time rebuild so incorrectly ordered generated files are not reused.

## 0.6.0 - 2026-11-13

- Combined active CSS snippets into one generated stylesheet for each frontend, WordPress admin, or login context.
- Replaced inline frontend CSS output with a content-addressed, cache-safe stylesheet enqueued through WordPress.
- Loaded the same frontend bundle in the block editor iframe and retained its final cascade authority without duplicate stylesheet nodes.
- Preserved legacy header-before-footer grouping and numeric snippet-priority ordering inside each bundle.
- Added atomic file publication, cleanup of superseded bundles, and a last-known-good bundle or inline fallback when rebuilding fails.
- Rebuilt bundles after snippet saves, activation changes, trash/restore actions, and revision restores, with a one-time upgrade build for existing sites.
- Relabelled the CSS header/footer control as an earlier/later cascade position because the combined stylesheet is enqueued in the document head.
- Added disposable WordPress integration coverage and live frontend/block-editor verification for generated-file delivery and cache invalidation.

## 0.5.0 - 2026-11-05

- Added contextual `edit_post` capability checks to both legacy AJAX handlers before they can change a custom code item.
- Upgraded the bundled CodeMirror editor from 5.28.0 to 5.65.20 and moved the editor to a locally bundled minified asset set.
- Disabled the legacy external-file selector and permalink editor so new and edited CSS and JavaScript snippets always render inline.
- Added a one-time migration that converts existing external CSS and JavaScript snippets to inline output and rebuilds their generated assets and search tree.
- Removed the content-side Related Custom Code panel and its Create CSS/Create JS actions while retaining relationship management on custom code items.
- Added a backlog-candidates file for the recommended metadata-query and output-escaping follow-up work.
- Added WordPress integration coverage for inline migration, editor assets, relationship UI removal, and AJAX authorization.

## 0.4.10 - 2026-11-05

- Enabled WordPress revisions for the `custom-css-js` post type's snippet code and execution options.
- Registered the `options` post meta as revisioned so language, location, linking mode, side, and priority restore with the code.
- Rebuilt generated CSS and JavaScript files and the active snippet search tree after a revision is restored.
- Added regression coverage for revision registration and regenerated CSS output.

## 0.4.9 - 2026-11-05

- Fixed internal HTML snippets not rendering on WordPress 7.0 because filename-shaped search-tree references such as `123.html` were passed directly to `get_post()`.
- Resolved and validated HTML snippet post IDs consistently for internal, external, and both linking modes.
- Ignored stale or invalid HTML references instead of rendering the current page or raising a null-post warning.

## 0.4.8 - 2026-10-03

- Add a rendered-table fallback so the Related Content column appears on saved custom-code column layouts.

## 0.4.7 - 2026-10-03

- Ensure the Related Content list-table column remains available when column-layout tools filter the custom code columns.

## 0.4.6 - 2026-10-03

- Added a Related Content column to the custom code list table with same-tab editor links to each related content item.

## 0.4.5 - 2026-10-03

- Load related-content metabox registration from the top-level admin bootstrap so it is not skipped by legacy class guards.
- Register the custom code relationship metabox on the post-type-specific metabox hook and move relationship boxes higher in the sidebar.
- Keep the Related Content metabox in the original custom code editor's allowed side metabox list.

## 0.4.4 - 2026-10-03

- Added bidirectional related content and custom code meta boxes.
- Added searchable repeaters for linking custom code to editable content from supported post types.
- Added new-tab edit links for related content and related custom code.
- Added content-side Create CSS and Create JS actions that create draft custom code and preconfigure the relationship.

## 0.4.3 - 2026-09-26

- Replace the independent updater with AS Update Controller API 1 and guarded local Install/Activate/Check actions.
- Standardise AlphaSys author metadata and declare WordPress 7.0 / PHP 7.4 compatibility.
- Include the GPL-3.0 license while retaining upstream attribution and third-party notices.
- Preserve existing snippets, options, upload paths, capabilities and front-end/block-editor behaviour.

## 0.4.2 - 2026-08-23

- Promoted Local CSS stylesheet nodes after Gutenberg's native block styles inside iframe and non-iframe editor canvases.
- Preserved authored selectors and declarations unchanged instead of adding `!important` or rewriting CSS.
- Reapplied final cascade order when Gutenberg dynamically adds further native styles.

## 0.4.1 - 2026-08-23

- Added a repository-controlled update manifest so WordPress can discover releases when the GitHub API returns a shared-host rate-limit or forbidden response.
- Removed the release-asset `HEAD` request from the public redirect fallback because signed GitHub asset URLs can reject `HEAD` while accepting the updater's normal `GET` download.

## 0.4.0 - 2026-08-23

- Mirrored stylesheets registered through WordPress's front-end enqueue hook into the block editor iframe.
- Kept front-end JavaScript out of the editor while collecting its stylesheet dependencies.
- Loaded mirrored front-end styles before Local CSS so custom properties and cascade dependencies resolve as they do on the front end.

## 0.3.2 - 2026-08-23

- Restored raw Local CSS loading in the editor canvas so Gutenberg cannot rewrite `:root`, `html`, `body`, or front-end body-class selectors.
- Persisted the plugin's GitHub update entry after WordPress rebuilds its native plugin update transient.
- Recognised the plugin-row manual check as a forced release lookup.
- Added explicit Plugins-screen feedback when an update is available, the plugin is current, or GitHub cannot be reached.

## 0.3.1 - 2026-08-23

- Fixed the Local CSS/JS/HTML editing screen failing to initialise CodeMirror after the plugin folder was renamed to `as-local-css`.
- Stopped unregistering unrelated plugins' admin scripts on Local CSS editing screens.
- Declared CodeMirror and the tooltip library as explicit dependencies of the plugin's admin script.
- Added Local CSS directly to WordPress's block editor settings for reliable iframe and non-iframe loading.

## 0.3.0 - 2026-08-23

- Added the edited post's front-end WordPress body classes to the block editor iframe.
- Preserved Gutenberg's existing editor body classes while adding singular, template, post, page, and active-theme selector context.
- Reapplied front-end classes when the editor iframe reloads and supported the non-iframe editor fallback.

## 0.2.0 - 2026-08-23

- Added active front-end CSS snippets to the block editor content iframe so editor content matches the site front end.
- Preserved front-end header/footer and priority ordering for editor CSS, including internal and external snippets.
- Excluded admin-only and login-only CSS from the editor content canvas.
- Aligned the plugin header and GitHub updater with the current Codex WordPress plugin standards.
- Added native Plugins-screen GitHub and "Check for updates" links, forced-check cache bypassing, short no-update caching, and a verified public-release fallback.

## 0.1.0 - 2026-06-15

- Created the AlphaSys-controlled compatible release as `as-local-css`.
- Preserved the original plugin's custom post type, screens, options, capabilities, upload path, AJAX actions, CodeMirror editor assets, and front-end rendering behavior.
- Added GitHub release updater support for `cchatterton/as-local-css`.
- Removed bundled translations so the maintained interface is English only.
- Added attribution to the original Simple Custom CSS and JS plugin, Diana Burduja, and SilkyPress.
