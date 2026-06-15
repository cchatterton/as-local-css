# AS Local CSS

AS Local CSS is an AlphaSys-maintained, compatible replacement for the WordPress plugin "Simple Custom CSS and JS".

The goal of this repository is a controlled release path for the same operational plugin surface:

- Custom code post type remains `custom-css-js`.
- Existing options, post meta, capabilities, upload folder, AJAX actions, and editor screens are preserved.
- Existing custom CSS, JavaScript, and HTML snippets continue to load from `wp-content/uploads/custom-css-js`.
- The plugin supports GitHub release updates from this repository.
- The interface is maintained in English only.

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

## GitHub Update Metadata

- GitHub owner: `cchatterton`
- GitHub repository: `as-local-css`
- Plugin slug: `as-local-css`
- Main plugin file: `as-local-css/as-local-css.php`
- Release ZIP asset name: `as-local-css.zip`
- Author: `AlphaSys`
- Author URL: `https://github.com/cchatterton`
- Plugin URI: `https://github.com/cchatterton/as-local-css`

