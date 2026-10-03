=== AS Local CSS ===
Contributors:
Tags: CSS, JS, javascript, custom CSS, custom JS, custom code, local css
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 0.4.6
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
