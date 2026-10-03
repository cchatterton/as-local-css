# Changelog

All notable changes to AS Local CSS are recorded here.

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
