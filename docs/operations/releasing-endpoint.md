# Releasing the endpoint

The endpoint is published on Packagist as
[`jensovic/dbmysync-addin`](https://packagist.org/packages/jensovic/dbmysync-addin).
Packagist builds one version per **git tag** of
`github.com/Jensovic/dbmysync-addin`. Commits on `main` without a tag are only
available as `dev-main`.

## Steps

All commands run in `endpoint/` (the inner repository, see
[architecture/repositories.md](../architecture/repositories.md)).

1. Commit and push the changes in the addin repository:

   ```bash
   cd endpoint
   git status
   git push origin main
   ```

   If the same files changed in the root repository, commit them there too.

2. Tag and push the tag:

   ```bash
   git tag -a v1.2.0 -m "Short summary"
   git push origin v1.2.0
   ```

3. Packagist picks it up by itself: the GitHub webhook is installed (the
   package page says "This package is auto-updated"), the new version appears
   within a few minutes. Only if it does not, trigger it via the Packagist API
   with the API token from the Packagist profile ("Show API Token"):

   ```bash
   curl -X POST -H "Content-Type: application/json" \
     "https://packagist.org/api/update-package?username=Jensovic&apiToken=<token>" \
     -d '{"repository":{"url":"https://github.com/Jensovic/dbmysync-addin"}}'
   ```

   Or press "Update" on the package page.

4. Verify:

   ```bash
   curl -s https://repo.packagist.org/p2/jensovic/dbmysync-addin.json
   cd ../test-installation && composer update && php test.php
   ```

5. Update the projects that use the endpoint:

   ```bash
   composer update jensovic/dbmysync-addin
   ```

## Versioning

- **Patch** (`1.2.1`): bug fixes, no change in the JSON.
- **Minor** (`1.3.0`): new optional fields or actions.
- **Major** (`2.0.0`): removed/renamed fields, changed authentication.

Raising the PHP minimum was released as a minor version, since Composer keeps
installing the previous release on older PHP versions.

## Release history

| Tag | Content |
|---|---|
| `v1.0.0` | Initial release |
| `v1.1.0` | PHP 7.4 compatibility |
| `v1.2.0` (2026-11-19) | Column order (`position`), column comments, PHP 8.1+ |

Note: the local tag `v1.0.0` points to a different commit than the one on
GitHub (`git fetch --tags` reports "would clobber existing tag"). GitHub is
authoritative.
