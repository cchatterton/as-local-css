# Changelog

All notable changes to AS Local CSS are recorded here.

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
