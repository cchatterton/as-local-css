# Release completion

Follow the AlphaSys Codex standards and the AS Update Controller release workflow.

Every AS Local CSS release is incomplete until all of the following are finished:

1. Update the plugin header, `ASLC_VERSION`, readme stable tag, changelog, README version, and `update.json` to the same version.
2. Run syntax and regression tests, build `as-local-css.zip`, and commit the root ZIP.
3. Push the source commit and publish the matching GitHub tag and `as-local-css.zip` release asset.
4. In a current `cchatterton/as-update-controller` checkout, run:

   ```sh
   python3 scripts/publish-catalogue.py --repo as-local-css --expect-version <version>
   ```

5. Commit and push the resulting `catalogue.json` change without overwriting other catalogue updates.
6. Fetch the public raw controller catalogue with a unique cache-busting query and verify the AS Local CSS version, tag, basename, asset name, and SHA-256 against the published ZIP.

Do not report a release as complete before the catalogue commit and public verification succeed.
