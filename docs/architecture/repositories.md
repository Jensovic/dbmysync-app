# Repositories

The working copy `htdocs/dbmysync` contains **two git repositories**.

| Repository | Path | Remote | Purpose |
|---|---|---|---|
| `dbmysync-app` | `dbmysync/` (root) | `https://Jensovic@github.com/Jensovic/dbmysync-app.git` | Main application, docs, test installation, and a copy of the endpoint |
| `dbmysync-addin` | `dbmysync/endpoint/` (own `.git`) | `https://github.com/Jensovic/dbmysync-addin.git` | The endpoint package, published on Packagist as `jensovic/dbmysync-addin` |

## The endpoint is tracked twice

The root repository ignores `endpoint/.git/`, `endpoint/vendor/` and
`endpoint/composer.lock`, but it **does track the endpoint's source files**
(`endpoint/src/*`, `composer.json`, `README.md`, ...). The same files are also
tracked by the inner `dbmysync-addin` repository.

Consequences:

- A change under `endpoint/` shows up in **both** `git status` outputs and must
  be committed in **both** repositories.
- What Packagist users receive is decided only by `dbmysync-addin` and its tags,
  never by the root repository.
- Run git commands for the endpoint from inside `endpoint/` (or with
  `git -C endpoint ...`), otherwise they hit the root repository.

## Other top-level folders

| Folder | Purpose |
|---|---|
| `main-application/` | The central tool |
| `endpoint/` | The addin (see above) |
| `test-installation/` | Minimal Composer project that installs `jensovic/dbmysync-addin` from Packagist and instantiates `Endpoint` (`test.php`). Use it to check a release. |
| `docs/` | This documentation |
| `prompt.txt` | The original idea, kept for context |

## Git accounts

Both repositories are private-account repositories (GitHub user `Jensovic`,
author `jens@horing.com`). The remote URL contains the user name so the Windows
credential manager does not pick a different GitHub account.
