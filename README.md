# AS Local CSS

Author: AlphaSys
Version: 0.6.0
Status: Production

## Purpose

AS Local CSS is an AlphaSys-maintained, compatible replacement for the WordPress plugin "Simple Custom CSS and JS".

## Key Features

- Custom code post type remains `custom-css-js`.
- Existing options, post meta, capabilities, upload folder, AJAX actions, and editor screens are preserved.
- Active CSS snippets are compiled into one content-addressed stylesheet per frontend, admin, or login context; JavaScript remains inline.
- CSS bundles are written atomically, retain legacy snippet ordering, and keep the last working bundle or inline fallback if rebuilding fails.
- Active front-end CSS snippets load in the block editor content iframe as well as on the site front end.
- Stylesheets registered through WordPress's front-end enqueue hook load in the editor iframe before Local CSS, preserving design-token dependencies.
- Local CSS is promoted after Gutenberg's native block styles in the editor canvas so authored rules retain front-end cascade authority.
- The block editor content canvas receives the edited post's front-end WordPress body classes so class-scoped CSS has the same selector context.
- Custom code can be related to editable content items from the custom code editor, with relationship details retained in the custom-code list table.
- Custom code revisions preserve both authored code and execution options, and restored revisions immediately rebuild generated assets.
- The bundled CodeMirror 5 editor is maintained at version 5.65.20.
- AS Update Controller handles GitHub release discovery and installation; this plugin only registers its identity and local controller actions.
- The interface is maintained in English only.

## Folder Structure

- `as-local-css/` contains the installable WordPress plugin.
- `as-local-css/includes/` contains focused admin, editor-asset, and controller-client modules.
- `scripts/` contains the release ZIP build script.
- `dist/` is generated during packaging and is not committed.

## Important Notes

- The plugin intentionally preserves the original `custom-css-js` text domain, post type, data keys, and upload directory for compatibility.
- The legacy per-snippet external-file selector and permalink editor remain disabled; CSS delivery is managed centrally through generated bundles.
- Frontend CSS is enqueued in the document head. Legacy footer CSS remains after header CSS inside the combined bundle to preserve its relative cascade order.
- Relative URLs in authored CSS resolve from the generated bundle in `wp-content/uploads/custom-css-js`; use root-relative or absolute asset URLs when location matters.
- Only active snippets assigned to the front end are mirrored into the block editor content canvas.
- Gutenberg's native editor classes remain intact when front-end body classes are added.
- JavaScript and HTML snippets are not loaded in the editor iframe.

## Future Considerations

- Confirm compatibility against newer WordPress releases as part of each release cycle.

## Original Project Attribution

This project is based on "Simple Custom CSS and JS", originally authored by Diana Burduja and published by SilkyPress.

Original WordPress plugin: https://wordpress.org/plugins/custom-css-js/

Thank you to the original author and maintainers for the plugin that this compatible AlphaSys-controlled release continues from.

## Release Packaging

Build the uploadable WordPress package with:

```bash
scripts/build-plugin-zip.sh
```

The script creates:

- `dist/as-local-css.zip`
- `as-local-css.zip`

The ZIP contains `as-local-css/` as its top-level directory.

After publishing the matching GitHub release, refresh the AS Update Controller catalogue:

```bash
python3 scripts/publish-catalogue.py --repo as-local-css --expect-version <version>
```

Run that command from a current `cchatterton/as-update-controller` checkout, commit and push `catalogue.json`, then verify the public raw catalogue entry and checksum. The plugin release is incomplete until this catalogue publication succeeds.

## GitHub Update Metadata

- GitHub owner: `cchatterton`
- GitHub repository: `as-local-css`
- Plugin slug: `as-local-css`
- Main plugin file: `as-local-css/as-local-css.php`
- Release ZIP asset name: `as-local-css.zip`
- Author: `AlphaSys`
- Author URL: `https://alphasys.com.au`
- Update URI: `https://github.com/cchatterton/as-local-css`

## Controller migration 0.4.3

Requires WordPress 7.0 and PHP 7.4. AS Local CSS is the sole non-beta AlphaSys catalogue entry. The package preserves its original basename, snippet post type, options, generated upload files, capabilities and upstream attribution. Release metadata is managed by AS Update Controller; there is no independent updater.
