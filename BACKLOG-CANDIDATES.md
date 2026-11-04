# Backlog candidates

These items were identified while reviewing upstream Simple Custom CSS and JS 3.55. They are candidates for separate, focused changes and are not part of the inline-only / CodeMirror 5.65.20 release.

## Replace the direct metadata SQL query

The custom-code language filter in `CustomCSSandJS_Admin::parse_query()` queries the postmeta table directly. Replace it with a `WP_Meta_Query` through `$query->set( 'meta_query', ... )` while preserving the current custom post type and language-filter guards.

Acceptance criteria:

- CSS, JavaScript, and HTML filters return the same entries as the current prepared query.
- The query change affects only the `custom-css-js` main list-table query.
- Existing priority sorting and Admin Columns compatibility remain unchanged.

## Port the output-escaping improvements

Perform a context-by-context audit of admin output and port the escaping improvements introduced upstream in 3.54.1. Use `esc_html()`, `esc_attr()`, and `esc_url()` at their correct output boundaries.

Acceptance criteria:

- User-controlled admin values are escaped for their actual HTML context.
- Authored CSS, JavaScript, and HTML remain byte-for-byte unchanged where raw snippet output is required.
- CodeMirror content, generated files, front-end output, block-editor CSS, relationship controls, and revision restores continue to work.
- Intentional raw output is documented locally rather than mechanically escaped.
